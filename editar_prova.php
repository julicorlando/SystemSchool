<?php
session_start();
include "conexao.php";
if($_SESSION['tipo'] !== "professor") header("Location: index.php");
$professor_id = $_SESSION['id'];

$prova_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Verificar se a prova pertence ao professor
$prova = $conn->query("SELECT * FROM provas WHERE id=$prova_id AND professor_id=$professor_id")->fetch_assoc();
if(!$prova) {
    echo "<script>alert('Prova não encontrada ou sem permissão!');window.location='listar_provas.php';</script>";
    exit;
}

// Atualização da prova
if(isset($_POST['titulo']) && isset($_POST['materia_id'])) {
    $titulo = $_POST['titulo'];
    $descricao = $_POST['descricao'] ?? '';
    $materia_id = intval($_POST['materia_id']);
    
    $stmt = $conn->prepare("UPDATE provas SET titulo=?, descricao=?, materia_id=? WHERE id=? AND professor_id=?");
    $stmt->bind_param("ssiii", $titulo, $descricao, $materia_id, $prova_id, $professor_id);
    
    if($stmt->execute()) {
        echo "<script>alert('Prova atualizada com sucesso!');window.location='listar_provas.php';</script>";
    } else {
        echo "<script>alert('Erro ao atualizar prova!');</script>";
    }
}

// Resortear questões
if(isset($_POST['resortear']) && isset($_POST['num_questoes'])) {
    $num_questoes = intval($_POST['num_questoes']);
    $materia_id = $prova['materia_id'];
    
    // Verificar se há questões suficientes
    $count_questoes = $conn->query("SELECT COUNT(*) as total FROM questoes WHERE materia_id=$materia_id AND professor_id=$professor_id")->fetch_assoc();
    
    if($count_questoes['total'] < $num_questoes) {
        echo "<script>alert('Você não tem questões suficientes nesta matéria! Disponível: {$count_questoes['total']}, Solicitado: $num_questoes');</script>";
    } else {
        // Excluir questões antigas
        $conn->query("DELETE FROM prova_questoes WHERE prova_id=$prova_id");
        
        // Sortear novas questões
        $questoes_sorteadas = $conn->query("SELECT id FROM questoes WHERE materia_id=$materia_id AND professor_id=$professor_id ORDER BY RAND() LIMIT $num_questoes");
        
        $ordem = 1;
        while($q = $questoes_sorteadas->fetch_assoc()) {
            $conn->query("INSERT INTO prova_questoes (prova_id, questao_id, ordem) VALUES ($prova_id, {$q['id']}, $ordem)");
            $ordem++;
        }
        
        // Atualizar total de questões
        $conn->query("UPDATE provas SET total_questoes=$num_questoes WHERE id=$prova_id");
        
        echo "<script>alert('Questões da prova foram resorteadas!');window.location='visualizar_prova.php?id=$prova_id';</script>";
    }
}

// Listar matérias
$materias = $conn->query("SELECT id, nome FROM materias ORDER BY nome ASC");

// Contar questões atuais da prova
$total_questoes_atuais = $conn->query("SELECT COUNT(*) as total FROM prova_questoes WHERE prova_id=$prova_id")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Editar Prova - Banco de Questões</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .secao {
            background: #f8f9fa;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            border-left: 4px solid #007bff;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Editar Prova</h1>
    
    <div class="secao">
        <h2>Informações da Prova</h2>
        <form method="post">
            <label>Título da Prova</label>
            <input type="text" name="titulo" required value="<?=htmlspecialchars($prova['titulo'])?>">
            
            <label>Descrição</label>
            <textarea name="descricao" rows="3"><?=htmlspecialchars($prova['descricao'])?></textarea>
            
            <label>Matéria</label>
            <select name="materia_id" required>
                <?php if($materias && $materias->num_rows > 0) {
                    while($m = $materias->fetch_assoc()) { ?>
                    <option value="<?=$m['id']?>" <?=($prova['materia_id'] == $m['id'] ? 'selected' : '')?>><?=$m['nome']?></option>
                <?php } } ?>
            </select>
            
            <button type="submit">Atualizar Informações</button>
        </form>
    </div>
    
    <div class="secao">
        <h2>Questões da Prova</h2>
        <p><strong>Questões atuais:</strong> <?=$total_questoes_atuais?></p>
        
        <form method="post">
            <label>Resortear Questões - Novo Número de Questões</label>
            <input type="number" name="num_questoes" min="1" value="<?=$prova['total_questoes']?>" required>
            <input type="hidden" name="resortear" value="1">
            <div style="color: #666; font-size: 0.9em; margin: 5px 0;">
                ⚠️ Atenção: Isso irá excluir todas as questões atuais e sortear novas questões da mesma matéria.
            </div>
            <button type="submit" onclick="return confirm('Tem certeza? Isso irá substituir todas as questões atuais!')">Resortear Questões</button>
        </form>
    </div>
    
    <div style="margin-top: 20px;">
        <a href="visualizar_prova.php?id=<?=$prova_id?>"><button>Visualizar Prova</button></a>
        <a href="listar_provas.php"><button>Voltar à Lista</button></a>
        <a href="dashboard_professor.php"><button>Dashboard</button></a>
    </div>
</div>
</body>
</html>