<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include "conexao.php";

// Verifica se o usuário está logado como aluno
if (!isset($_SESSION['tipo']) || $_SESSION['tipo'] !== "aluno" || !isset($_SESSION['id'])) {
    header("Location: index.php");
    exit;
}

$id_aluno = $_SESSION['id'];

// Bloqueio de acesso 10 dias após finalização da turma
$turma_data = $conn->query("SELECT t.data_finalizacao FROM turmas t JOIN alunos a ON a.turma_id = t.id WHERE a.id = $id_aluno")->fetch_assoc();
if ($turma_data && $turma_data['data_finalizacao']) {
    $dias_passados = (strtotime(date('Y-m-d')) - strtotime($turma_data['data_finalizacao'])) / (60*60*24);
    if ($dias_passados > 10) {
        echo "<script>alert('Esta turma foi encerrada. O acesso foi bloqueado após 10 dias.');window.location='logout.php';</script>";
        exit;
    }
}

// Função de notificações (placeholder caso não exista)
if (!function_exists('contar_notificacoes_nao_lidas')) {
    function contar_notificacoes_nao_lidas($conn, $id_aluno, $tipo) {
        // Exemplo: retorne 0 ou implemente sua lógica
        return 0;
    }
}

$stats = [];

// Dados do aluno e da turma
$stmt = $conn->prepare("SELECT a.*, t.nome as turma_nome FROM alunos a JOIN turmas t ON a.turma_id = t.id WHERE a.id = ?");
$stmt->bind_param("i", $id_aluno);
$stmt->execute();
$aluno_dados = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$aluno_dados) {
    echo "Aluno não encontrado!";
    exit;
}

// Notas e faltas
$stmt = $conn->prepare("SELECT nota1, nota2, media FROM notas_faltas WHERE aluno_id = ?");
$stmt->bind_param("i", $id_aluno);
$stmt->execute();
$notas_faltas = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Verifica notas pendentes
$notas_pendentes = false;
$pendentes = [];
if (!$notas_faltas || $notas_faltas['nota1'] === null || $notas_faltas['nota2'] === null || $notas_faltas['media'] === null) {
    $notas_pendentes = true;
    if (!$notas_faltas || $notas_faltas['nota1'] === null) $pendentes[] = "Nota 1";
    if (!$notas_faltas || $notas_faltas['nota2'] === null) $pendentes[] = "Nota 2";
    if (!$notas_faltas || $notas_faltas['media'] === null) $pendentes[] = "Média";
}

// CONTABILIZAÇÃO DAS FALTAS DIRETO NA TABELA FREQUENCIA
// Cada registro presente=0 é uma falta
$stmt = $conn->prepare("SELECT COUNT(*) AS faltas FROM frequencia WHERE aluno_id = ? AND presente = 0");
$stmt->bind_param("i", $id_aluno);
$stmt->execute();
$faltas_result = $stmt->get_result()->fetch_assoc();
$stmt->close();
$faltas = $faltas_result['faltas'] ?? 0;

