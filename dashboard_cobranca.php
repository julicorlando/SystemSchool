<?php
// Dashboard Cobrança (visual aprimorado)
// Requisitos: conexao.php (mysqli $conn) e funcoes.php (formatar_data_br, formatar_data_hora_br)
// Permissões: acessível para perfis 'admin', 'professor' e 'cobradores'

session_start();
include "conexao.php";
include_once "funcoes.php";

if (!isset($_SESSION['tipo']) || !isset($_SESSION['id'])) {
    header("Location: index.php");
    exit;
}
$usuario_tipo = $_SESSION['tipo'];
$usuario_id = intval($_SESSION['id']);
$perfis = ['admin','professor','cobradores'];
if (!in_array($usuario_tipo, $perfis)) {
    header("Location: index.php");
    exit;
}

// Filtros (GET)
$turma_id = isset($_GET['turma_id']) && $_GET['turma_id'] !== '' ? intval($_GET['turma_id']) : 0;
$cobrador_id = isset($_GET['cobrador_id']) && $_GET['cobrador_id'] !== '' ? intval($_GET['cobrador_id']) : 0;
$mes_ref = isset($_GET['mes']) ? intval($_GET['mes']) : intval(date('n'));
$ano_ref = isset($_GET['ano']) ? intval($_GET['ano']) : intval(date('Y'));
$q_search = isset($_GET['q']) ? trim($_GET['q']) : '';

// Detectar existência de tabela/coluna cobradores links
$has_cobradores = false;
$turmas_has_cobrador = false;
$res = $conn->query("SHOW TABLES LIKE 'cobradores'");
if ($res && $res->num_rows > 0) $has_cobradores = true;
$res = $conn->query("SHOW COLUMNS FROM turmas LIKE 'cobrador_id'");
if ($res && $res->num_rows > 0) $turmas_has_cobrador = true;

// Buscar lista de turmas e cobradores para filtros
$turmas_res = $conn->query("SELECT id, nome FROM turmas ORDER BY nome");
$cobradores_res = $has_cobradores ? $conn->query("SELECT id, nome FROM cobradores WHERE ativo = 1 ORDER BY nome") : false;

// Build where clauses for filters (including search)
$where_filters = "1=1";
$params = [];
$types = "";

if ($turma_id) {
    $where_filters .= " AND a.turma_id = ?";
    $types .= "i"; $params[] = $turma_id;
}
if ($cobrador_id && $turmas_has_cobrador) {
    $where_filters .= " AND t.cobrador_id = ?";
    $types .= "i"; $params[] = $cobrador_id;
}
if ($q_search !== '') {
    // simple search on student name or matricula
    $where_filters .= " AND (a.nome LIKE CONCAT('%', ?, '%') OR a.matricula LIKE CONCAT('%', ?, '%'))";
    $types .= "ss"; $params[] = $q_search; $params[] = $q_search;
}

// Helper for bindings
function refValues($arr){
    $refs = [];
    foreach($arr as $k => $v) $refs[$k] = &$arr[$k];
    return $refs;
}

// Small helper to format currency (if not in funcoes)
function format_currency($v) { return 'R$ ' . number_format(floatval($v), 2, ',', '.'); }

// -- KPIs (same queries as before) --
$kpis = [
    'total_vencido' => 0.0,
    'total_pendente_mes' => 0.0,
    'total_recebido_mes' => 0.0,
    'qtd_mensalidades_vencidas' => 0,
];

$sql_total_vencido = "
    SELECT SUM(m.valor) AS total_vencido
    FROM mensalidades m
    JOIN contas_aluno ca ON m.conta_id = ca.id
    JOIN alunos a ON ca.aluno_id = a.id
    JOIN turmas t ON a.turma_id = t.id
    WHERE m.status = 'pendente' AND m.data_vencimento < CURDATE() AND ($where_filters)
