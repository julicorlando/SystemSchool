<?php
// Página: Setor de Cobranças
// Exibe lista de alunos com mensalidades em atraso: nome, contato, valores e dias em atraso
// Requer: conexao.php (variável $conn) e funcoes.php (opcionais: formatar_data_br)
// Segurança: restringe acesso a usuários logados (ajuste conforme sua autenticação)

// Observação: Ajustado para permitir acesso a "cobradores" além de "admin" e "professor".

session_start();
include "conexao.php";
include_once "funcoes.php";

// Verifica login — ajuste conforme seu sistema de permissões
if (!isset($_SESSION['tipo']) || !isset($_SESSION['id'])) {
    header("Location: index.php");
    exit;
}

// Permitir admin, professor e cobradores
$usuario_tipo = $_SESSION['tipo'];
$perfis_permitidos = ['admin', 'professor', 'cobradores']; // 'cobradores' adicionado
if (!in_array($usuario_tipo, $perfis_permitidos)) {
    // usuário não autorizado
    header("Location: index.php");
    exit;
}

// Verifica se coluna telefone existe (para mostrar contato telefônico se houver)
$has_telefone = false;
$res_col = $conn->query("SHOW COLUMNS FROM alunos LIKE 'telefone'");
if ($res_col && $res_col->num_rows > 0) $has_telefone = true;

// Filtros
$turma_id = isset($_GET['turma_id']) && $_GET['turma_id'] !== '' ? intval($_GET['turma_id']) : 0;
$q_search = isset($_GET['q']) ? trim($_GET['q']) : '';
$export_csv = isset($_GET['export']) && $_GET['export'] === 'csv';

// Buscar lista de turmas para filtro
$turmas_res = $conn->query("SELECT id, nome FROM turmas ORDER BY nome");

// Build SQL - agregação por aluno de mensalidades vencidas (status pendente e data_vencimento < hoje)
$where_parts = [];
$params = [];
$types = "";

// somente pendentes vencidos
$where_parts[] = "m.status = 'pendente' AND m.data_vencimento < CURDATE()";

// filtro por turma
if ($turma_id) {
    $where_parts[] = "a.turma_id = ?";
    $types .= "i";
    $params[] = $turma_id;
}

// pesquisa por nome/matrícula/email
if ($q_search !== '') {
    $where_parts[] = "(a.nome LIKE CONCAT('%', ?, '%') OR a.matricula LIKE CONCAT('%', ?, '%') OR a.email LIKE CONCAT('%', ?, '%'))";
    $types .= "sss";
    $params[] = $q_search;
    $params[] = $q_search;
    $params[] = $q_search;
}

$where_sql = implode(" AND ", $where_parts);

// Seleciona campos dinamicamente (telefone só se existir)
$select_contact = $has_telefone ? "a.email, a.telefone" : "a.email";

// SQL principal: agregação por aluno
$sql = "
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
    WHERE {$where_sql}
    GROUP BY a.id, a.nome, a.matricula" .
    ($has_telefone ? ", a.email, a.telefone" : ", a.email") . "
    ORDER BY total_vencido DESC, max_dias_atraso DESC
";

// Preparar e executar
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Erro na query: " . $conn->error);
}
if (!empty($types)) {
    // bind dinamicamente
    $bind_names = [];
    $bind_names[] = $types;
    for ($i = 0; $i < count($params); $i++) {
        $bind_name = 'bind' . $i;
        $$bind_name = $params[$i];
        $bind_names[] = &$$bind_name;
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_names);
}
$stmt->execute();
$res = $stmt->get_result();

// Se export CSV
if ($export_csv) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename=cobrancas_'.date('Ymd_His').'.csv');
    $out = fopen('php://output', 'w');
    // cabeçalho
    $header = ['Aluno','Matrícula','E-mail'];
    if ($has_telefone) $header[] = 'Telefone';
    $header = array_merge($header, ['Qtd Vencidas','Total Vencido','Máx Dias Atraso','Média Dias Atraso']);
    fputcsv($out, $header);
    while ($row = $res->fetch_assoc()) {
        $r = [$row['nome'],$row['matricula'],$row['email']];
        if ($has_telefone) $r[] = $row['telefone'];
        $r[] = $row['qtd_vencidas'];
        $r[] = number_format($row['total_vencido'],2,'.',',');
        $r[] = $row['max_dias_atraso'];
        $r[] = $row['media_dias_atraso'];
        fputcsv($out, $r);
    }
    fclose($out);
    exit;
}

// Calcular totais gerais para o topo
$totais_sql = "
    SELECT
        COUNT(DISTINCT a.id) AS alunos_atraso,
        SUM(m.valor) AS valor_total_vencido,
        ROUND(AVG(DATEDIFF(CURDATE(), m.data_vencimento)),1) AS media_dias_atraso
    FROM mensalidades m
    JOIN contas_aluno ca ON m.conta_id = ca.id
    JOIN alunos a ON ca.aluno_id = a.id
    WHERE m.status = 'pendente' AND m.data_vencimento < CURDATE()
