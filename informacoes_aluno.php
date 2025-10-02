<?php
// informacoes_aluno.php
// Página: Informações do Aluno + ações: ativar/inativar, negociar valor, dar baixa como pago, aplicar % de juros por atraso
// Requer: conexao.php ($conn) e funcoes.php (formatar_data_br, formatar_data_hora_br)
// Segurança: verifica sessão; apenas usuários autenticados podem executar ações.
// Nota: esta versão calcula juros proporcional por dia (juros mensal % / 30 * dias em atraso).
// Ao aplicar juros com persistência, registra um log como mensagem enviada ao aluno (não cria coluna extra).

session_start();
include "conexao.php";
include_once "funcoes.php";

// DEBUG: remover em produção
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Autenticação básica ---
if (!isset($_SESSION['tipo']) || !isset($_SESSION['id'])) {
    header("Location: index.php");
    exit;
}
$usuario_tipo = $_SESSION['tipo'];
$usuario_id_sess = intval($_SESSION['id']);

// CSRF token
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
$csrf_token = $_SESSION['csrf_token'];

// aluno id
$aluno_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['aluno_id']) ? intval($_POST['aluno_id']) : 0);
if ($aluno_id <= 0) die("Aluno não especificado.");

// Checar colunas opcionais
$has_telefone = ($conn->query("SHOW COLUMNS FROM alunos LIKE 'telefone'") && $conn->query("SHOW COLUMNS FROM alunos LIKE 'telefone'")->num_rows > 0);
$has_aluno_ativo = ($conn->query("SHOW COLUMNS FROM alunos LIKE 'ativo'") && $conn->query("SHOW COLUMNS FROM alunos LIKE 'ativo'")->num_rows > 0);

// Helper
function format_currency($v){ return 'R$ ' . number_format(floatval($v),2,',','.'); }
function days_overdue($date_venc){
    $d = (strtotime(date('Y-m-d')) - strtotime($date_venc)) / (60*60*24);
    return $d > 0 ? intval(floor($d)) : 0;
}

