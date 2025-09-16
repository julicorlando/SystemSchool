<?php
session_start();
include "conexao.php";
if($_SESSION['tipo'] !== "professor") header("Location: index.php");
$professor_id = $_SESSION['id'];

$questao_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Verificar se a questão pertence ao professor
$questao = $conn->query("SELECT * FROM questoes WHERE id=$questao_id AND professor_id=$professor_id")->fetch_assoc();
if(!$questao) {
    echo "<script>alert('Questão não encontrada ou sem permissão!');window.location='listar_questoes.php';</script>";
    exit;
}

// Atualização da questão
if(isset($_POST['enunciado']) && isset($_POST['alternativa_a']) && isset($_POST['alternativa_b']) && 
   isset($_POST['alternativa_c']) && isset($_POST['alternativa_d']) && isset($_POST['resposta_correta']) && 
   isset($_POST['materia_id'])) {
    
    $enunciado = $_POST['enunciado'];
    $alt_a = $_POST['alternativa_a'];
    $alt_b = $_POST['alternativa_b'];
    $alt_c = $_POST['alternativa_c'];
    $alt_d = $_POST['alternativa_d'];
    $resposta = $_POST['resposta_correta'];
    $materia_id = intval($_POST['materia_id']);
    
    $stmt = $conn->prepare("UPDATE questoes SET enunciado=?, alternativa_a=?, alternativa_b=?, alternativa_c=?, alternativa_d=?, resposta_correta=?, materia_id=?, updated_at=NOW() WHERE id=? AND professor_id=?");
    $stmt->bind_param("ssssssiii", $enunciado, $alt_a, $alt_b, $alt_c, $alt_d, $resposta, $materia_id, $questao_id, $professor_id);
    
    if($stmt->execute()) {
        echo "<script>alert('Questão atualizada com sucesso!');window.location='listar_questoes.php';</script>";
    } else {
        echo "<script>alert('Erro ao atualizar questão!');</script>";
    }
}

// Listar matérias
$materias = $conn->query("SELECT id, nome FROM materias ORDER BY nome ASC");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Editar Questão - Banco de Questões</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container">
    <h1>Editar Questão</h1>
    
    <form method="post">
        <label>Matéria</label>
        <select name="materia_id" required>
            <option value="">Selecione uma matéria</option>
            <?php if($materias && $materias->num_rows > 0) {
                while($m = $materias->fetch_assoc()) { ?>
                <option value="<?=$m['id']?>" <?=($questao['materia_id'] == $m['id'] ? 'selected' : '')?>><?=$m['nome']?></option>
            <?php } } ?>
        </select>
        
        <label>Enunciado da Questão</label>
        <textarea name="enunciado" rows="4" required placeholder="Digite o enunciado da questão..."><?=htmlspecialchars($questao['enunciado'])?></textarea>
        
        <label>Alternativa A</label>
        <input type="text" name="alternativa_a" required placeholder="Digite a alternativa A" value="<?=htmlspecialchars($questao['alternativa_a'])?>">
        
        <label>Alternativa B</label>
        <input type="text" name="alternativa_b" required placeholder="Digite a alternativa B" value="<?=htmlspecialchars($questao['alternativa_b'])?>">
        
        <label>Alternativa C</label>
        <input type="text" name="alternativa_c" required placeholder="Digite a alternativa C" value="<?=htmlspecialchars($questao['alternativa_c'])?>">
        
        <label>Alternativa D</label>
        <input type="text" name="alternativa_d" required placeholder="Digite a alternativa D" value="<?=htmlspecialchars($questao['alternativa_d'])?>">
        
        <label>Resposta Correta</label>
        <select name="resposta_correta" required>
            <option value="">Selecione a resposta correta</option>
            <option value="A" <?=($questao['resposta_correta'] == 'A' ? 'selected' : '')?>>A</option>
            <option value="B" <?=($questao['resposta_correta'] == 'B' ? 'selected' : '')?>>B</option>
            <option value="C" <?=($questao['resposta_correta'] == 'C' ? 'selected' : '')?>>C</option>
            <option value="D" <?=($questao['resposta_correta'] == 'D' ? 'selected' : '')?>>D</option>
        </select>
        
        <button type="submit">Atualizar Questão</button>
    </form>
    
    <a href="listar_questoes.php"><button>Voltar à Lista</button></a>
    <a href="dashboard_professor.php"><button>Dashboard</button></a>
</div>
</body>
</html>