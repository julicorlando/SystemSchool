<?php
session_start();
include "conexao.php";
include_once "funcoes.php";
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SESSION['tipo'] !== "admin") {
    header("Location: index.php");
    exit;
}

// LIBERAR/BLOQUEAR cadastro de alunos por professor
if (isset($_GET['liberar_cadastro_professor'])) {
    $id = intval($_GET['liberar_cadastro_professor']);
    $conn->query("UPDATE turmas SET professor_pode_cadastrar=1 WHERE id=$id");
    header("Location: dashboard_admin.php");
    exit;
}
if (isset($_GET['bloquear_cadastro_professor'])) {
    $id = intval($_GET['bloquear_cadastro_professor']);
    $conn->query("UPDATE turmas SET professor_pode_cadastrar=0 WHERE id=$id");
    header("Location: dashboard_admin.php");
    exit;
}

// Finalizar turma
if(isset($_GET['finalizar'])) {
    $id = intval($_GET['finalizar']);
    $stmt = $conn->prepare("UPDATE turmas SET finalizada=1 WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    // Criar notificação para professores e alunos da turma
    $turma_info = $conn->prepare("SELECT nome, professor_id FROM turmas WHERE id=?");
    $turma_info->bind_param("i", $id);
    $turma_info->execute();
    $turma = $turma_info->get_result()->fetch_assoc();

    if ($turma) {
        if (function_exists('criar_notificacao')) {
            criar_notificacao(
                $conn,
                $turma['professor_id'],
                'professor',
                'Turma Finalizada',
                "A turma '{$turma['nome']}' foi finalizada.",
                'info'
            );
        }
        $alunos = $conn->prepare("SELECT id FROM alunos WHERE turma_id=?");
        $alunos->bind_param("i", $id);
        $alunos->execute();
        $resultado_alunos = $alunos->get_result();

        while ($aluno = $resultado_alunos->fetch_assoc()) {
            if (function_exists('criar_notificacao')) {
                criar_notificacao(
                    $conn,
                    $aluno['id'],
                    'aluno',
                    'Turma Finalizada',
                    "Sua turma '{$turma['nome']}' foi finalizada.",
                    'info'
                );
            }
        }
    }
    header("Location: dashboard_admin.php");
    exit;
}

// Reabrir turma
if(isset($_GET['reabrir'])) {
    $id = intval($_GET['reabrir']);
    $stmt = $conn->prepare("UPDATE turmas SET finalizada=0 WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: dashboard_admin.php");
    exit;
}

// Estatísticas básicas
$stats = [];
$stats['total_alunos'] = $conn->query("SELECT COUNT(*) as total FROM alunos")->fetch_assoc()['total'] ?? 0;
$stats['total_professores'] = $conn->query("SELECT COUNT(*) as total FROM professores")->fetch_assoc()['total'] ?? 0;
$stats['total_turmas'] = $conn->query("SELECT COUNT(*) as total FROM turmas")->fetch_assoc()['total'] ?? 0;
$stats['turmas_ativas'] = $conn->query("SELECT COUNT(*) as total FROM turmas WHERE finalizada=0")->fetch_assoc()['total'] ?? 0;

// --- NOVO BLOCO: alunos em atraso e dias de atraso ---
$alunos_atraso = 0;
$dias_media_atraso = 0;
$dias_total_atraso = 0;

$resAtraso = $conn->query("
    SELECT m.data_vencimento, a.id
    FROM mensalidades m
    JOIN contas_aluno ca ON m.conta_id = ca.id
    JOIN alunos a ON ca.aluno_id = a.id
    WHERE m.status != 'pago' AND m.data_vencimento < CURDATE()
");

$ids_alunos = [];
$dias_soma = 0;
while ($row = $resAtraso->fetch_assoc()) {
    $ids_alunos[$row['id']] = true;
    $dias = (strtotime(date('Y-m-d')) - strtotime($row['data_vencimento'])) / (60 * 60 * 24);
    $dias_soma += $dias;
    $dias_total_atraso++;
}
$alunos_atraso = count($ids_alunos);
$dias_media_atraso = $dias_total_atraso > 0 ? round($dias_soma / $dias_total_atraso, 1) : 0;

// Listar turmas
$turmas = $conn->query(
    "SELECT t.id, t.nome, t.turno, p.nome as professor, t.finalizada, t.professor_pode_cadastrar,
    (SELECT COUNT(*) FROM alunos a WHERE a.turma_id = t.id) as total_alunos
    FROM turmas t LEFT JOIN professores p ON p.id=t.professor_id
    ORDER BY t.finalizada ASC, t.nome ASC"
);

// Contar notificações não lidas
$notificacoes_nao_lidas = function_exists('contar_notificacoes_nao_lidas')
    ? contar_notificacoes_nao_lidas($conn, $_SESSION['id'], 'admin')
    : 0;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel do Administrador</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .dashboard-card {
            background: #f9fafb;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #b0b8c1;
            text-align: center;
        }
        .dashboard-card h3 {
            margin: 0 0 10px 0;
            color: #555;
        }
        .dashboard-card .number {
            font-size: 2em;
            font-weight: bold;
            color: #b0b8c1;
        }
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .quick-actions a {
            text-decoration: none;
        }
        .quick-actions button {
            width: 100%;
            padding: 15px;
            font-size: 1em;
        }
        .notification-badge {
            background: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.8em;
            margin-left: 5px;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
            font-weight: bold;
        }
        .status-ativa {
            background: #d4edda;
            color: #155724;
        }
        .status-finalizada {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h1>Painel do Administrador</h1>
        <div>
            <span>Bem-vindo, <?= htmlspecialchars($_SESSION['nome']) ?>!</span>
            <?php if ($notificacoes_nao_lidas > 0): ?>
                <span class="notification-badge"><?= $notificacoes_nao_lidas ?></span>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Estatísticas -->
    <div class="dashboard-grid">
        <div class="dashboard-card">
            <h3>Total de Alunos</h3>
            <div class="number"><?= $stats['total_alunos'] ?></div>
        </div>
        <div class="dashboard-card">
            <h3>Total de Professores</h3>
            <div class="number"><?= $stats['total_professores'] ?></div>
        </div>
        <div class="dashboard-card">
            <h3>Total de Turmas</h3>
            <div class="number"><?= $stats['total_turmas'] ?></div>
        </div>
        <div class="dashboard-card">
            <h3>Turmas Ativas</h3>
            <div class="number"><?= $stats['turmas_ativas'] ?></div>
        </div>
        <div class="dashboard-card">
            <h3>Alunos em atraso</h3>
            <div class="number"><?= $alunos_atraso ?></div>
            <div style="margin-top:8px;font-size:0.95em;color:#a94442;">
                <span>Média dias atraso: <b><?= $dias_media_atraso ?></b></span>
            </div>
        </div>
    </div>
    
    <!-- Ações Rápidas -->
    <h2>Ações Rápidas</h2>
    <div class="quick-actions">
        <a href="gerenciar_usuarios.php"><button>Gerenciar Usuários</button></a>
        <a href="cadastro_turma.php"><button>Cadastrar Turma</button></a>
        <a href="cadastro_professor.php"><button>Cadastrar Professor</button></a>
        <a href="cadastro_aluno.php"><button>Cadastrar Aluno</button></a>
        <a href="notas_faltas.php"><button>Notas e Faltas</button></a>
        <a href="frequencia.php"><button>Controle de Frequência</button></a>
        <a href="relatorios.php"><button>Relatórios</button></a>
        <a href="comunicacao.php"><button>Comunicação</button></a>
        <a href="calendario.php"><button>Calendário Escolar</button></a>
        <a href="financeiro.php"><button>Módulo Financeiro</button></a>
        <a href="atividades.php"><button>Atividades / Conteúdos</button></a>
        <a href="documentos.php"><button>Documentos</button></a>
        <a href="liberar_cadastro.php"><button>Liberar auto cadastro</button></a>
        <a href="cobrancas.php"><button>Cobranças</button></a>
        <a href="cadastro_cobradores.php"><button>Cadastro de AGC</button></a>
    </div>

    <!-- Turmas -->
    <h2>Turmas</h2>
    <div class="table-responsive">
        <table>
            <tr>
                <th>Nome</th>
                <th>Turno</th>
                <th>Professor</th>
                <th>Alunos</th>
                <th>Status</th>
                <th>C.A.P.</th>
                <th>Ação</th>
            </tr>
            <?php if ($turmas && $turmas->num_rows > 0): ?>
                <?php while($t = $turmas->fetch_assoc()) { ?>
                    <tr>
                        <td><?= htmlspecialchars($t['nome']) ?></td>
                        <td><?= htmlspecialchars($t['turno']) ?></td>
                        <td><?= htmlspecialchars($t['professor'] ?? 'Não definido') ?></td>
                        <td><?= $t['total_alunos'] ?></td>
                        <td>
                            <span class="status-badge <?= $t['finalizada'] ? 'status-finalizada' : 'status-ativa' ?>">
                                <?= $t['finalizada'] ? 'Finalizada' : 'Ativa' ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!$t['professor_pode_cadastrar']): ?>
                                <a href="?liberar_cadastro_professor=<?= $t['id'] ?>"><button>L</button></a>
                                <span style="color:red;">OFF</span>
                            <?php else: ?>
                                <span style="color:green;">ON</span>
                                <a href="?bloquear_cadastro_professor=<?= $t['id'] ?>"><button>B</button></a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if(!$t['finalizada']) { ?>
                                <a href="?finalizar=<?= $t['id'] ?>" onclick="return confirm('Finalizar turma?')">
                                    <button>Finalizar</button>
                                </a>
                            <?php } else { ?>
                                <a href="?reabrir=<?= $t['id'] ?>" onclick="return confirm('Reabrir turma?')">
                                    <button>Reabrir</button>
                                </a>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            <?php else: ?>
                <tr>
                    <td colspan="7">Nenhuma turma cadastrada.</td>
                </tr>
            <?php endif; ?>
        </table>
    </div>
    
    <div style="margin-top: 30px;">
        <a href="logout.php"><button>Sair</button></a>
    </div>
</div>
</body>
</html>