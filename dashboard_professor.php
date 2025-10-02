<?php
session_start();
include "conexao.php";
if($_SESSION['tipo'] !== "professor") header("Location: index.php");

$id_professor = $_SESSION['id'];

// Buscar estatísticas básicas do professor
$stats = [];

// Número de turmas
$result = $conn->prepare("SELECT COUNT(*) as total FROM turmas WHERE professor_id = ?");
$result->bind_param("i", $id_professor);
$result->execute();
$stats['turmas'] = $result->get_result()->fetch_assoc()['total'];

// Número total de alunos
$result = $conn->prepare("SELECT COUNT(DISTINCT a.id) as total FROM alunos a JOIN turmas t ON a.turma_id = t.id WHERE t.professor_id = ?");
$result->bind_param("i", $id_professor);
$result->execute();
$stats['alunos'] = $result->get_result()->fetch_assoc()['total'];

// Mensagens não lidas
$stats['mensagens'] = contar_notificacoes_nao_lidas($conn, $id_professor, 'professor');

// Atividades enviadas
$result = $conn->prepare("SELECT COUNT(*) as total FROM atividades WHERE professor_id = ?");
$result->bind_param("i", $id_professor);
$result->execute();
$stats['atividades'] = $result->get_result()->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel do Professor</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h1>Painel do Professor</h1>
        <div>
            <span>Bem-vindo, <?= htmlspecialchars($_SESSION['nome'] ?? $_SESSION['usuario']) ?>!</span>
        </div>
    </div>
    
    <!-- Estatísticas -->
    <div class="dashboard-grid">
        <div class="dashboard-card">
            <h3>Minhas Turmas</h3>
            <div class="number"><?= $stats['turmas'] ?></div>
        </div>
        <div class="dashboard-card">
            <h3>Total de Alunos</h3>
            <div class="number"><?= $stats['alunos'] ?></div>
        </div>
        <div class="dashboard-card">
            <h3>Atividades Enviadas</h3>
            <div class="number"><?= $stats['atividades'] ?></div>
        </div>
        <div class="dashboard-card">
            <h3>Mensagens</h3>
            <div class="number"><?= $stats['mensagens'] ?></div>
        </div>
    </div>
    
    <!-- Ações Rápidas -->
    <h2>Ações Rápidas</h2>
    <div class="quick-actions">
        <a href="atividades.php"><button>Enviar/Ver Atividades e conteúdos</button></a>
        <a href="notas_faltas.php"><button>Atribuir Notas e Faltas</button></a>
        <a href="frequencia.php"><button>Controle de Frequência</button></a>
        <a href="comunicacao.php"><button>Comunicação</button></a>
        </button></a>
        <a href="calendario.php"><button>Calendário Escolar</button></a>
        <a href="relatorios.php"><button>Relatórios</button></a>
        <a href="cadastro_aluno_professor.php"><button>Cadastrar Alunos</button></a>
    </div>
    
    <div style="margin-top: 30px;">
        <a href="logout.php"><button>Sair</button></a>
    </div>
</div>
</body>
</html>