";
if ($turma_id) {
    $totais_sql .= " AND a.turma_id = " . intval($turma_id);
}
$totais_res = $conn->query($totais_sql);
$totais = $totais_res ? $totais_res->fetch_assoc() : ['alunos_atraso'=>0,'valor_total_vencido'=>0,'media_dias_atraso'=>0];

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>Setor de Cobranças</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        /* Minimal styles; você pode linkar seu app-style.css ou outro arquivo global */
        body { font-family: Arial, Helvetica, sans-serif; background:#f4f6f8; color:#111; margin:0; padding:0; }
        .wrap { max-width:1200px; margin:24px auto; padding:20px; background:#fff; border-radius:10px; box-shadow:0 6px 20px rgba(0,0,0,0.06); }
        .top { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; }
        .filters { display:flex; gap:8px; align-items:center; }
        select,input { padding:8px 10px; border:1px solid #d0d6dc; border-radius:8px; }
        button { padding:9px 12px; border-radius:8px; border:0; background:#2563eb; color:#fff; cursor:pointer; }
        .stats { display:flex; gap:12px; margin-top:16px; flex-wrap:wrap; }
        .stat { background:#f4f7fb; padding:12px 14px; border-radius:8px; min-width:180px; }
        table { width:100%; border-collapse:collapse; margin-top:18px; }
        th,td { padding:12px 10px; border-bottom:1px solid #e8edf1; text-align:left; }
        th { background:#f6f8fa; font-weight:700; }
        tr:hover { background:#fbfdff; }
        .actions { display:flex; gap:8px; }
        .btn-ghost { background:transparent; border:1px solid #cbd5df; color:#111; padding:8px 10px; border-radius:8px; cursor:pointer; }
        .small { font-size:0.9em; color:#666; }
        @media (max-width:800px) {
            .top { flex-direction:column; align-items:flex-start; }
            table, thead, tbody, th, td, tr { display:block; }
            tr { margin-bottom:12px; }
            th { display:none; }
            td { display:flex; justify-content:space-between; padding:8px 6px; border-bottom:1px solid #eee; }
            td::after { content:""; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div>
            <h1 style="margin:0 0 4px 0">Setor de Cobranças</h1>
            <div class="small">Lista de alunos com mensalidades em atraso (dados consultados no banco)</div>
        </div>
        <div class="filters">
            <form method="get" style="display:flex;gap:8px;align-items:center;">
                <input type="hidden" name="export" value="">
                <select name="turma_id">
                    <option value="">Todas as turmas</option>
                    <?php if ($turmas_res) { $turmas_res->data_seek(0); while ($t = $turmas_res->fetch_assoc()): ?>
                        <option value="<?= intval($t['id']) ?>" <?= $turma_id == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nome']) ?></option>
                    <?php endwhile; } ?>
                </select>
                <input type="search" name="q" placeholder="Pesquisar nome, matrícula ou e-mail" value="<?= htmlspecialchars($q_search) ?>">
                <button type="submit">Filtrar</button>
            </form>

            <form method="get" style="margin-left:6px;">
                <input type="hidden" name="turma_id" value="<?= intval($turma_id) ?>">
                <input type="hidden" name="q" value="<?= htmlspecialchars($q_search) ?>">
                <input type="hidden" name="export" value="csv">
                <button type="submit" class="btn-ghost">Exportar CSV</button>
            </form>
        </div>
    </div>

    <div class="stats" aria-hidden="false">
        <div class="stat">
            <div class="small">Alunos em atraso</div>
            <div style="font-size:1.25rem;font-weight:700"><?= intval($totais['alunos_atraso']) ?></div>
        </div>
        <div class="stat">
            <div class="small">Valor total vencido</div>
            <div style="font-size:1.25rem;font-weight:700">R$ <?= number_format(floatval($totais['valor_total_vencido']),2,',','.') ?></div>
        </div>
        <div class="stat">
            <div class="small">Média dias de atraso</div>
            <div style="font-size:1.25rem;font-weight:700"><?= floatval($totais['media_dias_atraso']) ?></div>
        </div>
        <div class="stat">
            <div class="small">Gerenciar</div>
            <div class="actions">
                <a href="dashboard_admin.php" class="btn-ghost">Voltar</a>
                <form method="get" style="margin:0;">
                    <input type="hidden" name="export" value="csv">
                    <button type="submit" class="btn-ghost">Exportar completo</button>
                </form>
            </div>
        </div>
    </div>

    <?php if ($res && $res->num_rows > 0): ?>
        <div class="table-responsive">
            <table role="table" aria-label="Alunos em atraso">
                <thead>
                    <tr>
                        <th>Aluno</th>
                        <th>Matrícula</th>
                        <th>Contato</th>
                        <th>Qtd vencidas</th>
                        <th>Total vencido</th>
                        <th>Máx dias atraso</th>
                        <th>Média dias atraso</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $res->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['nome']) ?></td>
                            <td><?= htmlspecialchars($row['matricula']) ?></td>
                            <td>
                                <?= htmlspecialchars($row['email'] ?? '') ?>
                                <?php if ($has_telefone && !empty($row['telefone'])): ?>
                                    <br><span class="small">Tel: <?= htmlspecialchars($row['telefone']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= intval($row['qtd_vencidas']) ?></td>
                            <td>R$ <?= number_format(floatval($row['total_vencido']),2,',','.') ?></td>
                            <td><?= intval($row['max_dias_atraso']) ?></td>
                            <td><?= floatval($row['media_dias_atraso']) ?></td>
                            <td>
                                <div style="display:flex;gap:6px;">
                                    <a class="btn-ghost" href="informacoes_aluno.php?id=<?= intval($row['aluno_id']) ?>" title="Abrir cadastro">Abrir</a>
                                    <a class="btn-ghost" href="comunicacao.php?chat=aluno-<?= intval($row['aluno_id']) ?>" title="Enviar mensagem">Mensagem</a>
                                    <!-- se desejar, pode adicionar ação para gerar boleto ou marcar pago -->
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="small">Nenhum aluno em atraso encontrado para os filtros selecionados.</p>
    <?php endif; ?>

</div>
</body>
</html>