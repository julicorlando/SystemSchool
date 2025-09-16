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
    
    <h2>Banco de Questões</h2>
    <a href="listar_questoes.php"><button>Minhas Questões</button></a>
    <a href="cadastro_questao.php"><button>Cadastrar Questão</button></a>
    <a href="listar_provas.php"><button>Minhas Provas</button></a>
    <a href="cadastro_prova.php"><button>Criar Prova</button></a>
    <a href="listar_materias.php"><button>Matérias</button></a>
    
    <a href="logout.php"><button>Sair</button></a>
</div>
</body>
</html>