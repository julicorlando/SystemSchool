<?php
session_start();
include "conexao.php";
if($_SESSION['tipo'] !== "aluno") header("Location: index.php");

$id_aluno = $_SESSION['id'];

// Buscar estatísticas básicas do aluno
$stats = [];

// Dados do aluno
$stmt = $conn->prepare("SELECT a.*, t.nome as turma_nome FROM alunos a JOIN turmas t ON a.turma_id = t.id WHERE a.id = ?");
$stmt->bind_param("i", $id_aluno);
$stmt->execute();
$aluno_dados = $stmt->get_result()->fetch_assoc();

// Notas e faltas
$stmt = $conn->prepare("SELECT nota1, nota2, media, faltas FROM notas_faltas WHERE aluno_id = ?");
$stmt->bind_param("i", $id_aluno);
$stmt->execute();
$notas_faltas = $stmt->get_result()->fetch_assoc();

// Status acadêmico
$status = "-";
if ($notas_faltas) {
    $media = $notas_faltas['media'];
    $faltas = $notas_faltas['faltas'];
    if ($faltas >= 4) {
        $status = "Reprovado por faltas";
    } elseif ($media >= 7) {
        $status = "Aprovado";
    } elseif ($media >= 5) {
        $status = "Recuperação";
    } else {
        $status = "Reprovado";
    }
}

// Atividades pendentes
$stmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM atividades a 
    WHERE a.turma_id = ? 
    AND a.id NOT IN (
        SELECT atividade_id FROM respostas WHERE aluno_id = ?
    )
");
$stmt->bind_param("ii", $aluno_dados['turma_id'], $id_aluno);
$stmt->execute();
$stats['atividades_pendentes'] = $stmt->get_result()->fetch_assoc()['total'];

// Mensagens não lidas
$stats['mensagens'] = contar_notificacoes_nao_lidas($conn, $id_aluno, 'aluno');

// Frequência do mês atual
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_dias,
        SUM(CASE WHEN presente = 1 THEN 1 ELSE 0 END) as presencas
    FROM frequencia 
    WHERE aluno_id = ? 
    AND MONTH(data) = MONTH(CURDATE()) 
    AND YEAR(data) = YEAR(CURDATE())
");
$stmt->bind_param("i", $id_aluno);
$stmt->execute();
$freq_mes = $stmt->get_result()->fetch_assoc();
$stats['frequencia_mes'] = $freq_mes['total_dias'] > 0 ? round(($freq_mes['presencas'] / $freq_mes['total_dias']) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel do Aluno</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h1>Painel do Aluno</h1>
        <div>
            <span>Bem-vindo, <?= htmlspecialchars($_SESSION['nome'] ?? $_SESSION['usuario']) ?>!</span>
            <?php if ($stats['mensagens'] > 0): ?>
                <span class="notification-badge"><?= $stats['mensagens'] ?></span>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Informações do Aluno -->
    <div class="card">
        <div class="card-header">
            <h3>Suas Informações</h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-6">
                    <p><strong>Matrícula:</strong> <?= htmlspecialchars($aluno_dados['matricula']) ?></p>
                    <p><strong>Turma:</strong> <?= htmlspecialchars($aluno_dados['turma_nome']) ?></p>
                </div>
                <div class="col-6">
                    <p><strong>Média:</strong> <?= $notas_faltas['media'] ?? '-' ?></p>
                    <p><strong>Status:</strong> <span class="<?= $status === 'Aprovado' ? 'text-success' : ($status === 'Reprovado' || $status === 'Reprovado por faltas' ? 'text-danger' : 'text-warning') ?>"><?= $status ?></span></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Estatísticas -->
    <div class="dashboard-grid">
        <div class="dashboard-card">
            <h3>Atividades Pendentes</h3>
            <div class="number"><?= $stats['atividades_pendentes'] ?></div>
        </div>
        <div class="dashboard-card">
            <h3>Frequência (Este Mês)</h3>
            <div class="number"><?= $stats['frequencia_mes'] ?>%</div>
        </div>
        <div class="dashboard-card">
            <h3>Faltas</h3>
            <div class="number"><?= $notas_faltas['faltas'] ?? 0 ?></div>
        </div>
        <div class="dashboard-card">
            <h3>Mensagens</h3>
            <div class="number"><?= $stats['mensagens'] ?></div>
        </div>
    </div>
    
    <!-- Ações Rápidas -->
    <h2>Ações Rápidas</h2>
    <div class="quick-actions">
        <a href="notas_faltas.php"><button>Ver Notas e Faltas</button></a>
        <a href="atividades.php"><button>Atividades 
            <?php if ($stats['atividades_pendentes'] > 0): ?>
                <span class="badge badge-danger"><?= $stats['atividades_pendentes'] ?></span>
            <?php endif; ?>
        </button></a>
        <a href="comunicacao.php"><button>Comunicação
            <?php if ($stats['mensagens'] > 0): ?>
                <span class="notification-badge"><?= $stats['mensagens'] ?></span>
            <?php endif; ?>
        </button></a>
        <a href="calendario.php"><button>Calendário Escolar</button></a>
        <a href="documentos.php"><button>Meus Documentos</button></a>
    </div>
    
    <div style="margin-top: 30px;">
        <a href="logout.php"><button>Sair</button></a>
    </div>
</div>
</body>
</html>