// --- Processar ações POST ---
$flash_success = '';
$flash_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    // validate csrf
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $flash_error = "Token inválido. Atualize a página e tente novamente.";
    } else {
        $acao = $_POST['acao'];

        if ($acao === 'toggle_aluno') {
            // Ativar/Inativar aluno (ou contas)
            if ($has_aluno_ativo) {
                $s = $conn->prepare("SELECT ativo FROM alunos WHERE id = ?");
                $s->bind_param("i", $aluno_id); $s->execute(); $r = $s->get_result()->fetch_assoc(); $s->close();
                $atual = $r ? intval($r['ativo']) : 1;
                $novo = $atual ? 0 : 1;
                $u = $conn->prepare("UPDATE alunos SET ativo = ? WHERE id = ?");
                $u->bind_param("ii", $novo, $aluno_id);
                if ($u->execute()) $flash_success = $novo ? "Aluno ativado." : "Aluno inativado.";
                else $flash_error = "Erro ao alterar status: " . $u->error;
                $u->close();
            } else {
                // fallback: alterna contas_aluno
                $s = $conn->prepare("SELECT SUM(ativo) as soma FROM contas_aluno WHERE aluno_id = ?");
                $s->bind_param("i", $aluno_id); $s->execute(); $r = $s->get_result()->fetch_assoc(); $s->close();
                $soma = intval($r['soma']);
                $novo = $soma > 0 ? 0 : 1;
                $u = $conn->prepare("UPDATE contas_aluno SET ativo = ? WHERE aluno_id = ?");
                $u->bind_param("ii", $novo, $aluno_id);
                if ($u->execute()) $flash_success = $novo ? "Contas ativadas." : "Contas inativadas.";
                else $flash_error = "Erro ao alterar contas: " . $u->error;
                $u->close();
            }
        }

        if ($acao === 'negociar_valor') {
            $mensalidade_id = intval($_POST['mensalidade_id'] ?? 0);
            $novo_valor_raw = trim($_POST['novo_valor'] ?? '');
            $observacao = trim($_POST['observacao'] ?? '');
            $novo_valor = floatval(str_replace(',', '.', str_replace('.', '', $novo_valor_raw)));
            if ($mensalidade_id <= 0 || $novo_valor <= 0) {
                $flash_error = "Mensalidade ou valor inválido.";
            } else {
                $u = $conn->prepare("UPDATE mensalidades SET valor = ? WHERE id = ?");
                $u->bind_param("di", $novo_valor, $mensalidade_id);
                if ($u->execute()) {
                    $flash_success = "Valor negociado atualizado.";
                    // log como mensagem
                    $log_assunto = "Negociação de mensalidade #$mensalidade_id";
                    $log_msg = "Valor negociado para " . format_currency($novo_valor) . ($observacao ? "\nObservação: $observacao" : "");
                    $stmt_log = $conn->prepare("INSERT INTO mensagens (remetente_id, remetente_tipo, destinatario_id, destinatario_tipo, assunto, mensagem, created_at) VALUES (?, ?, ?, 'aluno', ?, ?, NOW())");
                    if ($stmt_log) {
                        $remet_tipo = $usuario_tipo; $remet_id = $usuario_id_sess;
                        $stmt_log->bind_param("isiss", $remet_id, $remet_tipo, $aluno_id, $log_assunto, $log_msg);
                        $stmt_log->execute(); $stmt_log->close();
                    }
                } else $flash_error = "Erro ao atualizar: " . $u->error;
                $u->close();
            }
        }

        if ($acao === 'registrar_pagamento') {
            $mensalidade_id = intval($_POST['mensalidade_id'] ?? 0);
            $valor_pago_raw = trim($_POST['valor_pago'] ?? '');
            $data_pagamento = $_POST['data_pagamento'] ?? date('Y-m-d');
            $valor_pago = floatval(str_replace(',', '.', str_replace('.', '', $valor_pago_raw)));
            if ($mensalidade_id <= 0 || $valor_pago <= 0) {
                $flash_error = "Dados inválidos.";
            } else {
                $u = $conn->prepare("UPDATE mensalidades SET status = 'pago', data_pagamento = ?, valor_pago = ? WHERE id = ?");
                $u->bind_param("sdi", $data_pagamento, $valor_pago, $mensalidade_id);
                if ($u->execute()) {
                    $flash_success = "Pagamento registrado.";
                    $log_assunto = "Pagamento registrado #$mensalidade_id";
                    $log_msg = "Pagamento de " . format_currency($valor_pago) . " registrado em $data_pagamento.";
                    $stmt_log = $conn->prepare("INSERT INTO mensagens (remetente_id, remetente_tipo, destinatario_id, destinatario_tipo, assunto, mensagem, created_at) VALUES (?, ?, ?, 'aluno', ?, ?, NOW())");
                    if ($stmt_log) {
                        $remet_tipo = $usuario_tipo; $remet_id = $usuario_id_sess;
                        $stmt_log->bind_param("isiss", $remet_id, $remet_tipo, $aluno_id, $log_assunto, $log_msg);
                        $stmt_log->execute(); $stmt_log->close();
                    }
                } else $flash_error = "Erro: " . $u->error;
                $u->close();
            }
        }

        if ($acao === 'aplicar_juros') {
            // campos: juros_percent, aplicar (todos|selecionados), mens_ids[] (opcional), persist (on)
            $juros_percent = floatval(str_replace(',', '.', str_replace('.', '', trim($_POST['juros_percent'] ?? '0'))));
            $aplicar = $_POST['aplicar'] ?? 'todos';
            $persist = isset($_POST['persist']) ? true : false;
            $selected = $_POST['mens_ids'] ?? [];

            if ($juros_percent <= 0) {
                $flash_error = "Informe um percentual de juros válido (>0).";
            } else {
                // build list of mensalidades to affect
                $mens_ids = [];
                if ($aplicar === 'selecionados') {
                    foreach ($selected as $mid) {
                        $mid_i = intval($mid);
                        if ($mid_i > 0) $mens_ids[] = $mid_i;
                    }
                } else {
                    // todos vencidos e pendentes do aluno
                    // buscar mensalidades pendentes com vencimento < hoje para as contas do aluno
                    $sql = "SELECT m.id, m.valor, m.data_vencimento FROM mensalidades m JOIN contas_aluno ca ON m.conta_id = ca.id WHERE ca.aluno_id = ? AND m.status = 'pendente' AND m.data_vencimento < CURDATE()";
                    $s = $conn->prepare($sql);
                    $s->bind_param("i", $aluno_id); $s->execute(); $res_m = $s->get_result();
                    while ($r = $res_m->fetch_assoc()) $mens_ids[] = intval($r['id']);
                    $s->close();
                }

                if (empty($mens_ids)) {
                    $flash_error = "Nenhuma mensalidade selecionada para aplicar juros.";
                } else {
                    // process each mensalidade: calcular juros e (se persist) atualizar valor; sempre registrar log mensagem
                    $updated = 0; $errors = 0; $details = [];
                    $stmt_select = $conn->prepare("SELECT id, valor, data_vencimento FROM mensalidades WHERE id = ?");
                    $stmt_update = $conn->prepare("UPDATE mensalidades SET valor = ? WHERE id = ?");
                    $stmt_log = $conn->prepare("INSERT INTO mensagens (remetente_id, remetente_tipo, destinatario_id, destinatario_tipo, assunto, mensagem, created_at) VALUES (?, ?, ?, 'aluno', ?, ?, NOW())");
                    foreach ($mens_ids as $mid) {
                        $stmt_select->bind_param("i", $mid);
                        $stmt_select->execute();
                        $mres = $stmt_select->get_result();
                        if ($mrow = $mres->fetch_assoc()) {
                            $valor = floatval($mrow['valor']);
                            $dias = days_overdue($mrow['data_vencimento']);
                            // juros proporcional por dia (juros % ao mês)
                            $juros = round($valor * ($juros_percent / 100) * ($dias / 30), 2);
                            $novo_valor = round($valor + $juros, 2);
                            if ($persist) {
                                $stmt_update->bind_param("di", $novo_valor, $mid);
                                if ($stmt_update->execute()) {
                                    $updated++;
                                } else {
                                    $errors++;
                                }
                            }
                            // log message
                            if ($stmt_log) {
                                $remet_tipo = $usuario_tipo; $remet_id = $usuario_id_sess;
                                $ass = "Juros aplicado (mensalidade #$mid)";
                                $msg = "Juros de {$juros_percent}% aplicado pro rata ($dias dias). Valor original: " . format_currency($valor) . ". Juros: " . format_currency($juros) . ". Valor final: " . format_currency($novo_valor) . ".";
                                $stmt_log->bind_param("isiss", $remet_id, $remet_tipo, $aluno_id, $ass, $msg);
                                $stmt_log->execute();
                            }
                            $details[] = ['id'=>$mid,'orig'=>$valor,'juros'=>$juros,'novo'=>$novo_valor,'dias'=>$dias];
                        }
                    }
                    if ($stmt_select) $stmt_select->close();
                    if ($stmt_update) $stmt_update->close();
                    if ($stmt_log) $stmt_log->close();

                    if ($errors === 0) {
                        $flash_success = ($persist ? "Juros aplicados e salvos em $updated mensalidades." : "Juros calculados e registrados em log (sem salvar valores).");
                    } else {
                        $flash_error = "Alguns erros ocorreram ao atualizar mensalidades.";
                    }
                    // store details in session to show summary
                    $_SESSION['juros_details'] = $details;
                }
            }
        }
    }
    // redirect to avoid repost
    $_SESSION['msg_sucesso'] = $flash_success;
    $_SESSION['msg_erro'] = $flash_error;
    header("Location: informacoes_aluno.php?id=" . $aluno_id);
    exit;
}

