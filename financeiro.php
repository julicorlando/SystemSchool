<?php
// ATIVANDO EXIBIÇÃO DE ERROS PARA DEBUG
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include "conexao.php";
include_once "funcoes.php"; // formatar_data_br deve estar aqui

// Verificar se é admin
if (!isset($_SESSION['tipo']) || $_SESSION['tipo'] !== "admin") {
    header("Location: index.php");
    exit;
}

// Parâmetros e mensagens
$acao = $_GET['acao'] ?? 'dashboard';
$turma_id = isset($_GET['turma_id']) ? intval($_GET['turma_id']) : 0;
$msg_sucesso = '';
$msg_erro = '';

// Buscar turmas para filtros
$res_turmas = $conn->query("SELECT id, nome FROM turmas ORDER BY nome");

// Processar ações POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    $acao_post = $_POST['acao'];

    if ($acao_post === 'criar_plano') {
        $nome = trim($_POST['nome'] ?? '');
        $valor_mensalidade = floatval($_POST['valor_mensalidade'] ?? 0);
        $valor_matricula = floatval($_POST['valor_matricula'] ?? 0);

        if ($nome === '') {
            $msg_erro = "Nome do plano é obrigatório.";
        } else {
            $stmt = $conn->prepare("INSERT INTO planos_pagamento (nome, valor_mensalidade, valor_matricula) VALUES (?, ?, ?)");
            $stmt->bind_param("sdd", $nome, $valor_mensalidade, $valor_matricula);
            if ($stmt->execute()) {
                $msg_sucesso = "Plano criado com sucesso!";
            } else {
                $msg_erro = "Erro ao criar plano.";
            }
            $stmt->close();
        }
    }

    if ($acao_post === 'gerar_mensalidades') {
        $mes = intval($_POST['mes']);
        $ano = intval($_POST['ano']);
        $turma = intval($_POST['turma_id']);
        $plano_id = intval($_POST['plano_id']);

        if (!$turma || !$plano_id) {
            $msg_erro = "Turma e plano são obrigatórios.";
        } else {
            try {
                $conn->begin_transaction();

                $alunos = $conn->query("SELECT id FROM alunos WHERE turma_id = $turma");
                $planoDados = $conn->query("SELECT valor_mensalidade FROM planos_pagamento WHERE id = $plano_id")->fetch_assoc();
                $valor_mensalidade = $planoDados['valor_mensalidade'] ?? 0;

                $geradas = 0;
                while ($aluno = $alunos->fetch_assoc()) {
                    $aluno_id = intval($aluno['id']);
                    $conta = $conn->query("SELECT id FROM contas_aluno WHERE aluno_id = $aluno_id AND plano_id = $plano_id AND ativo = 1")->fetch_assoc();
                    if (!$conta) {
                        $stmt = $conn->prepare("INSERT INTO contas_aluno (aluno_id, plano_id, ativo, data_inicio) VALUES (?, ?, 1, CURDATE())");
                        $stmt->bind_param("ii", $aluno_id, $plano_id);
                        $stmt->execute();
                        $conta_id = $conn->insert_id;
                        $stmt->close();
                    } else {
                        $conta_id = $conta['id'];
                    }

                    $stmt = $conn->prepare("SELECT id FROM mensalidades WHERE conta_id = ? AND mes_referencia = ? AND ano_referencia = ?");
                    $stmt->bind_param("iii", $conta_id, $mes, $ano);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($res->num_rows === 0) {
                        $data_vencimento = sprintf("%04d-%02d-10", $ano, $mes);
                        $stmt_insert = $conn->prepare("INSERT INTO mensalidades (conta_id, mes_referencia, ano_referencia, valor, data_vencimento) VALUES (?, ?, ?, ?, ?)");
                        $stmt_insert->bind_param("iiiis", $conta_id, $mes, $ano, $valor_mensalidade, $data_vencimento);
                        $stmt_insert->execute();
                        $stmt_insert->close();
                        $geradas++;
                    }
                    $stmt->close();
                }

                $conn->commit();
                $msg_sucesso = "$geradas mensalidades geradas com sucesso!";
            } catch (Exception $e) {
                $conn->rollback();
                $msg_erro = "Erro ao gerar mensalidades: " . $e->getMessage();
            }
        }
    }

    if ($acao_post === 'gerar_mensalidade_aluno') {
        $aluno_id = intval($_POST['aluno_id']);
        $mes = intval($_POST['mes']);
        $ano = intval($_POST['ano']);
        $plano_id = intval($_POST['plano_id']);

        if (!$aluno_id || !$plano_id) {
            $msg_erro = "Aluno e plano são obrigatórios.";
        } else {
            try {
                $conn->begin_transaction();

                $planoDados = $conn->query("SELECT valor_mensalidade FROM planos_pagamento WHERE id = $plano_id")->fetch_assoc();
                $valor_mensalidade = $planoDados['valor_mensalidade'] ?? 0;

                $conta = $conn->query("SELECT id FROM contas_aluno WHERE aluno_id = $aluno_id AND plano_id = $plano_id AND ativo = 1")->fetch_assoc();
                if (!$conta) {
                    $stmt = $conn->prepare("INSERT INTO contas_aluno (aluno_id, plano_id, ativo, data_inicio) VALUES (?, ?, 1, CURDATE())");
                    $stmt->bind_param("ii", $aluno_id, $plano_id);
                    $stmt->execute();
                    $conta_id = $conn->insert_id;
                    $stmt->close();
                } else {
                    $conta_id = $conta['id'];
                }

                $stmt = $conn->prepare("SELECT id FROM mensalidades WHERE conta_id = ? AND mes_referencia = ? AND ano_referencia = ?");
                $stmt->bind_param("iii", $conta_id, $mes, $ano);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res->num_rows === 0) {
                    $data_vencimento = sprintf("%04d-%02d-10", $ano, $mes);
                    $stmt_insert = $conn->prepare("INSERT INTO mensalidades (conta_id, mes_referencia, ano_referencia, valor, data_vencimento) VALUES (?, ?, ?, ?, ?)");
                    $stmt_insert->bind_param("iiiis", $conta_id, $mes, $ano, $valor_mensalidade, $data_vencimento);
                    $stmt_insert->execute();
                    $stmt_insert->close();
                    $msg_sucesso = "Mensalidade gerada com sucesso para o aluno!";
                } else {
                    $msg_erro = "Já existe mensalidade para este mês/ano para este aluno.";
                }
                $stmt->close();

                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
                $msg_erro = "Erro ao gerar mensalidade para o aluno: " . $e->getMessage();
            }
        }
    }

    if ($acao_post === 'registrar_pagamento') {
        $mensalidade_id = intval($_POST['mensalidade_id']);
        $valor_pago = floatval($_POST['valor_pago']);
        $data_pagamento = $_POST['data_pagamento'] ?? date('Y-m-d');

        if (!$mensalidade_id || $valor_pago <= 0) {
            $msg_erro = "Mensalidade e valor são obrigatórios.";
        } else {
            $stmt = $conn->prepare("UPDATE mensalidades SET data_pagamento = ?, valor_pago = ?, status = 'pago' WHERE id = ?");
            $stmt->bind_param("sdi", $data_pagamento, $valor_pago, $mensalidade_id);
            if ($stmt->execute()) {
                $msg_sucesso = "Pagamento registrado com sucesso!";
            } else {
                $msg_erro = "Erro ao registrar pagamento.";
            }
            $stmt->close();
        }
    }
}