";
$stmt = $conn->prepare($sql_total_vencido);
if ($stmt) {
    if (!empty($types)) {
        $bind = array_merge([$types], $params);
        call_user_func_array([$stmt, 'bind_param'], refValues($bind));
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $kpis['total_vencido'] = floatval($row['total_vencido'] ?? 0);
    $stmt->close();
}

$sql_pendente_mes = "
    SELECT SUM(m.valor) AS total
    FROM mensalidades m
    JOIN contas_aluno ca ON m.conta_id = ca.id
    JOIN alunos a ON ca.aluno_id = a.id
    JOIN turmas t ON a.turma_id = t.id
    WHERE m.mes_referencia = ? AND m.ano_referencia = ? AND m.status = 'pendente' AND ($where_filters)
";
$stmt = $conn->prepare($sql_pendente_mes);
if ($stmt) {
    $full_types = "ii" . $types;
    $bind = array_merge([$full_types, $mes_ref, $ano_ref], $params);
    call_user_func_array([$stmt, 'bind_param'], refValues($bind));
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $kpis['total_pendente_mes'] = floatval($row['total'] ?? 0);
    $stmt->close();
}

$sql_recebido_mes = "
    SELECT SUM(m.valor_pago) AS total
    FROM mensalidades m
    JOIN contas_aluno ca ON m.conta_id = ca.id
    JOIN alunos a ON ca.aluno_id = a.id
    JOIN turmas t ON a.turma_id = t.id
    WHERE m.mes_referencia = ? AND m.ano_referencia = ? AND m.status = 'pago' AND ($where_filters)
";
$stmt = $conn->prepare($sql_recebido_mes);
if ($stmt) {
    $full_types = "ii" . $types;
    $bind = array_merge([$full_types, $mes_ref, $ano_ref], $params);
    call_user_func_array([$stmt, 'bind_param'], refValues($bind));
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $kpis['total_recebido_mes'] = floatval($row['total'] ?? 0);
    $stmt->close();
}

$sql_qtd_venc = "
    SELECT COUNT(*) AS qtd
    FROM mensalidades m
    JOIN contas_aluno ca ON m.conta_id = ca.id
    JOIN alunos a ON ca.aluno_id = a.id
    JOIN turmas t ON a.turma_id = t.id
    WHERE m.status = 'pendente' AND m.data_vencimento < CURDATE() AND ($where_filters)
";
$stmt = $conn->prepare($sql_qtd_venc);
if ($stmt) {
    if (!empty($types)) {
        $bind = array_merge([$types], $params);
        call_user_func_array([$stmt, 'bind_param'], refValues($bind));
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $kpis['qtd_mensalidades_vencidas'] = intval($row['qtd'] ?? 0);
    $stmt->close();
}

// Aging buckets
$aging_buckets = [
    '0-30' => ['label'=>'1-30 dias','count'=>0,'sum'=>0],
    '31-60' => ['label'=>'31-60 dias','count'=>0,'sum'=>0],
    '61-90' => ['label'=>'61-90 dias','count'=>0,'sum'=>0],
    '90+' => ['label'=>'>90 dias','count'=>0,'sum'=>0],
];

$sql_aging = "
    SELECT
      CASE
        WHEN DATEDIFF(CURDATE(), m.data_vencimento) BETWEEN 1 AND 30 THEN '0-30'
        WHEN DATEDIFF(CURDATE(), m.data_vencimento) BETWEEN 31 AND 60 THEN '31-60'
        WHEN DATEDIFF(CURDATE(), m.data_vencimento) BETWEEN 61 AND 90 THEN '61-90'
        WHEN DATEDIFF(CURDATE(), m.data_vencimento) > 90 THEN '90+'
        ELSE '0-30'
      END AS bucket,
      COUNT(*) AS cnt,
      SUM(m.valor) AS sum_val
    FROM mensalidades m
    JOIN contas_aluno ca ON m.conta_id = ca.id
    JOIN alunos a ON ca.aluno_id = a.id
    JOIN turmas t ON a.turma_id = t.id
    WHERE m.status = 'pendente' AND m.data_vencimento < CURDATE() AND ($where_filters)
    GROUP BY bucket
";
$stmt = $conn->prepare($sql_aging);
if ($stmt) {
    if (!empty($types)) {
        $bind = array_merge([$types], $params);
        call_user_func_array([$stmt, 'bind_param'], refValues($bind));
    }
    $stmt->execute();
    $res_age = $stmt->get_result();
    while ($r = $res_age->fetch_assoc()) {
        $b = $r['bucket'];
        if (isset($aging_buckets[$b])) {
            $aging_buckets[$b]['count'] = intval($r['cnt']);
            $aging_buckets[$b]['sum'] = floatval($r['sum_val']);
        }
    }
    $stmt->close();
}

// Top devedores
$top_devedores = [];
$sql_top = "
    SELECT a.id AS aluno_id, a.nome, a.matricula, SUM(m.valor) AS total_devido, COUNT(m.id) AS qtd_vencidas
    FROM mensalidades m
    JOIN contas_aluno ca ON m.conta_id = ca.id
    JOIN alunos a ON ca.aluno_id = a.id
    JOIN turmas t ON a.turma_id = t.id
    WHERE m.status = 'pendente' AND m.data_vencimento < CURDATE() AND ($where_filters)
    GROUP BY a.id, a.nome, a.matricula
    ORDER BY total_devido DESC
    LIMIT 10
";
$stmt = $conn->prepare($sql_top);
if ($stmt) {
    if (!empty($types)) {
        $bind = array_merge([$types], $params);
        call_user_func_array([$stmt, 'bind_param'], refValues($bind));
    }
    $stmt->execute();
    $res_top = $stmt->get_result();
    while ($r = $res_top->fetch_assoc()) {
        $top_devedores[] = $r;
    }
    $stmt->close();
}

// Monthly chart (last 6 months)
$months = []; $billed = []; $collected = [];
for ($i = 5; $i >= 0; $i--) {
    $m = date('n', strtotime("-$i months"));
    $y = date('Y', strtotime("-$i months"));
    $months[] = date('M/Y', strtotime("-$i months"));
    // billed
    $sql_billed = "
        SELECT SUM(valor) as total
        FROM mensalidades m
        JOIN contas_aluno ca ON m.conta_id = ca.id
        JOIN alunos a ON ca.aluno_id = a.id
        JOIN turmas t ON a.turma_id = t.id
        WHERE m.mes_referencia = ? AND m.ano_referencia = ? AND ($where_filters)
    ";
    $stmt = $conn->prepare($sql_billed);
    if ($stmt) {
        $full_types = "ii" . $types;
        $bind = array_merge([$full_types, $m, $y], $params);
        call_user_func_array([$stmt, 'bind_param'], refValues($bind));
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $billed[] = floatval($row['total'] ?? 0);
        $stmt->close();
    } else { $billed[] = 0; }

    // collected
    $sql_col = "
        SELECT SUM(valor_pago) as total
        FROM mensalidades m
        JOIN contas_aluno ca ON m.conta_id = ca.id
        JOIN alunos a ON ca.aluno_id = a.id
        JOIN turmas t ON a.turma_id = t.id
        WHERE m.mes_referencia = ? AND m.ano_referencia = ? AND m.status = 'pago' AND ($where_filters)
    ";
    $stmt = $conn->prepare($sql_col);
    if ($stmt) {
        $full_types = "ii" . $types;
        $bind = array_merge([$full_types, $m, $y], $params);
        call_user_func_array([$stmt, 'bind_param'], refValues($bind));
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $collected[] = floatval($row['total'] ?? 0);
        $stmt->close();
    } else { $collected[] = 0; }
}

// Reuse main listing query to show table (same as earlier code block)
$select_contact = ($conn->query("SHOW COLUMNS FROM alunos LIKE 'telefone'")->num_rows > 0) ? "a.email, a.telefone" : "a.email";
$sql_list = "
    SELECT
        a.id AS aluno_id,
        a.nome,
        a.matricula,
        {$select_contact},
        COUNT(m.id) AS qtd_vencidas,
        SUM(m.valor) AS total_vencido,
        MAX(DATEDIFF(CURDATE(), m.data_vencimento)) AS max_dias_atraso,
        ROUND(AVG(DATEDIFF(CURDATE(), m.data_vencimento)),1) AS media_dias_atraso
    FROM mensalidades m
    JOIN contas_aluno ca ON m.conta_id = ca.id
    JOIN alunos a ON ca.aluno_id = a.id
    WHERE m.status = 'pendente' AND m.data_vencimento < CURDATE()
    GROUP BY a.id, a.nome, a.matricula, {$select_contact}
    ORDER BY total_vencido DESC, max_dias_atraso DESC
    LIMIT 200
";
$res = $conn->query($sql_list);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>Dashboard Cobrança</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="app-style.css">
    <style>
        /* Visual improvements specific for dashboard_cobranca */
        :root{
            --bg: #f4f6f9;
            --card: #ffffff;
            --muted: #6b7280;
            --accent: #2563eb;
            --accent-2: #06b6d4;
            --success: #10b981;
            --danger: #ef4444;
            --glass: rgba(255,255,255,0.6);
        }
        body { background: var(--bg); color: #0f1724; font-family: Inter, "Segoe UI", Arial, sans-serif; }
        .container { max-width:1200px; margin:28px auto; padding:20px; }
        header.topbar { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:18px; }
        .brand { display:flex; align-items:center; gap:12px; }
        .brand .logo { width:48px; height:48px; border-radius:10px; background:linear-gradient(135deg,var(--accent),var(--accent-2)); display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; box-shadow:0 6px 24px rgba(37,99,235,0.16); }
        .brand h1 { margin:0; font-size:1.25rem; }
        .controls { display:flex; gap:8px; align-items:center; }
        .btn, .btn-ghost { padding:8px 12px; border-radius:10px; border:0; cursor:pointer; font-weight:600; }
        .btn { background:var(--accent); color:#fff; box-shadow: 0 8px 24px rgba(37,99,235,0.12); }
        .btn-ghost { background:transparent; border:1px solid rgba(15,23,36,0.06); color:var(--muted); }

        .card { background:var(--card); border-radius:12px; padding:16px; box-shadow: 0 8px 30px rgba(15,23,36,0.04); }
        .kpi-grid { display:grid; grid-template-columns: repeat(4,1fr); gap:14px; margin-top:14px; }
        .kpi { padding:16px; border-radius:10px; display:flex; justify-content:space-between; align-items:center; gap:12px; }
        .kpi .meta { color:var(--muted); font-weight:600; }
        .kpi .value { font-size:1.35rem; font-weight:800; color:var(--accent); }
        .kpi .icon { width:48px;height:48px;border-radius:8px; display:flex;align-items:center;justify-content:center;color:#fff; }

        .kpi.debt .icon { background: linear-gradient(180deg,#f97316,#ea580c); }
        .kpi.pending .icon { background: linear-gradient(180deg,#7c3aed,#6d28d9); }
        .kpi.collected .icon { background: linear-gradient(180deg,#06b6d4,#0891b2); }
        .kpi.avg .icon { background: linear-gradient(180deg,#10b981,#059669); }

        .aging-grid { display:flex; gap:12px; margin-top:12px; }
        .aging { padding:12px; border-radius:10px; min-width:150px; background:linear-gradient(180deg,#fbfdff,#f7fbff); border:1px solid rgba(37,99,235,0.03); }
        .aging .label { font-weight:700; color:var(--muted); }
        .aging .sum { font-size:1.05rem; font-weight:800; color:var(--danger); margin-top:6px; }

        .top-table table { width:100%; border-collapse:collapse; margin-top:8px; }
        .top-table th, .top-table td { padding:10px 12px; text-align:left; border-bottom:1px solid #edf2f7; }
        .top-table th { background:transparent; color:var(--muted); font-weight:700; font-size:0.95rem; }
        .badge { padding:6px 10px; border-radius:999px; font-weight:700; color:#fff; font-size:0.85rem; }
        .badge.vencido { background: linear-gradient(90deg,#f97316,#ea580c); }
        .badge.pago { background: linear-gradient(90deg,#10b981,#059669); }
        .controls .filter { padding:8px 10px; border-radius:8px; border:1px solid #e6eef8; }

        .grid-2 { display:grid; grid-template-columns: 1fr 420px; gap:16px; margin-top:16px; }
        @media (max-width:1024px){ .kpi-grid{ grid-template-columns: repeat(2,1fr);} .grid-2{ grid-template-columns:1fr; } }
        .table-responsive { overflow:auto; }
        .search-input { padding:8px 10px; border-radius:8px; border:1px solid #e6eef8; width:220px; }
        .small { color:var(--muted); font-size:0.95rem; }

        footer { margin-top:18px; text-align:center; color:var(--muted); font-size:0.9rem; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="container">
    <header class="topbar">
        <div class="brand">
            <div class="logo">CB</div>
            <div>
                <h1>Dashboard Cobrança</h1>
                <div class="small">Visão geral das pendências e desempenho</div>
            </div>
        </div>

        <div class="controls">
            <form method="get" style="display:flex;align-items:center;gap:8px;">
                <select name="turma_id" class="filter" onchange="this.form.submit()">
                    <option value="">Todas turmas</option>
                    <?php if ($turmas_res) { $turmas_res->data_seek(0); while ($t = $turmas_res->fetch_assoc()): ?>
                        <option value="<?= intval($t['id']) ?>" <?= $turma_id == $t['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['nome']) ?>
                        </option>
                    <?php endwhile; } ?>
                </select>

                <?php if ($has_cobradores && $turmas_has_cobrador): ?>
                    <select name="cobrador_id" class="filter" onchange="this.form.submit()">
                        <option value="">Todos cobradores</option>
                        <?php if ($cobradores_res) { $cobradores_res->data_seek(0); while ($c = $cobradores_res->fetch_assoc()): ?>
                            <option value="<?= intval($c['id']) ?>" <?= $cobrador_id == $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nome']) ?>
                            </option>
                        <?php endwhile; } ?>
                    </select>
                <?php endif; ?>

                <select name="mes" class="filter" onchange="this.form.submit()">
                    <?php for ($m=1;$m<=12;$m++): ?>
                        <option value="<?= $m ?>" <?= $mes_ref == $m ? 'selected' : '' ?>><?= date('M', mktime(0,0,0,$m,1)) ?></option>
                    <?php endfor; ?>
                </select>

                <select name="ano" class="filter" onchange="this.form.submit()">
                    <?php for($y=date('Y')-1;$y<=date('Y');$y++): ?>
                        <option value="<?= $y ?>" <?= $ano_ref == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>

                <input type="search" name="q" class="search-input" placeholder="Pesquisar aluno ou matrícula..." value="<?= htmlspecialchars($q_search) ?>">
                <button class="btn" title="Aplicar filtros"><i class="fa-solid fa-filter" style="margin-right:6px"></i>Filtrar</button>
            </form>

            <a class="btn-ghost" href="cobrancas.php" title="Lista completa">Lista</a>
            <a class="btn-ghost" href="?download=top10" title="Exportar top10">Exportar</a>
        </div>
    </header>

    <section class="card">
        <div class="kpi-grid">
            <div class="kpi debt">
                <div>
                    <div class="meta">Total Vencido (hoje)</div>
                    <div class="value"><?= format_currency($kpis['total_vencido']) ?></div>
                    <div class="small">Mensalidades vencidas: <?= intval($kpis['qtd_mensalidades_vencidas']) ?></div>
                </div>
                <div class="icon"><i class="fa-solid fa-calendar-xmark"></i></div>
            </div>

            <div class="kpi pending">
                <div>
                    <div class="meta">Pendente (mês <?= $mes_ref ?>/<?= $ano_ref ?>)</div>
                    <div class="value"><?= format_currency($kpis['total_pendente_mes']) ?></div>
                    <div class="small">Previsto para receber</div>
                </div>
                <div class="icon"><i class="fa-solid fa-hourglass-half"></i></div>
            </div>

            <div class="kpi collected">
                <div>
                    <div class="meta">Recebido (mês <?= $mes_ref ?>/<?= $ano_ref ?>)</div>
                    <div class="value"><?= format_currency($kpis['total_recebido_mes']) ?></div>
                    <div class="small">Entradas registradas</div>
                </div>
                <div class="icon"><i class="fa-solid fa-hand-holding-dollar"></i></div>
            </div>

            <div class="kpi avg">
                <div>
                    <div class="meta">Média dias atraso</div>
                    <div class="value"><?= number_format(floatval($totais['media_dias_atraso'] ?? 0),1,',','.') ?> dias</div>
                    <div class="small">Alunos em atraso: <?= intval($totais['alunos_atraso'] ?? 0) ?></div>
                </div>
                <div class="icon"><i class="fa-solid fa-clock"></i></div>
            </div>
        </div>

        <div class="grid-2">
            <div>
                <h3 style="margin-top:14px">Aging buckets</h3>
                <div class="aging-grid">
                    <?php foreach ($aging_buckets as $k => $b): ?>
                        <div class="aging card">
                            <div class="label"><?= htmlspecialchars($b['label']) ?></div>
                            <div class="sum"><?= format_currency($b['sum']) ?></div>
                            <div class="small" style="margin-top:6px">Quantidade: <?= intval($b['count']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <h3 style="margin-top:18px">Top Devedores</h3>
                <div class="top-table card" style="margin-top:8px;">
                    <?php if (!empty($top_devedores)): ?>
                        <table>
                            <thead>
                                <tr><th>Aluno</th><th>Matrícula</th><th>Qtd</th><th>Total</th><th></th></tr>
                            </thead>
                            <tbody>
                                <?php foreach($top_devedores as $d): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($d['nome']) ?></td>
                                        <td><?= htmlspecialchars($d['matricula']) ?></td>
                                        <td><?= intval($d['qtd_vencidas']) ?></td>
                                        <td><?= format_currency($d['total_devido']) ?></td>
                                        <td><a class="btn-ghost" href="informacoes_aluno.php?id=<?= intval($d['aluno_id']) ?>">Abrir</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="small">Nenhum devedor encontrado.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <h3 style="margin-top:14px">Faturado vs Recebido (últimos 6 meses)</h3>
                <div class="card" style="padding:12px">
                    <canvas id="chartMonthly" style="width:100%;height:320px"></canvas>
                </div>
            </div>
        </div>
    </section>

    <section style="margin-top:16px" class="card">
        <h3 style="margin:0 0 8px 0">Lista resumida de alunos em atraso</h3>
        <div class="table-responsive" style="margin-top:12px">
            <table role="table" aria-label="Alunos em atraso" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="background:#fbfdff;">
                        <th style="padding:10px 12px">Aluno</th>
                        <th style="padding:10px 12px">Matrícula</th>
                        <th style="padding:10px 12px">Contato</th>
                        <th style="padding:10px 12px">Qtd vencidas</th>
                        <th style="padding:10px 12px">Total vencido</th>
                        <th style="padding:10px 12px">Máx dias atraso</th>
                        <th style="padding:10px 12px">Média dias atraso</th>
                        <th style="padding:10px 12px">Ações</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if ($res && $res->num_rows > 0) {
                    $res->data_seek(0);
                    while ($row = $res->fetch_assoc()):
                ?>
                    <tr style="border-bottom:1px solid #eef2f7">
                        <td style="padding:10px 12px"><?= htmlspecialchars($row['nome']) ?></td>
                        <td style="padding:10px 12px"><?= htmlspecialchars($row['matricula']) ?></td>
                        <td style="padding:10px 12px">
                            <?= htmlspecialchars($row['email'] ?? '') ?>
                            <?php if (!empty($row['telefone'])): ?>
                                <div class="small">Tel: <?= htmlspecialchars($row['telefone']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="padding:10px 12px"><?= intval($row['qtd_vencidas']) ?></td>
                        <td style="padding:10px 12px"><?= format_currency($row['total_vencido']) ?></td>
                        <td style="padding:10px 12px"><?= intval($row['max_dias_atraso']) ?></td>
                        <td style="padding:10px 12px"><?= floatval($row['media_dias_atraso']) ?></td>
                        <td style="padding:10px 12px">
                            <a class="btn-ghost" href="informacoes_aluno.php?id=<?= intval($row['aluno_id']) ?>">Abrir</a>
                            <a class="btn-ghost" href="comunicacao.php?chat=aluno-<?= intval($row['aluno_id']) ?>">Mensagem</a>
                        </td>
                    </tr>
                <?php
                    endwhile;
                } else {
                    echo '<tr><td colspan="8" class="small" style="padding:12px">Nenhum aluno em atraso encontrado para os filtros selecionados.</td></tr>';
                }
                ?>
                </tbody>
            </table>
        </div>
    </section>

    <footer>
        Gerado em <?= date('d/m/Y H:i') ?> — Perfil: <?= htmlspecialchars($usuario_tipo) ?>
    </footer>
</div>

<script>
    // Chart
    const months = <?= json_encode($months) ?>;
    const billed = <?= json_encode($billed) ?>;
    const collected = <?= json_encode($collected) ?>;
    const ctx = document.getElementById('chartMonthly').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: months,
            datasets: [
                { label: 'Faturado', data: billed, backgroundColor: 'rgba(37,99,235,0.85)', borderRadius:6 },
                { label: 'Recebido', data: collected, backgroundColor: 'rgba(6,182,212,0.85)', borderRadius:6 }
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { labels: { boxWidth:12, padding:12 } },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': R$ ' + Number(context.parsed.y).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                        }
                    }
                }
            },
            scales: {
                x: { stacked: false },
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(v){ return 'R$ ' + v.toLocaleString('pt-BR'); }
                    }
                }
            }
        }
    });
</script>
</body>
</html>