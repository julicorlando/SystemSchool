<?php
session_start();
if($_SESSION['tipo'] !== "aluno") header("Location: index.php");
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
    <a href="atividades.php"><button>Atividades</button></a>
    <a href="logout.php"><button>Sair</button></a>
</div>
</body>
</html>