// Buscar dados conforme ação
$dados = null;

if ($acao === 'dashboard') {
    $mes = date('n');
    $ano = date('Y');
    $stats = [];

    $stats['a_receber'] = $conn->query("SELECT SUM(valor) as total FROM mensalidades WHERE mes_referencia = $mes AND ano_referencia = $ano AND status = 'pendente'")->fetch_assoc()['total'] ?: 0;
    $stats['recebido'] = $conn->query("SELECT SUM(valor_pago) as total FROM mensalidades WHERE mes_referencia = $mes AND ano_referencia = $ano AND status = 'pago'")->fetch_assoc()['total'] ?: 0;
    $total_mes = $conn->query("SELECT COUNT(*) as total FROM mensalidades WHERE mes_referencia = $mes AND ano_referencia = $ano")->fetch_assoc()['total'] ?: 0;
    $total_pagas = $conn->query("SELECT COUNT(*) as total FROM mensalidades WHERE mes_referencia = $mes AND ano_referencia = $ano AND status = 'pago'")->fetch_assoc()['total'] ?: 0;
    $stats['vencidas'] = $conn->query("SELECT COUNT(*) as total FROM mensalidades WHERE data_vencimento < CURDATE() AND status = 'pendente'")->fetch_assoc()['total'] ?: 0;
    $stats['alunos_ativos'] = $conn->query("SELECT COUNT(*) as total FROM contas_aluno WHERE ativo = 1")->fetch_assoc()['total'] ?: 0;
    $stats['eficiencia'] = $total_mes > 0 ? round(($total_pagas / $total_mes) * 100, 1) : 0;
    $total_vencidas_mes = $conn->query("SELECT COUNT(*) as total FROM mensalidades WHERE mes_referencia = $mes AND ano_referencia = $ano AND data_vencimento < CURDATE() AND status = 'pendente'")->fetch_assoc()['total'] ?: 0;
    $stats['inadimplencia'] = $total_mes > 0 ? round(($total_vencidas_mes / $total_mes) * 100, 1) : 0;

    $resAtraso = $conn->query("SELECT a.id, m.data_vencimento, m.valor FROM mensalidades m JOIN contas_aluno ca ON m.conta_id = ca.id JOIN alunos a ON ca.aluno_id = a.id WHERE m.status = 'pendente' AND m.data_vencimento < CURDATE()");
    $alunos_atrasados = [];
    $dias_soma = 0;
    $valor_vencido = 0;
    $cont_vencidas = 0;
    while ($row = $resAtraso->fetch_assoc()) {
        $alunos_atrasados[$row['id']] = true;
        $dias = (strtotime(date('Y-m-d')) - strtotime($row['data_vencimento'])) / (60*60*24);
        $dias_soma += $dias;
        $valor_vencido += $row['valor'];
        $cont_vencidas++;
    }
    $stats['alunos_atrasados'] = count($alunos_atrasados);
    $stats['media_dias_atraso'] = $cont_vencidas > 0 ? round($dias_soma/$cont_vencidas,1) : 0;
    $stats['valor_vencido'] = $valor_vencido;
    $stats['pendente_futuro'] = $conn->query("SELECT SUM(valor) as total FROM mensalidades WHERE data_vencimento >= CURDATE() AND status = 'pendente'")->fetch_assoc()['total'] ?: 0;

    $dados = $stats;
} else {
    switch ($acao) {
        case 'mensalidades':
            $mes = $_GET['mes'] ?? date('n');
            $ano = $_GET['ano'] ?? date('Y');
            $status = $_GET['status'] ?? '';
            $turma_id = $_GET['turma_id'] ?? '';

            $where_status = $status ? "AND m.status = '". $conn->real_escape_string($status) ."'" : "";
            $where_turma = $turma_id ? "AND a.turma_id = ". intval($turma_id) : "";

            $dados = $conn->query("
                SELECT m.*, a.nome as aluno, a.matricula, pp.nome as plano, t.nome as turma
                FROM mensalidades m
                JOIN contas_aluno ca ON m.conta_id = ca.id
                JOIN alunos a ON ca.aluno_id = a.id
                JOIN turmas t ON a.turma_id = t.id
                JOIN planos_pagamento pp ON ca.plano_id = pp.id
                WHERE m.mes_referencia = ". intval($mes) ." AND m.ano_referencia = ". intval($ano) ." $where_status $where_turma
                ORDER BY m.data_vencimento ASC
            ");
            break;

        case 'planos':
            $dados = $conn->query("SELECT * FROM planos_pagamento ORDER BY nome");
            break;

        case 'contas':
            $dados = $conn->query("
                SELECT ca.*, a.nome as aluno, a.matricula, pp.nome as plano, pp.valor_mensalidade
                FROM contas_aluno ca
                JOIN alunos a ON ca.aluno_id = a.id
                JOIN planos_pagamento pp ON ca.plano_id = pp.id
                ORDER BY a.nome
            ");
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Módulo Financeiro</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { font-family: Arial, sans-serif; background:#f6f9fc; color:#222; }
        .container { max-width:1100px; margin:24px auto; padding:20px; background:#fff; border-radius:8px; box-shadow:0 6px 24px rgba(0,0,0,0.08); }
        .dashboard-financeiro-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 24px; margin: 30px 0 18px 0; }
        .fin-card { background: #fff; border-radius: 12px; box-shadow: 0 3px 12px rgba(0,0,0,0.06); padding: 22px; text-align: center; border-left: 7px solid #667eea;}
        .fin-card.success { border-left-color: #22c55e;}
        .fin-card.warning { border-left-color: #f39c12;}
        .fin-card.danger { border-left-color: #ff6b6b;}
        .fin-card.info   { border-left-color: #00f2fe;}
        .fin-card .number { font-size: 2em; font-weight: bold;}
        .fin-card .label  { font-size: 1em; color: #666; margin-top: 7px;}
        .fin-card .subinfo {font-size:0.92em;color:#888;margin-top:3px;}
        .nav-tabs {margin-bottom:18px;}
        .nav-tab {display:inline-block;padding:8px 14px;margin-right:8px;border-radius:8px;background:#eee;color:#333;text-decoration:none;}
        .nav-tab.active {background:#667eea;color:#fff;}
        .table-responsive {overflow-x:auto;}
        table {width:100%; border-collapse:collapse; margin-top:20px;}
        th, td { border:1px solid #e6e6e6; padding:10px; text-align:left; vertical-align:middle;}
        th { background:#fafafa;}
        .btn { border: none; padding:8px 12px; border-radius:6px; cursor:pointer; }
        .btn-primary { background:#667eea; color:#fff; }
        .btn-success { background:#4facfe; color:#fff; }
        .btn-danger { background:#ff6b6b; color:#fff; }
        .modal {display: none;position: fixed;z-index: 1000;left: 0;top: 0;width: 100%;height: 100%;background-color: rgba(0,0,0,0.5);}
        .modal-content {background-color: #fff;margin: 6% auto;padding: 18px;border-radius: 10px;width: 90%;max-width: 720px;}
        .close {color: #999;float: right;font-size: 22px;font-weight: bold;cursor: pointer;}
        .close:hover {color: #333;}
        @media(max-width:800px){ .dashboard-financeiro-grid{grid-template-columns:1fr;} table{font-size:13px;} }
    </style>
    <script>
        function abrirModal(id){ document.getElementById(id).style.display='block'; }
        function fecharModal(id){ document.getElementById(id).style.display='none'; }
        function registrarPagamento(mensalidadeId, valor){
            document.getElementById('mensalidade_id').value = mensalidadeId;
            document.getElementById('valor_original').value = valor;
            document.getElementById('valor_pago').value = valor;
            abrirModal('modalPagamento');
        }
        window.onclick = function(event){
            ['modalGerar','modalGerarAluno','modalPagamento','modalPlano'].forEach(function(id){
                var el=document.getElementById(id);
                if(el && event.target == el) el.style.display='none';
            });
        };
    </script>
</head>
<body>
<div class="container">
    <div style="display:flex;justify-content:space-between;align-items:center;">
        <h1 style="margin:0">Módulo Financeiro</h1>
        <div>
            <a href="dashboard_admin.php" class="btn btn-primary">Voltar ao Dashboard</a>
        </div>
    </div>

    <?php if ($msg_sucesso): ?><div style="margin-top:12px;background:#d4edda;color:#155724;padding:10px;border-radius:6px;"><?= htmlspecialchars($msg_sucesso) ?></div><?php endif; ?>
    <?php if ($msg_erro): ?><div style="margin-top:12px;background:#f8d7da;color:#721c24;padding:10px;border-radius:6px;"><?= htmlspecialchars($msg_erro) ?></div><?php endif; ?>

    <div class="nav-tabs" style="margin-top:18px;">
        <a href="?acao=dashboard" class="nav-tab <?= $acao === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
        <a href="?acao=mensalidades" class="nav-tab <?= $acao === 'mensalidades' ? 'active' : '' ?>">Mensalidades</a>
        <a href="?acao=planos" class="nav-tab <?= $acao === 'planos' ? 'active' : '' ?>">Planos</a>
        <a href="?acao=contas" class="nav-tab <?= $acao === 'contas' ? 'active' : '' ?>">Contas de Alunos</a>
    </div>

    <?php if ($acao === 'dashboard' && $dados): ?>
        <div class="dashboard-financeiro-grid">
            <div class="fin-card info">
                <div class="number"><?= $dados['eficiencia'] ?>%</div>
                <div class="label">Eficiência de Cobrança</div>
                <div class="subinfo">Mensalidades pagas no mês</div>
            </div>

            <div class="fin-card danger">
                <div class="number"><?= $dados['inadimplencia'] ?>%</div>
                <div class="label">Inadimplência do Mês</div>
                <div class="subinfo">Mensalidades vencidas não pagas</div>
            </div>

            <div class="fin-card">
                <div class="number">R$ <?= number_format($dados['a_receber'],2,',','.') ?></div>
                <div class="label">A Receber (Mês)</div>
                <div class="subinfo">Pendentes do mês selecionado</div>
            </div>

            <div class="fin-card success">
                <div class="number">R$ <?= number_format($dados['recebido'],2,',','.') ?></div>
                <div class="label">Recebido (Mês)</div>
                <div class="subinfo">Pagamentos registrados</div>
            </div>

            <div class="fin-card warning">
                <div class="number">R$ <?= number_format($dados['valor_vencido'],2,',','.') ?></div>
                <div class="label">Valor Total Vencido</div>
                <div class="subinfo">Soma de mens. vencidas</div>
            </div>

            <div class="fin-card danger">
                <div class="number"><?= $dados['vencidas'] ?></div>
                <div class="label">Mensalidades Vencidas</div>
                <div class="subinfo">Não pagas</div>
            </div>

            <div class="fin-card info">
                <div class="number"><?= $dados['alunos_ativos'] ?></div>
                <div class="label">Alunos Ativos</div>
                <div class="subinfo">Contas ativas</div>
            </div>

            <div class="fin-card danger">
                <div class="number"><?= $dados['alunos_atrasados'] ?></div>
                <div class="label">Alunos em Atraso</div>
                <div class="subinfo">Média dias atraso: <b><?= $dados['media_dias_atraso'] ?></b></div>
            </div>

            <div class="fin-card">
                <div class="number">R$ <?= number_format($dados['pendente_futuro'],2,',','.') ?></div>
                <div class="label">Pendente Futuro</div>
                <div class="subinfo">Mensalidades a vencer</div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($acao === 'mensalidades'): ?>
        <h2>Mensalidades</h2>

        <form method="get" style="display:flex;gap:12px;flex-wrap:wrap;align-items:end;margin-bottom:12px;">
            <input type="hidden" name="acao" value="mensalidades">
            <div>
                <label>Turma</label>
                <select name="turma_id" onchange="this.form.submit()">
                    <option value="">Todas</option>
                    <?php $res_turmas->data_seek(0); while ($t = $res_turmas->fetch_assoc()): ?>
                        <option value="<?= $t['id'] ?>" <?= ($t['id'] == $turma_id ? 'selected' : '') ?>><?= htmlspecialchars($t['nome']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div>
                <label>Mês</label>
                <select name="mes">
                    <?php for ($i=1;$i<=12;$i++): ?>
                        <option value="<?= $i ?>" <?= ($i == ($_GET['mes'] ?? date('n')) ? 'selected' : '') ?>><?= date('F', mktime(0,0,0,$i,1)) ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div>
                <label>Ano</label>
                <select name="ano">
                    <?php for ($i=date('Y')-2;$i<=date('Y')+1;$i++): ?>
                        <option value="<?= $i ?>" <?= ($i == ($_GET['ano'] ?? date('Y')) ? 'selected' : '') ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div>
                <label>Status</label>
                <select name="status">
                    <option value="">Todos</option>
                    <option value="pendente" <?= ($_GET['status'] ?? '') === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                    <option value="pago" <?= ($_GET['status'] ?? '') === 'pago' ? 'selected' : '' ?>>Pago</option>
                    <option value="vencido" <?= ($_GET['status'] ?? '') === 'vencido' ? 'selected' : '' ?>>Vencido</option>
                </select>
            </div>

            <div>
                <button class="btn btn-primary" type="submit">Filtrar</button>
            </div>
        </form>

        <div style="margin-bottom:12px;">
            <button class="btn btn-primary" onclick="abrirModal('modalGerar')" <?= !$turma_id ? 'disabled' : '' ?>>Atribuir Plano e Gerar Mensalidades da Turma</button>
            <button class="btn btn-primary" onclick="abrirModal('modalGerarAluno')">Atribuir Plano e Gerar Mensalidade de Aluno</button>
        </div>

        <?php if ($dados && $dados->num_rows > 0): ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Aluno</th>
                            <th>Matrícula</th>
                            <th>Turma</th>
                            <th>Plano</th>
                            <th>Valor</th>
                            <th>Vencimento</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($m = $dados->fetch_assoc()): 
                            $status_class = $m['status'];
                            if ($m['status'] === 'pendente' && $m['data_vencimento'] < date('Y-m-d')) $status_class = 'vencido';
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($m['aluno']) ?></td>
                                <td><?= htmlspecialchars($m['matricula']) ?></td>
                                <td><?= htmlspecialchars($m['turma']) ?></td>
                                <td><?= htmlspecialchars($m['plano']) ?></td>
                                <td>R$ <?= number_format($m['valor'],2,',','.') ?></td>
                                <td><?= formatar_data_br($m['data_vencimento']) ?></td>
                                <td><span><?= ucfirst($status_class) ?></span></td>
                                <td>
                                    <?php if ($m['status'] === 'pendente'): ?>
                                        <button class="btn btn-success" onclick="registrarPagamento(<?= $m['id'] ?>, <?= $m['valor'] ?>)">Registrar Pagamento</button>
                                    <?php else: ?>
                                        Pago em <?= formatar_data_br($m['data_pagamento']) ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>Nenhuma mensalidade encontrada com os filtros selecionados.</p>
        <?php endif; ?>

        <!-- Modal Gerar Mensalidades (Turma) -->
        <div id="modalGerar" class="modal">
            <div class="modal-content">
                <span class="close" onclick="fecharModal('modalGerar')">&times;</span>
                <h3>Atribuir Plano e Gerar Mensalidades da Turma</h3>
                <form method="post">
                    <input type="hidden" name="acao" value="gerar_mensalidades">
                    <input type="hidden" name="turma_id" value="<?= intval($turma_id) ?>">
                    <div>
                        <label>Plano</label>
                        <select name="plano_id" required>
                            <?php $planos = $conn->query("SELECT id, nome FROM planos_pagamento ORDER BY nome"); while ($p = $planos->fetch_assoc()): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nome']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label>Mês</label>
                        <select name="mes" required>
                            <?php for ($i=1;$i<=12;$i++): ?>
                                <option value="<?= $i ?>" <?= $i == date('n') ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$i,1)) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div>
                        <label>Ano</label>
                        <select name="ano" required>
                            <?php for ($i=date('Y'); $i<=date('Y')+1; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div style="margin-top:12px;">
                        <button class="btn btn-primary" type="submit" <?= !$turma_id ? 'disabled' : '' ?>>Gerar</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Gerar Mensalidade Aluno -->
        <div id="modalGerarAluno" class="modal">
            <div class="modal-content">
                <span class="close" onclick="fecharModal('modalGerarAluno')">&times;</span>
                <h3>Atribuir Plano e Gerar Mensalidade de Aluno</h3>
                <form method="post">
                    <input type="hidden" name="acao" value="gerar_mensalidade_aluno">
                    <div>
                        <label>Aluno</label>
                        <select name="aluno_id" required>
                            <?php $alunos_q = $conn->query("SELECT id, nome, matricula FROM alunos ORDER BY nome"); while($a = $alunos_q->fetch_assoc()): ?>
                                <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['nome']) ?> (<?= htmlspecialchars($a['matricula']) ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label>Plano</label>
                        <select name="plano_id" required>
                            <?php $planos2 = $conn->query("SELECT id, nome FROM planos_pagamento ORDER BY nome"); while($p = $planos2->fetch_assoc()): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nome']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label>Mês</label>
                        <select name="mes" required>
                            <?php for ($i=1;$i<=12;$i++): ?>
                                <option value="<?= $i ?>" <?= $i==date('n') ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$i,1)) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div>
                        <label>Ano</label>
                        <select name="ano" required>
                            <?php for ($i=date('Y'); $i<=date('Y')+1; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div style="margin-top:12px;">
                        <button class="btn btn-primary" type="submit">Gerar para Aluno</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Registrar Pagamento -->
        <div id="modalPagamento" class="modal">
            <div class="modal-content">
                <span class="close" onclick="fecharModal('modalPagamento')">&times;</span>
                <h3>Registrar Pagamento</h3>
                <form method="post">
                    <input type="hidden" name="acao" value="registrar_pagamento">
                    <input type="hidden" name="mensalidade_id" id="mensalidade_id">
                    <div>
                        <label>Valor Original</label>
                        <input type="number" id="valor_original" step="0.01" readonly>
                    </div>
                    <div>
                        <label>Valor Pago</label>
                        <input type="number" name="valor_pago" id="valor_pago" step="0.01" required>
                    </div>
                    <div>
                        <label>Data do Pagamento</label>
                        <input type="date" name="data_pagamento" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div style="margin-top:12px;">
                        <button class="btn btn-success" type="submit">Registrar Pagamento</button>
                    </div>
                </form>
            </div>
        </div>

    <?php endif; ?>

    <?php if ($acao === 'planos'): ?>
        <h2>Planos de Pagamento</h2>
        <button class="btn btn-primary" onclick="abrirModal('modalPlano')">Novo Plano</button>

        <?php if ($dados && $dados->num_rows > 0): ?>
            <div class="table-responsive" style="margin-top:12px;">
                <table>
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Mensalidade</th>
                            <th>Taxa de Matrícula</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($plano = $dados->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($plano['nome']) ?></td>
                                <td>R$ <?= number_format($plano['valor_mensalidade'],2,',','.') ?></td>
                                <td>R$ <?= number_format($plano['valor_matricula'],2,',','.') ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="margin-top:12px;">Nenhum plano cadastrado.</p>
        <?php endif; ?>

        <!-- Modal Novo Plano -->
        <div id="modalPlano" class="modal">
            <div class="modal-content">
                <span class="close" onclick="fecharModal('modalPlano')">&times;</span>
                <h3>Novo Plano de Pagamento</h3>
                <form method="post">
                    <input type="hidden" name="acao" value="criar_plano">
                    <div>
                        <label>Nome do Plano</label>
                        <input type="text" name="nome" required>
                    </div>
                    <div>
                        <label>Valor da Mensalidade</label>
                        <input type="number" name="valor_mensalidade" step="0.01" required>
                    </div>
                    <div>
                        <label>Taxa de Matrícula</label>
                        <input type="number" name="valor_matricula" step="0.01" value="0">
                    </div>
                    <div style="margin-top:12px;">
                        <button class="btn btn-primary" type="submit">Criar Plano</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($acao === 'contas'): ?>
        <h2>Contas de Alunos</h2>
        <?php if ($dados && $dados->num_rows > 0): ?>
            <div class="table-responsive" style="margin-top:12px;">
                <table>
                    <thead>
                        <tr>
                            <th>Aluno</th>
                            <th>Matrícula</th>
                            <th>Plano</th>
                            <th>Mensalidade</th>
                            <th>Data Início</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($conta = $dados->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($conta['aluno']) ?></td>
                                <td><?= htmlspecialchars($conta['matricula']) ?></td>
                                <td><?= htmlspecialchars($conta['plano']) ?></td>
                                <td>R$ <?= number_format($conta['valor_mensalidade'],2,',','.') ?></td>
                                <td><?= formatar_data_br($conta['data_inicio']) ?></td>
                                <td><?= $conta['ativo'] ? 'Ativa' : 'Inativa' ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="margin-top:12px;">Nenhuma conta encontrada.</p>
        <?php endif; ?>
    <?php endif; ?>

    <div style="margin-top:20px;">
        <a href="dashboard_admin.php" class="btn btn-primary">Voltar ao Dashboard</a>
    </div>
</div>
</body>
</html>