// --- Buscar dados do aluno e relacionamentos (igual ao código anterior) ---
$sql = "
    SELECT a.*, t.nome AS turma_nome, t.id AS turma_id, p.id AS professor_id, p.nome AS professor_nome,
           ca.id AS conta_id, ca.plano_id, ca.ativo AS conta_ativa, ca.data_inicio,
           pp.nome AS plano_nome, pp.valor_mensalidade, pp.valor_matricula
    FROM alunos a
    LEFT JOIN turmas t ON a.turma_id = t.id
    LEFT JOIN professores p ON t.professor_id = p.id
    LEFT JOIN contas_aluno ca ON ca.aluno_id = a.id
    LEFT JOIN planos_pagamento pp ON ca.plano_id = pp.id
    WHERE a.id = ?
";
$stmt = $conn->prepare($sql);
if (!$stmt) die("Erro no banco: " . $conn->error);
$stmt->bind_param("i", $aluno_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) { $stmt->close(); die("Aluno não encontrado."); }
$aluno = null; $contas = [];
while ($row = $res->fetch_assoc()) {
    if (!$aluno) $aluno = $row;
    if (!empty($row['conta_id'])) $contas[intval($row['conta_id'])] = [
        'conta_id'=>intval($row['conta_id']),
        'plano_id'=>intval($row['plano_id']),
        'plano_nome'=>$row['plano_nome'],
        'valor_mensalidade'=>$row['valor_mensalidade'],
        'valor_matricula'=>$row['valor_matricula'],
        'conta_ativa'=>intval($row['conta_ativa']),
        'data_inicio'=>$row['data_inicio']
    ];
}
$stmt->close();

