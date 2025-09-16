<?php
session_start();
include "conexao.php";
if($_SESSION['tipo'] !== "professor") header("Location: index.php");
$professor_id = $_SESSION['id'];

// Exclusão de prova
if(isset($_GET['excluir'])) {
    $prova_id = intval($_GET['excluir']);
    // Excluir relacionamentos primeiro (CASCADE já faz isso, mas vamos ser explícitos)
    $conn->query("DELETE FROM prova_questoes WHERE prova_id=$prova_id");
    $conn->query("DELETE FROM provas WHERE id=$prova_id AND professor_id=$professor_id");
    echo "<script>alert('Prova excluída!');window.location='listar_provas.php';</script>";
}

// Buscar provas do professor
$provas = $conn->query("SELECT p.*, m.nome as materia_nome 
                       FROM provas p 
                       JOIN materias m ON m.id = p.materia_id 
                       WHERE p.professor_id = $professor_id 
                       ORDER BY p.created_at DESC");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Minhas Provas - Banco de Questões</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .prova-item {
            border: 1px solid #ddd;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            background: #fafafa;
        }
        .prova-titulo {
            font-weight: bold;
            font-size: 1.2em;
            margin-bottom: 5px;
        }
        .prova-info {
            color: #666;
            font-size: 0.9em;
            margin: 5px 0;
        }
        .prova-acoes {
            margin-top: 10px;
        }
        .prova-acoes button {
            width: auto;
            margin: 5px 5px 0 0;
            padding: 8px 15px;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Minhas Provas</h1>
    
    <?php if($provas && $provas->num_rows > 0) { ?>
        <p><strong>Total de provas:</strong> <?=$provas->num_rows?></p>
        
        <?php while($p = $provas->fetch_assoc()) { ?>
            <div class="prova-item">
                <div class="prova-titulo"><?=htmlspecialchars($p['titulo'])?></div>
                
                <?php if($p['descricao']) { ?>
                    <div class="prova-info"><strong>Descrição:</strong> <?=htmlspecialchars($p['descricao'])?></div>
                <?php } ?>
                
                <div class="prova-info">
                    <strong>Matéria:</strong> <?=$p['materia_nome']?> | 
                    <strong>Questões:</strong> <?=$p['total_questoes']?> | 
                    <strong>Criada em:</strong> <?=date('d/m/Y H:i', strtotime($p['created_at']))?>
                </div>
                
                <div class="prova-acoes">
                    <a href="visualizar_prova.php?id=<?=$p['id']?>"><button>Visualizar</button></a>
                    <a href="editar_prova.php?id=<?=$p['id']?>"><button>Editar</button></a>
                    <a href="imprimir_prova.php?id=<?=$p['id']?>" target="_blank"><button style="background-color: #28a745;">Imprimir</button></a>
                    <a href="?excluir=<?=$p['id']?>" onclick="return confirm('Tem certeza que deseja excluir esta prova?')"><button style="background-color: #dc3545;">Excluir</button></a>
                </div>
            </div>
        <?php } ?>
        
    <?php } else { ?>
        <p>Você ainda não criou nenhuma prova.</p>
    <?php } ?>
    
    <div style="margin-top: 20px;">
        <a href="cadastro_prova.php"><button>Criar Nova Prova</button></a>
        <a href="dashboard_professor.php"><button>Voltar ao Dashboard</button></a>
    </div>
</div>
</body>
</html>