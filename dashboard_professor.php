<?php
session_start();
if($_SESSION['tipo'] !== "professor") header("Location: index.php");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Painel do Professor</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container">
    <h1>Painel do Professor</h1>
    <a href="atividades.php"><button>Enviar/Ver Atividades</button></a>
    <a href="notas_faltas.php"><button>Atribuir Notas e Faltas</button></a>
    <a href="logout.php"><button>Sair</button></a>
</div>
</body>
</html>