// mensalidades
$mensalidades = [];
if (!empty($contas)) {
    $in = implode(',', array_map('intval', array_keys($contas)));
    $sql_m = "SELECT m.* FROM mensalidades m WHERE m.conta_id IN ($in) ORDER BY m.ano_referencia DESC, m.mes_referencia DESC, m.data_vencimento DESC";
    $res_m = $conn->query($sql_m);
    if ($res_m) while ($r = $res_m->fetch_assoc()) $mensalidades[] = $r;
}

// mensagens
$sql_msg = "
    SELECT m.*,
           COALESCE(a.usuario, p.nome, al.nome) AS remetente_nome,
           COALESCE(ad.usuario, pd.nome, ald.nome) AS destinatario_nome
    FROM mensagens m
    LEFT JOIN admins a ON m.remetente_tipo = 'admin' AND m.remetente_id = a.id
    LEFT JOIN professores p ON m.remetente_tipo = 'professor' AND m.remetente_id = p.id
    LEFT JOIN alunos al ON m.remetente_tipo = 'aluno' AND m.remetente_id = al.id
    LEFT JOIN admins ad ON m.destinatario_tipo = 'admin' AND m.destinatario_id = ad.id
    LEFT JOIN professores pd ON m.destinatario_tipo = 'professor' AND m.destinatario_id = pd.id
    LEFT JOIN alunos ald ON m.destinatario_tipo = 'aluno' AND m.destinatario_id = ald.id
    WHERE (m.remetente_tipo = 'aluno' AND m.remetente_id = ?) OR (m.destinatario_tipo = 'aluno' AND m.destinatario_id = ?)
    ORDER BY m.created_at DESC
    LIMIT 500
";
$stmt = $conn->prepare($sql_msg);
$mensagens = [];
if ($stmt) {
    $stmt->bind_param("ii", $aluno_id, $aluno_id);
    $stmt->execute();
    $res_msg = $stmt->get_result();
    $mensagens = $res_msg ? $res_msg->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}

// resumo financeiro
$summary = ['total_mensalidades'=>0,'total_vencido'=>0.0,'total_pago'=>0.0,'pendentes'=>0];
if (!empty($contas)) {
    $in = implode(',', array_map('intval', array_keys($contas)));
    $sql_sum = "
        SELECT
            COUNT(*) AS total_mensalidades,
            SUM(CASE WHEN status = 'pendente' THEN valor ELSE 0 END) AS total_vencido,
            SUM(CASE WHEN status = 'pago' THEN COALESCE(valor_pago,0) ELSE 0 END) AS total_pago,
            SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) AS pendentes
        FROM mensalidades
        WHERE conta_id IN ($in)
    ";
    $res_sum = $conn->query($sql_sum);
    if ($res_sum) {
        $r = $res_sum->fetch_assoc();
        if ($r) {
            $summary['total_mensalidades'] = intval($r['total_mensalidades']);
            $summary['total_vencido'] = floatval($r['total_vencido']);
            $summary['total_pago'] = floatval($r['total_pago']);
            $summary['pendentes'] = intval($r['pendentes']);
        }
    }
}

