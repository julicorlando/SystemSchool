<?php
session_start();
include "conexao.php";
if($_SESSION['tipo'] !== "professor") header("Location: index.php");
$professor_id = $_SESSION['id'];

// Cadastro de questão
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
    
    $stmt = $conn->prepare("INSERT INTO questoes (enunciado, alternativa_a, alternativa_b, alternativa_c, alternativa_d, resposta_correta, materia_id, professor_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssii", $enunciado, $alt_a, $alt_b, $alt_c, $alt_d, $resposta, $materia_id, $professor_id);
    
    if($stmt->execute()) {
        echo "<script>alert('Questão cadastrada com sucesso!');window.location='cadastro_questao.php';</script>";
    } else {
        echo "<script>alert('Erro ao cadastrar questão!');</script>";
    }
}

// Listar matérias
$materias = $conn->query("SELECT id, nome FROM materias ORDER BY nome ASC");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Cadastrar Questão - Banco de Questões</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container">
    <h1>Cadastrar Nova Questão</h1>
    
    <form method="post">
        <label>Matéria</label>
        <select name="materia_id" required>
            <option value="">Selecione uma matéria</option>
            <?php if($materias && $materias->num_rows > 0) {
                while($m = $materias->fetch_assoc()) { ?>
                <option value="<?=$m['id']?>"><?=$m['nome']?></option>
            <?php } } ?>
        </select>
        
        <label>Enunciado da Questão</label>
        <textarea name="enunciado" rows="4" required placeholder="Digite o enunciado da questão..."></textarea>
        
        <label>Alternativa A</label>
        <input type="text" name="alternativa_a" required placeholder="Digite a alternativa A">
        
        <label>Alternativa B</label>
        <input type="text" name="alternativa_b" required placeholder="Digite a alternativa B">
        
        <label>Alternativa C</label>
        <input type="text" name="alternativa_c" required placeholder="Digite a alternativa C">
        
        <label>Alternativa D</label>
        <input type="text" name="alternativa_d" required placeholder="Digite a alternativa D">
        
        <label>Resposta Correta</label>
        <select name="resposta_correta" required>
            <option value="">Selecione a resposta correta</option>
            <option value="A">A</option>
            <option value="B">B</option>
            <option value="C">C</option>
            <option value="D">D</option>
        </select>
        
        <button type="submit">Cadastrar Questão</button>
    </form>
    
    <a href="dashboard_professor.php"><button>Voltar ao Dashboard</button></a>
    <a href="listar_questoes.php"><button>Ver Minhas Questões</button></a>
</div>
</body>
</html>