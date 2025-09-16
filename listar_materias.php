<?php
session_start();
include "conexao.php";
if($_SESSION['tipo'] !== "professor" && $_SESSION['tipo'] !== "admin") header("Location: index.php");
$user_id = $_SESSION['id'];
$user_type = $_SESSION['tipo'];

// Exclusão de matéria (apenas admin)
if($user_type === "admin" && isset($_GET['excluir'])) {
    $materia_id = intval($_GET['excluir']);
    $conn->query("DELETE FROM materias WHERE id=$materia_id");
    echo "<script>alert('Matéria excluída!');window.location='listar_materias.php';</script>";
}

// Cadastro de nova matéria
if(isset($_POST['nome']) && isset($_POST['descricao'])) {
    $nome = $_POST['nome'];
    $descricao = $_POST['descricao'];
    $conn->query("INSERT INTO materias (nome, descricao) VALUES ('$nome', '$descricao')");
    echo "<script>alert('Matéria cadastrada!');window.location='listar_materias.php';</script>";
}

// Listar todas as matérias
$materias = $conn->query("SELECT * FROM materias ORDER BY nome ASC");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Matérias - Banco de Questões</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container">
    <h1>Matérias</h1>
    
    <h2>Cadastrar Nova Matéria</h2>
    <form method="post">
        <label>Nome da Matéria</label>
        <input type="text" name="nome" required>
        <label>Descrição</label>
        <textarea name="descricao" rows="3"></textarea>
        <button type="submit">Cadastrar Matéria</button>
    </form>
    
    <h2>Matérias Disponíveis</h2>
    <table>
        <tr>
            <th>Nome</th>
            <th>Descrição</th>
            <th>Data de Criação</th>
            <?php if($user_type === "admin") { ?><th>Ações</th><?php } ?>
        </tr>
        <?php if($materias && $materias->num_rows > 0) { 
            while($m = $materias->fetch_assoc()) { ?>
            <tr>
                <td><?=$m['nome']?></td>
                <td><?=$m['descricao']?></td>
                <td><?=date('d/m/Y H:i', strtotime($m['created_at']))?></td>
                <?php if($user_type === "admin") { ?>
                <td>
                    <a href="?excluir=<?=$m['id']?>" onclick="return confirm('Tem certeza? Isso excluirá todas as questões desta matéria!')">
                        <button style="background-color: #dc3545;">Excluir</button>
                    </a>
                </td>
                <?php } ?>
            </tr>
        <?php } } else { ?>
            <tr><td colspan="<?=($user_type === 'admin' ? '4' : '3')?>">Nenhuma matéria encontrada.</td></tr>
        <?php } ?>
    </table>
    
    <a href="dashboard_<?=$user_type?>.php"><button>Voltar</button></a>
</div>
</body>
</html>