// mostrar detalhes de juros aplicados na sessão (se existir)
$juros_details = $_SESSION['juros_details'] ?? null;
unset($_SESSION['juros_details']);
$flash_success = $_SESSION['msg_sucesso'] ?? '';
$flash_error = $_SESSION['msg_erro'] ?? '';
unset($_SESSION['msg_sucesso'], $_SESSION['msg_erro']);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>Informações do Aluno - <?= htmlspecialchars($aluno['nome'] ?? '') ?></title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="app-style.css">
    <style>
        /* Local tweaks */
        .inline-form input[type="text"], .inline-form input[type="date"], .inline-form input[type="number"]{ padding:6px 8px; border-radius:6px; border:1px solid #d1d5db; }
        .badge { padding:.25rem .6rem; border-radius:999px; font-weight:700; }
        .muted { color:#6b7280; font-size:.95rem; }
        .actions a, .actions button { padding:8px 10px; border-radius:6px; text-decoration:none; color:#fff; display:inline-block; }
        .btn-apply { background:#7c3aed; }
        .btn-danger { background:#ef4444; }
        .summary { display:flex; gap:12px; margin-bottom:12px; flex-wrap:wrap; }
        .summary .item { background:var(--panel, #f4f6f8); padding:10px 12px; border-radius:8px; min-width:160px; }
        table th, table td { white-space:nowrap; }
        .checkbox-col { width:42px; text-align:center; }
    </style>
</head>
<body>
<div class="wrap" style="max-width:1100px;margin:20px auto;padding:18px;background:#fff;border-radius:12px;box-shadow:0 8px 30px rgba(2,6,23,0.06);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <div>
            <h1 style="margin:0 0 6px 0"><?= htmlspecialchars($aluno['nome'] ?? '') ?></h1>
            <div class="muted">Matrícula: <?= htmlspecialchars($aluno['matricula'] ?? '-') ?> — ID: <?= intval($aluno['id']) ?></div>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="cobrancas.php" class="actions" style="background:#6b7280;padding:8px 10px;border-radius:8px;color:#fff;text-decoration:none">Voltar</a>
            <a href="comunicacao.php?chat=aluno-<?= $aluno_id ?>" class="actions" style="background:#2563eb;padding:8px 10px;border-radius:8px;color:#fff;text-decoration:none">Abrir chat</a>
            <a href="cadastro_aluno.php?id=<?= $aluno_id ?>" class="actions" style="background:#10b981;padding:8px 10px;border-radius:8px;color:#fff;text-decoration:none">Editar</a>
        </div>
    </div>

    <?php if ($flash_success): ?><div class="msg-box msg-success"><?= htmlspecialchars($flash_success) ?></div><?php endif; ?>
    <?php if ($flash_error): ?><div class="msg-box msg-error"><?= htmlspecialchars($flash_error) ?></div><?php endif; ?>

    <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:14px;">
        <div style="flex:0 0 320px">
            <div class="card">
                <h3 style="margin-top:0">Dados</h3>
                <div class="muted">Nome: <?= htmlspecialchars($aluno['nome'] ?? '-') ?></div>
                <div class="muted">Matrícula: <?= htmlspecialchars($aluno['matricula'] ?? '-') ?></div>
                <div class="muted">E-mail: <?= htmlspecialchars($aluno['email'] ?? '-') ?></div>
                <?php if ($has_telefone): ?><div class="muted">Telefone: <?= htmlspecialchars($aluno['telefone'] ?? '-') ?></div><?php endif; ?>
                <div class="muted">Turma: <?= htmlspecialchars($aluno['turma_nome'] ?? '-') ?></div>
                <div class="muted">Professor: <?= htmlspecialchars($aluno['professor_nome'] ?? '-') ?></div>
                <div style="margin-top:10px;">
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="acao" value="toggle_aluno">
                        <input type="hidden" name="aluno_id" value="<?= $aluno_id ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <button type="submit" class="btn btn-ghost"><?= ($has_aluno_ativo ? (!empty($aluno['ativo']) ? 'Inativar Aluno' : 'Ativar Aluno') : 'Alternar Ativo (contas)') ?></button>
                    </form>
                </div>
            </div>

            <div class="card">
                <h4 style="margin-top:0">Resumo Financeiro</h4>
                <div class="muted">Total mensalidades: <?= intval($summary['total_mensalidades']) ?></div>
                <div class="muted">Total vencido: <?= format_currency($summary['total_vencido']) ?></div>
                <div class="muted">Total pago: <?= format_currency($summary['total_pago']) ?></div>
                <div class="muted">Pendentes: <?= intval($summary['pendentes']) ?></div>
            </div>
        </div>

        <div style="flex:1;min-width:320px">
            <div class="card" style="margin-bottom:12px;">
                <h4 style="margin-top:0">Mensalidades</h4>

                <?php if (!empty($mensalidades)): ?>
                    <form method="post" id="jurosForm">
                        <input type="hidden" name="acao" value="aplicar_juros">
                        <input type="hidden" name="aluno_id" value="<?= $aluno_id ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                        <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;flex-wrap:wrap;">
                            <label style="margin:0;font-weight:700">Juros % ao mês:</label>
                            <input type="text" name="juros_percent" placeholder="Ex: 2.5" style="width:120px;padding:6px;border-radius:6px;border:1px solid #d1d5db">
                            <label style="margin:0;">Aplicar a:</label>
                            <select name="aplicar" id="aplicarSelect" style="padding:6px;border-radius:6px;border:1px solid #d1d5db">
                                <option value="todos">Todos pendentes vencidos</option>
                                <option value="selecionados">Selecionados</option>
                            </select>
                            <label style="margin:0;"><input type="checkbox" name="persist" id="persistChk"> Salvar valores</label>
                            <button type="submit" class="btn btn-apply">Aplicar juros</button>
                        </div>

                        <div style="overflow:auto;max-height:360px;">
                            <table>
                                <thead>
                                    <tr>
                                        <th class="checkbox-col"><input type="checkbox" id="checkAll"></th>
                                        <th>Ref</th>
                                        <th>Vencimento</th>
                                        <th>Valor</th>
                                        <th>Juros estimado</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($mensalidades as $m):
                                        $ref = sprintf('%02d/%04d', intval($m['mes_referencia']), intval($m['ano_referencia']));
                                        $dias = days_overdue($m['data_vencimento']);
                                        $juros_est = 0; // will be calculated by JS or on server
                                        ?>
                                        <tr>
                                            <td class="checkbox-col"><input type="checkbox" name="mens_ids[]" value="<?= intval($m['id']) ?>" class="mens-chk" <?= ($m['status'] === 'pendente' && $dias>0) ? '' : 'disabled' ?>></td>
                                            <td><?= $ref ?></td>
                                            <td><?= formatar_data_br($m['data_vencimento']) ?><?php if ($dias>0) echo "<br><small class='muted'>{$dias} dias atraso</small>"; ?></td>
                                            <td><?= format_currency($m['valor']) ?></td>
                                            <td class="juros-cell" data-valor="<?= $m['valor'] ?>" data-dias="<?= $dias ?>">-</td>
                                            <td>
                                                <?php if ($m['status'] === 'pago'): ?><span class="badge" style="background:#10b981;color:#fff">Pago</span>
                                                <?php elseif ($m['status'] === 'pendente' && $dias>0): ?><span class="badge" style="background:#f97316;color:#fff">Vencido</span>
                                                <?php else: ?><span class="badge" style="background:#6b7280;color:#fff"><?= htmlspecialchars($m['status']) ?></span><?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                                    <!-- negociar -->
                                                    <form method="post" class="inline-form" style="margin:0;">
                                                        <input type="hidden" name="acao" value="negociar_valor">
                                                        <input type="hidden" name="aluno_id" value="<?= $aluno_id ?>">
                                                        <input type="hidden" name="mensalidade_id" value="<?= intval($m['id']) ?>">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                        <input type="text" name="novo_valor" placeholder="Novo valor" style="width:110px">
                                                        <input type="submit" value="Negociar" style="padding:6px 8px;border-radius:6px;background:#f59e0b;color:#08121a;border:0;cursor:pointer;">
                                                    </form>
                                                    <!-- registrar pagamento -->
                                                    <?php if ($m['status'] !== 'pago'): ?>
                                                        <form method="post" class="inline-form" style="margin:0;">
                                                            <input type="hidden" name="acao" value="registrar_pagamento">
                                                            <input type="hidden" name="aluno_id" value="<?= $aluno_id ?>">
                                                            <input type="hidden" name="mensalidade_id" value="<?= intval($m['id']) ?>">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                            <input type="text" name="valor_pago" placeholder="Valor pago" style="width:90px">
                                                            <input type="date" name="data_pagamento" value="<?= date('Y-m-d') ?>">
                                                            <input type="submit" value="Dar baixa" style="padding:6px 8px;border-radius:6px;background:#10b981;color:#fff;border:0;cursor:pointer;">
                                                        </form>
                                                    <?php else: ?><span class="muted">—</span><?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>

                    <?php if (!empty($juros_details)): ?>
                        <div class="card" style="margin-top:12px;">
                            <h4>Resumo Juros aplicados</h4>
                            <ul>
                                <?php foreach ($juros_details as $d): ?>
                                    <li>Mensalidade #<?= intval($d['id']) ?> — original <?= format_currency($d['orig']) ?> — juros <?= format_currency($d['juros']) ?> — novo <?= format_currency($d['novo']) ?> (<?= intval($d['dias']) ?> dias)</li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="muted">Nenhuma mensalidade encontrada.</div>
                <?php endif; ?>
            </div>

            <div class="card">
                <h4 style="margin-top:0">Mensagens (histórico)</h4>
                <?php if (!empty($mensagens)): ?>
                    <div style="max-height:420px;overflow:auto;">
                        <?php foreach ($mensagens as $msg):
                            $is_me = ($msg['remetente_tipo'] === 'aluno' && intval($msg['remetente_id']) === $aluno_id);
                            $from = htmlspecialchars($msg['remetente_nome'] ?? ($msg['remetente_tipo'] . '-' . $msg['remetente_id']));
                            $time = formatar_data_hora_br($msg['created_at']);
                            ?>
                            <div style="margin-bottom:10px;border-radius:8px;padding:10px; background:<?= $is_me ? '#2563eb' : '#f1f5f9' ?>; color:<?= $is_me ? '#fff' : '#111' ?>;">
                                <div style="font-size:.9rem;color:<?= $is_me ? 'rgba(255,255,255,0.85)' : '#6b7280' ?>;"><strong><?= $from ?></strong> — <?= $time ?></div>
                                <div style="margin-top:6px; white-space:pre-wrap;"><?= nl2br(htmlspecialchars($msg['mensagem'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="muted">Nenhuma mensagem encontrada.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// JS: calcula juros estimado nas células e controla seleção
(function(){
    function parseFloatSafe(v) {
        v = v.toString().replace(/\./g,'').replace(',','.');
        var n = parseFloat(v);
        return isNaN(n) ? 0 : n;
    }

    function calcJuros(valor, dias, percent){
        if (!percent || percent <= 0 || dias <= 0) return 0;
        // juros proporcional por dias: (percent/100) * (dias/30) * valor
        return Math.round((valor * (percent/100) * (dias/30)) * 100) / 100;
    }

    const jurosInput = document.querySelector('input[name="juros_percent"]');
    const jurosCells = document.querySelectorAll('.juros-cell');
    const checkAll = document.getElementById('checkAll');
    const mensChk = document.querySelectorAll('.mens-chk');

    function updateJurosPreview(){
        const p = parseFloatSafe(jurosInput.value);
        jurosCells.forEach(function(td){
            const v = parseFloat(td.dataset.valor || 0);
            const d = parseInt(td.dataset.dias || 0);
            const j = calcJuros(v, d, p);
            td.textContent = j > 0 ? j.toLocaleString('pt-BR',{minimumFractionDigits:2}) : '-';
        });
    }

    if (jurosInput) {
        jurosInput.addEventListener('input', updateJurosPreview);
        updateJurosPreview();
    }

    if (checkAll) {
        checkAll.addEventListener('change', function(){
            mensChk.forEach(function(ch){
                if (!ch.disabled) ch.checked = checkAll.checked;
            });
        });
    }
})();
</script>
</body>
</html>