<?php
session_start();
include "conexao.php";

// Verificar se é admin
if (!isset($_SESSION['tipo']) || $_SESSION['tipo'] !== "admin") {
    header("Location: index.php");
    exit;
}

$acao = $_GET['acao'] ?? 'dashboard';
$msg_sucesso = '';
$msg_erro = '';

// Processar ações POST
if ($_POST) {
    switch ($_POST['acao']) {
        case 'criar_plano':
            $dados = limpar_entrada($_POST);
            $stmt = $conn->prepare("INSERT INTO planos_pagamento (nome, valor_mensalidade, valor_matricula) VALUES (?, ?, ?)");
            $stmt->bind_param("sdd", $dados['nome'], $dados['valor_mensalidade'], $dados['valor_matricula']);
            if ($stmt->execute()) {
                $msg_sucesso = "Plano criado com sucesso!";
            } else {
                $msg_erro = "Erro ao criar plano.";
            }
            break;
            
        case 'gerar_mensalidades':
            $mes = intval($_POST['mes']);
            $ano = intval($_POST['ano']);
            
            try {
                $conn->begin_transaction();
                
                // Buscar alunos com contas ativas
                $alunos = $conn->query("
                    SELECT ca.id as conta_id, ca.aluno_id, pp.valor_mensalidade, a.nome
                    FROM contas_aluno ca
                    JOIN planos_pagamento pp ON ca.plano_id = pp.id
                    JOIN alunos a ON ca.aluno_id = a.id
                    WHERE ca.ativo = 1
                ");
                
                $geradas = 0;
                while ($aluno = $alunos->fetch_assoc()) {
                    // Verificar se já existe mensalidade para este mês
                    $stmt = $conn->prepare("SELECT id FROM mensalidades WHERE conta_id = ? AND mes_referencia = ? AND ano_referencia = ?");
                    $stmt->bind_param("iii", $aluno['conta_id'], $mes, $ano);
                    $stmt->execute();
                    
                    if ($stmt->get_result()->num_rows === 0) {
                        // Criar mensalidade
                        $data_vencimento = "$ano-$mes-10"; // Vencimento dia 10
                        $stmt = $conn->prepare("INSERT INTO mensalidades (conta_id, mes_referencia, ano_referencia, valor, data_vencimento) VALUES (?, ?, ?, ?, ?)");
                        $stmt->bind_param("iiiis", $aluno['conta_id'], $mes, $ano, $aluno['valor_mensalidade'], $data_vencimento);
                        $stmt->execute();
                        $geradas++;
                    }
                }
                
                $conn->commit();
                $msg_sucesso = "$geradas mensalidades geradas com sucesso!";
                
            } catch (Exception $e) {
                $conn->rollback();
                $msg_erro = "Erro ao gerar mensalidades: " . $e->getMessage();
            }
            break;
            
        case 'registrar_pagamento':
            $mensalidade_id = intval($_POST['mensalidade_id']);
            $valor_pago = floatval($_POST['valor_pago']);
            $data_pagamento = $_POST['data_pagamento'];
            
            $stmt = $conn->prepare("UPDATE mensalidades SET data_pagamento = ?, valor_pago = ?, status = 'pago' WHERE id = ?");
            $stmt->bind_param("sdi", $data_pagamento, $valor_pago, $mensalidade_id);
            
            if ($stmt->execute()) {
                $msg_sucesso = "Pagamento registrado com sucesso!";
            } else {
                $msg_erro = "Erro ao registrar pagamento.";
            }
            break;
    }
}

// Buscar dados conforme ação
$dados = null;

switch ($acao) {
    case 'dashboard':
        // Estatísticas financeiras
        $stats = [];
        
        // Total a receber este mês
        $result = $conn->query("
            SELECT SUM(valor) as total 
            FROM mensalidades 
            WHERE mes_referencia = MONTH(CURDATE()) 
            AND ano_referencia = YEAR(CURDATE()) 
            AND status = 'pendente'
        ");
        $stats['a_receber'] = $result->fetch_assoc()['total'] ?: 0;
        
        // Total recebido este mês
        $result = $conn->query("
            SELECT SUM(valor_pago) as total 
            FROM mensalidades 
            WHERE mes_referencia = MONTH(CURDATE()) 
            AND ano_referencia = YEAR(CURDATE()) 
            AND status = 'pago'
        ");
        $stats['recebido'] = $result->fetch_assoc()['total'] ?: 0;
        
        // Mensalidades vencidas
        $result = $conn->query("
            SELECT COUNT(*) as total 
            FROM mensalidades 
            WHERE data_vencimento < CURDATE() 
            AND status = 'pendente'
        ");
        $stats['vencidas'] = $result->fetch_assoc()['total'] ?: 0;
        
        // Total de alunos ativos
        $result = $conn->query("SELECT COUNT(*) as total FROM contas_aluno WHERE ativo = 1");
        $stats['alunos_ativos'] = $result->fetch_assoc()['total'] ?: 0;
        
        $dados = $stats;
        break;
        
    case 'mensalidades':
        // Listar mensalidades com filtros
        $mes = $_GET['mes'] ?? date('n');
        $ano = $_GET['ano'] ?? date('Y');
        $status = $_GET['status'] ?? '';
        
        $where_status = $status ? "AND m.status = '$status'" : "";
        
        $dados = $conn->query("
            SELECT m.*, a.nome as aluno, a.matricula, pp.nome as plano
            FROM mensalidades m
            JOIN contas_aluno ca ON m.conta_id = ca.id
            JOIN alunos a ON ca.aluno_id = a.id
            JOIN planos_pagamento pp ON ca.plano_id = pp.id
            WHERE m.mes_referencia = $mes AND m.ano_referencia = $ano $where_status
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
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Módulo Financeiro</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .financial-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .financial-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .financial-card.success {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .financial-card.warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .financial-card.danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
        }
        
        .financial-number {
            font-size: 2em;
            font-weight: bold;
            display: block;
        }
        
        .financial-label {
            font-size: 0.9em;
            opacity: 0.9;
            margin-top: 5px;
        }
        
        .status-pago {
            background: #d4edda;
            color: #155724;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
        }
        
        .status-pendente {
            background: #fff3cd;
            color: #856404;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
        }
        
        .status-vencido {
            background: #f8d7da;
            color: #721c24;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
        }
        
        .quick-actions {
            display: flex;
            gap: 10px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border-radius: 10px;
            width: 80%;
            max-width: 500px;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: black;
        }
    </style>
    <script>
        function abrirModal(id) {
            document.getElementById(id).style.display = 'block';
        }
        
        function fecharModal(id) {
            document.getElementById(id).style.display = 'none';
        }
        
        function registrarPagamento(mensalidadeId, valor) {
            document.getElementById('mensalidade_id').value = mensalidadeId;
            document.getElementById('valor_original').value = valor;
            document.getElementById('valor_pago').value = valor;
            abrirModal('modalPagamento');
        }
    </script>
</head>
<body>
<div class="container">
    <h1>Módulo Financeiro</h1>
    
    <?php if ($msg_sucesso): ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg_sucesso) ?></div>
    <?php endif; ?>
    
    <?php if ($msg_erro): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($msg_erro) ?></div>
    <?php endif; ?>
    
    <!-- Navegação -->
    <div class="nav-tabs">
        <a href="?acao=dashboard" class="nav-tab <?= $acao === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
        <a href="?acao=mensalidades" class="nav-tab <?= $acao === 'mensalidades' ? 'active' : '' ?>">Mensalidades</a>
        <a href="?acao=planos" class="nav-tab <?= $acao === 'planos' ? 'active' : '' ?>">Planos</a>
        <a href="?acao=contas" class="nav-tab <?= $acao === 'contas' ? 'active' : '' ?>">Contas de Alunos</a>
    </div>
    
    <?php if ($acao === 'dashboard'): ?>
        <!-- Dashboard Financeiro -->
        <div class="financial-grid">
            <div class="financial-card">
                <span class="financial-number">R$ <?= number_format($dados['a_receber'], 2, ',', '.') ?></span>
                <span class="financial-label">A Receber (Este Mês)</span>
            </div>
            <div class="financial-card success">
                <span class="financial-number">R$ <?= number_format($dados['recebido'], 2, ',', '.') ?></span>
                <span class="financial-label">Recebido (Este Mês)</span>
            </div>
            <div class="financial-card danger">
                <span class="financial-number"><?= $dados['vencidas'] ?></span>
                <span class="financial-label">Mensalidades Vencidas</span>
            </div>
            <div class="financial-card warning">
                <span class="financial-number"><?= $dados['alunos_ativos'] ?></span>
                <span class="financial-label">Alunos Ativos</span>
            </div>
        </div>
        
        <div class="quick-actions">
            <button onclick="abrirModal('modalGerar')">Gerar Mensalidades</button>
            <a href="?acao=mensalidades"><button class="btn-info">Ver Mensalidades</button></a>
            <button onclick="abrirModal('modalPlano')">Novo Plano</button>
        </div>
        
    <?php elseif ($acao === 'mensalidades'): ?>
        <!-- Mensalidades -->
        <h2>Mensalidades</h2>
        
        <!-- Filtros -->
        <form method="get" class="form-row">
            <input type="hidden" name="acao" value="mensalidades">
            <div>
                <label>Mês</label>
                <select name="mes">
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>" <?= ($i == ($_GET['mes'] ?? date('n'))) ? 'selected' : '' ?>>
                            <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label>Ano</label>
                <select name="ano">
                    <?php for ($i = date('Y') - 2; $i <= date('Y') + 1; $i++): ?>
                        <option value="<?= $i ?>" <?= ($i == ($_GET['ano'] ?? date('Y'))) ? 'selected' : '' ?>><?= $i ?></option>
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
                <button type="submit">Filtrar</button>
            </div>
        </form>
        
        <?php if ($dados && $dados->num_rows > 0): ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Aluno</th>
                            <th>Matrícula</th>
                            <th>Plano</th>
                            <th>Valor</th>
                            <th>Vencimento</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($mensalidade = $dados->fetch_assoc()): ?>
                            <?php 
                            $status_class = $mensalidade['status'];
                            if ($mensalidade['status'] === 'pendente' && $mensalidade['data_vencimento'] < date('Y-m-d')) {
                                $status_class = 'vencido';
                            }
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($mensalidade['aluno']) ?></td>
                                <td><?= htmlspecialchars($mensalidade['matricula']) ?></td>
                                <td><?= htmlspecialchars($mensalidade['plano']) ?></td>
                                <td>R$ <?= number_format($mensalidade['valor'], 2, ',', '.') ?></td>
                                <td><?= formatar_data_br($mensalidade['data_vencimento']) ?></td>
                                <td><span class="status-<?= $status_class ?>"><?= ucfirst($status_class) ?></span></td>
                                <td>
                                    <?php if ($mensalidade['status'] === 'pendente'): ?>
                                        <button onclick="registrarPagamento(<?= $mensalidade['id'] ?>, <?= $mensalidade['valor'] ?>)" class="btn-success">
                                            Registrar Pagamento
                                        </button>
                                    <?php else: ?>
                                        Pago em <?= formatar_data_br($mensalidade['data_pagamento']) ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>Nenhuma mensalidade encontrada para os filtros selecionados.</p>
        <?php endif; ?>
        
    <?php elseif ($acao === 'planos'): ?>
        <!-- Planos de Pagamento -->
        <h2>Planos de Pagamento</h2>
        
        <button onclick="abrirModal('modalPlano')" class="btn-primary">Novo Plano</button>
        
        <?php if ($dados && $dados->num_rows > 0): ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Mensalidade</th>
                            <th>Taxa de Matrícula</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($plano = $dados->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($plano['nome']) ?></td>
                                <td>R$ <?= number_format($plano['valor_mensalidade'], 2, ',', '.') ?></td>
                                <td>R$ <?= number_format($plano['valor_matricula'], 2, ',', '.') ?></td>
                                <td>
                                    <span class="status-<?= $plano['ativo'] ? 'pago' : 'pendente' ?>">
                                        <?= $plano['ativo'] ? 'Ativo' : 'Inativo' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>Nenhum plano cadastrado.</p>
        <?php endif; ?>
        
    <?php elseif ($acao === 'contas'): ?>
        <!-- Contas de Alunos -->
        <h2>Contas de Alunos</h2>
        
        <?php if ($dados && $dados->num_rows > 0): ?>
            <div class="table-responsive">
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
                                <td>R$ <?= number_format($conta['valor_mensalidade'], 2, ',', '.') ?></td>
                                <td><?= formatar_data_br($conta['data_inicio']) ?></td>
                                <td>
                                    <span class="status-<?= $conta['ativo'] ? 'pago' : 'pendente' ?>">
                                        <?= $conta['ativo'] ? 'Ativa' : 'Inativa' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>Nenhuma conta encontrada.</p>
        <?php endif; ?>
    <?php endif; ?>
    
    <!-- Modal Gerar Mensalidades -->
    <div id="modalGerar" class="modal">
        <div class="modal-content">
            <span class="close" onclick="fecharModal('modalGerar')">&times;</span>
            <h3>Gerar Mensalidades</h3>
            <form method="post">
                <input type="hidden" name="acao" value="gerar_mensalidades">
                <div>
                    <label>Mês</label>
                    <select name="mes" required>
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?= $i ?>" <?= $i == date('n') ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label>Ano</label>
                    <select name="ano" required>
                        <?php for ($i = date('Y'); $i <= date('Y') + 1; $i++): ?>
                            <option value="<?= $i ?>"><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <button type="submit">Gerar Mensalidades</button>
            </form>
        </div>
    </div>
    
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
                <button type="submit">Criar Plano</button>
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
                <button type="submit">Registrar Pagamento</button>
            </form>
        </div>
    </div>
    
    <div style="margin-top: 30px;">
        <a href="dashboard_admin.php"><button>Voltar ao Dashboard</button></a>
    </div>
</div>
</body>
</html>