<?php
session_start();
include "conexao.php";
if($_SESSION['tipo'] !== "professor") header("Location: index.php");
$professor_id = $_SESSION['id'];

// Exclusão de questão
if(isset($_GET['excluir'])) {
    $questao_id = intval($_GET['excluir']);
    $conn->query("DELETE FROM questoes WHERE id=$questao_id AND professor_id=$professor_id");
    echo "<script>alert('Questão excluída!');window.location='listar_questoes.php';</script>";
}

// Filtro por matéria
$filtro_materia = isset($_GET['materia_id']) ? intval($_GET['materia_id']) : 0;

// Buscar questões do professor
$sql = "SELECT q.*, m.nome as materia_nome 
        FROM questoes q 
        JOIN materias m ON m.id = q.materia_id 
        WHERE q.professor_id = $professor_id";

if($filtro_materia > 0) {
    $sql .= " AND q.materia_id = $filtro_materia";
}

$sql .= " ORDER BY q.created_at DESC";
$questoes = $conn->query($sql);

// Listar matérias para filtro
$materias = $conn->query("SELECT id, nome FROM materias ORDER BY nome ASC");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Minhas Questões - Banco de Questões</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .questao-item {
            border: 1px solid #ddd;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            background: #fafafa;
        }
        .questao-enunciado {
            font-weight: bold;
            margin-bottom: 10px;
        }
        .alternativa {
            margin: 5px 0;
            padding: 5px;
        }
        .alternativa.correta {
            background-color: #d4edda;
            font-weight: bold;
        }
        .questao-info {
            color: #666;
            font-size: 0.9em;
            margin-top: 10px;
        }
        .questao-acoes {
            margin-top: 10px;
        }
        .questao-acoes button {
            width: auto;
            margin: 5px 5px 0 0;
            padding: 8px 15px;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Minhas Questões</h1>
    
    <!-- Filtro por matéria -->
    <form method="get" style="margin-bottom: 20px;">
        <label>Filtrar por Matéria:</label>
        <select name="materia_id" onchange="this.form.submit()">
            <option value="0">Todas as matérias</option>
            <?php if($materias && $materias->num_rows > 0) {
                $materias->data_seek(0);
                while($m = $materias->fetch_assoc()) { ?>
                <option value="<?=$m['id']?>" <?=($filtro_materia == $m['id'] ? 'selected' : '')?>><?=$m['nome']?></option>
            <?php } } ?>
        </select>
    </form>
    
    <?php if($questoes && $questoes->num_rows > 0) { ?>
        <p><strong>Total de questões:</strong> <?=$questoes->num_rows?></p>
        
        <?php while($q = $questoes->fetch_assoc()) { ?>
            <div class="questao-item">
                <div class="questao-enunciado"><?=htmlspecialchars($q['enunciado'])?></div>
                
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
                
                <div class="questao-info">
                    <strong>Matéria:</strong> <?=$q['materia_nome']?> | 
                    <strong>Resposta Correta:</strong> <?=$q['resposta_correta']?> | 
                    <strong>Criada em:</strong> <?=date('d/m/Y H:i', strtotime($q['created_at']))?>
                </div>
                
                <div class="questao-acoes">
                    <a href="editar_questao.php?id=<?=$q['id']?>"><button>Editar</button></a>
                    <a href="?excluir=<?=$q['id']?>" onclick="return confirm('Tem certeza que deseja excluir esta questão?')"><button style="background-color: #dc3545;">Excluir</button></a>
                </div>
            </div>
        <?php } ?>
        
    <?php } else { ?>
        <p>Você ainda não cadastrou nenhuma questão.</p>
    <?php } ?>
    
    <div style="margin-top: 20px;">
        <a href="cadastro_questao.php"><button>Cadastrar Nova Questão</button></a>
        <a href="dashboard_professor.php"><button>Voltar ao Dashboard</button></a>
    </div>
</div>
</body>
</html>