// Status acadêmico
$status = "-";
if ($notas_faltas && !$notas_pendentes) {
    $media = $notas_faltas['media'];
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
$stmt->close();

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
$stmt->close();

$stats['frequencia_mes'] = $freq_mes['total_dias'] > 0 ? round(($freq_mes['presencas'] / $freq_mes['total_dias']) * 100, 1) : 0;

// Frequência TOTAL
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_dias,
        SUM(CASE WHEN presente = 1 THEN 1 ELSE 0 END) as presencas
    FROM frequencia 
    WHERE aluno_id = ?
");
$stmt->bind_param("i", $id_aluno);
$stmt->execute();
$freq_total = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stats['frequencia_total'] = $freq_total['total_dias'] > 0 ? round(($freq_total['presencas'] / $freq_total['total_dias']) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel do Aluno</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .dashboard-grid { display: flex; gap: 20px; margin: 20px 0; flex-wrap: wrap;}
        .dashboard-card { background: #fff; border-radius: 8px; box-shadow: 0 2px 8px #eee; padding: 20px; min-width: 200px; text-align: center;}
        .number { font-size: 2em; font-weight: bold; margin: 10px 0; }
        .notification-badge, .badge-danger { background: #e74c3c; color: #fff; border-radius: 50%; padding: 3px 8px; font-size: 0.9em; margin-left: 6px;}
        .msg-sucesso { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin: 10px 0; border: 1px solid #c3e6cb; }
        .msg-erro { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin: 10px 0; border: 1px solid #f5c6cb; }
        .text-success { color: #28a745; }
        .text-danger { color: #e74c3c; }
        .text-warning { color: #f39c12; }
        .quick-actions { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px;}
        .quick-actions a button { min-width: 160px; }
        .container { max-width: 900px; margin: auto; padding: 30px; background: #f6f9fc; border-radius: 12px; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 2px 8px #eee; margin: 18px 0; }
        .card-header { padding: 12px 20px; border-bottom: 1px solid #eee; }
        .card-body { padding: 20px; }
        .row { display: flex; gap: 20px; }
        .col-6 { flex: 1; }
        .btn { padding: 10px 18px; border-radius: 5px; border: none; background: #3498db; color: #fff; cursor: pointer; }
        .btn:hover { background: #217dbb; }
    </style>
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
                    <p><strong>Nota 1:</strong> <?= isset($notas_faltas['nota1']) ? $notas_faltas['nota1'] : '-' ?></p>
                    <p><strong>Nota 2:</strong> <?= isset($notas_faltas['nota2']) ? $notas_faltas['nota2'] : '-' ?></p>
                </div>
                <div class="col-6">
                    <p><strong>Média:</strong> <?= $notas_faltas['media'] ?? '-' ?></p>
                    <p><strong>Status:</strong>
                        <span class="<?= $status === 'Aprovado' ? 'text-success' : ($status === 'Reprovado' || $status === 'Reprovado por faltas' ? 'text-danger' : 'text-warning') ?>">
                            <?= $status ?>
                        </span>
                    </p>
                    <?php if ($notas_pendentes): ?>
                        <div class="msg-erro" style="margin-top:8px;">
                            <strong>Notas pendentes:</strong> <?= implode(", ", $pendentes) ?>.
                            Aguarde o professor lançar todas as notas.
                        </div>
                    <?php endif; ?>
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
            <h3>Frequência Total</h3>
            <div class="number"><?= $stats['frequencia_total'] ?>%</div>
        </div>
        <div class="dashboard-card">
            <h3>Faltas</h3>
            <div class="number"><?= $faltas ?></div>
        </div>
        <div class="dashboard-card">
            <h3>Mensagens</h3>
            <div class="number"><?= $stats['mensagens'] ?></div>
        </div>
    </div>
    
    <!-- Ações Rápidas -->
    <h2>Ações Rápidas</h2>
    <div class="quick-actions">
        <a href="notas_faltas.php"><button class="btn">Ver Notas e Faltas</button></a>
        <a href="atividades.php"><button class="btn">Atividades / Conteúdos
            <?php if ($stats['atividades_pendentes'] > 0): ?>
                <span class="badge badge-danger"><?= $stats['atividades_pendentes'] ?></span>
            <?php endif; ?>
        </button></a>
        <a href="comunicacao.php"><button class="btn">Comunicação
            <?php if ($stats['mensagens'] > 0): ?>
                <span class="notification-badge"><?= $stats['mensagens'] ?></span>
            <?php endif; ?>
        </button></a>
        <a href="calendario.php"><button class="btn">Calendário Escolar</button></a>
        <a href="documentos.php"><button class="btn">Meus Documentos</button></a>
    </div>
    
    <div style="margin-top: 30px;">
        <a href="logout.php"><button class="btn">Sair</button></a>
    </div>
</div>
</body>
</html>