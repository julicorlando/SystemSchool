<?php
session_start();
include "conexao.php";
if($_SESSION['tipo'] !== "aluno") header("Location: index.php");

$id = $_SESSION['id'];

// Obter turma do aluno
$aluno = $conn->query("SELECT turma_id FROM alunos WHERE id=$id")->fetch_assoc();
$turma_id = $aluno['turma_id'];

// Contar atividades pendentes: tipo='atividade' E sem resposta do aluno
$pendentes_query = "SELECT COUNT(*) as total FROM atividades a 
                    WHERE a.turma_id=$turma_id 
                    AND (a.tipo='atividade' OR a.tipo IS NULL)
                    AND NOT EXISTS (
                        SELECT 1 FROM respostas r 
                        WHERE r.atividade_id=a.id AND r.aluno_id=$id
                    )";
$pendentes = $conn->query($pendentes_query)->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Painel do Aluno</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container">
    <h1>Painel do Aluno</h1>
    <a href="notas_faltas.php"><button>Ver Notas e Faltas</button></a>
    <a href="atividades.php"><button>Atividades <?php if($pendentes > 0) echo "<span style='background:#e74c3c;color:white;padding:2px 8px;border-radius:50%;font-size:0.85em;margin-left:5px;'>$pendentes</span>"; ?></button></a>
    <a href="logout.php"><button>Sair</button></a>
</div>
</body>
</html>