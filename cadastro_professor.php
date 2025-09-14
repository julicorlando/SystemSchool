<?php
session_start();
include "conexao.php";
if($_SESSION['tipo'] !== "admin") header("Location: index.php");

// Cadastro de professor
if(isset($_POST['nome']) && isset($_POST['usuario']) && isset($_POST['senha'])) {
    $nome = $_POST['nome'];
    $usuario = $_POST['usuario'];
    $senha = $_POST['senha'];
    $conn->query("INSERT INTO professores (nome, usuario, senha) VALUES ('$nome', '$usuario', '$senha')");
    echo "<script>alert('Professor cadastrado!');window.location='cadastro_professor.php';</script>";
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Cadastrar Professor</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container">
    <h1>Cadastrar Professor</h1>
    <form method="post">
        <label>Nome Completo</label>
        <input type="text" name="nome" required>
        <label>Usuário</label>
        <input type="text" name="usuario" required>
        <label>Senha</label>
        <input type="password" name="senha" required>
        <button type="submit">Salvar</button>
    </form>
    <a href="dashboard_admin.php"><button>Voltar</button></a>
</div>
</body>
</html>