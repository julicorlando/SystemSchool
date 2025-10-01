<?php
session_start();
if(isset($_SESSION['tipo'])) {
    switch($_SESSION['tipo']) {
        case "admin":
            header("Location: dashboard_admin.php");
            exit;
        case "professor":
            header("Location: dashboard_professor.php");
            exit;
        case "aluno":
            header("Location: dashboard_aluno.php");
            exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Sistema Escolar</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container">
    <h1>Sistema Escolar</h1>
    <form method="post" action="login.php">
        <label for="usuario">Usuário</label>
        <input type="text" name="usuario" id="usuario" required>
        <label for="senha">Senha</label>
        <input type="password" name="senha" id="senha" required>
        <button type="submit">Entrar</button>
    </form>
</div>
</body>
</html>