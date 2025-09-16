<?php
session_start();
include "conexao.php";
if($_SESSION['tipo'] !== "professor") header("Location: index.php");
$professor_id = $_SESSION['id'];

$prova_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Verificar se a prova pertence ao professor
$prova = $conn->query("SELECT p.*, m.nome as materia_nome 
                      FROM provas p 
                      JOIN materias m ON m.id = p.materia_id 
                      WHERE p.id=$prova_id AND p.professor_id=$professor_id")->fetch_assoc();

if(!$prova) {
    echo "<script>alert('Prova não encontrada ou sem permissão!');window.location='listar_provas.php';</script>";
    exit;
}

// Buscar questões da prova
$questoes = $conn->query("SELECT q.*, pq.ordem 
                         FROM questoes q 
                         JOIN prova_questoes pq ON pq.questao_id = q.id 
                         WHERE pq.prova_id = $prova_id 
                         ORDER BY pq.ordem ASC");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Visualizar Prova - <?=htmlspecialchars($prova['titulo'])?></title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .prova-header {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .questao-item {
            border: 1px solid #ddd;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            background: #fff;
        }
        .questao-numero {
            font-weight: bold;
            color: #007bff;
            margin-bottom: 10px;
        }
        .questao-enunciado {
            font-weight: bold;
            margin-bottom: 15px;
            line-height: 1.4;
        }
        .alternativa {
            margin: 8px 0;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 3px;
        }
        .alternativa.correta {
            background-color: #d4edda;
            border-left: 4px solid #28a745;
            font-weight: bold;
        }
        .gabarito {
            background: #fff3cd;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            border-left: 4px solid #ffc107;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="prova-header">
        <h1><?=htmlspecialchars($prova['titulo'])?></h1>
        <p><strong>Matéria:</strong> <?=$prova['materia_nome']?></p>
        <?php if($prova['descricao']) { ?>
            <p><strong>Descrição:</strong> <?=htmlspecialchars($prova['descricao'])?></p>
        <?php } ?>
        <p><strong>Total de questões:</strong> <?=$prova['total_questoes']?></p>
        <p><strong>Criada em:</strong> <?=date('d/m/Y H:i', strtotime($prova['created_at']))?></p>
    </div>
    
    <?php if($questoes && $questoes->num_rows > 0) { ?>
        
        <?php $contador = 1; while($q = $questoes->fetch_assoc()) { ?>
            <div class="questao-item">
                <div class="questao-numero">Questão <?=$contador?></div>
                <div class="questao-enunciado"><?=nl2br(htmlspecialchars($q['enunciado']))?></div>
                
                <div class="alternativa <?=($q['resposta_correta'] == 'A' ? 'correta' : '')?>">
                    <strong>A)</strong> <?=htmlspecialchars($q['alternativa_a'])?>
                </div>
                <div class="alternativa <?=($q['resposta_correta'] == 'B' ? 'correta' : '')?>">
                    <strong>B)</strong> <?=htmlspecialchars($q['alternativa_b'])?>
                </div>
                <div class="alternativa <?=($q['resposta_correta'] == 'C' ? 'correta' : '')?>">
                    <strong>C)</strong> <?=htmlspecialchars($q['alternativa_c'])?>
                </div>
                <div class="alternativa <?=($q['resposta_correta'] == 'D' ? 'correta' : '')?>">
                    <strong>D)</strong> <?=htmlspecialchars($q['alternativa_d'])?>
                </div>
                
                <div class="gabarito">
                    <strong>Resposta Correta:</strong> <?=$q['resposta_correta']?>
                </div>
            </div>
            <?php $contador++; ?>
        <?php } ?>
        
    <?php } else { ?>
        <p>Esta prova não tem questões associadas.</p>
    <?php } ?>
    
    <div style="margin-top: 30px;">
        <a href="imprimir_prova.php?id=<?=$prova_id?>" target="_blank"><button style="background-color: #28a745;">Imprimir Prova</button></a>
        <a href="editar_prova.php?id=<?=$prova_id?>"><button>Editar Prova</button></a>
        <a href="listar_provas.php"><button>Voltar à Lista</button></a>
        <a href="dashboard_professor.php"><button>Dashboard</button></a>
    </div>
</div>
</body>
</html>