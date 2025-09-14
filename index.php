<?php
session_start();
if(isset($_SESSION['tipo'])) {
    if($_SESSION['tipo'] == "admin") header("Location: dashboard_admin.php");
    if($_SESSION['tipo'] == "professor") header("Location: dashboard_professor.php");
    if($_SESSION['tipo'] == "aluno") header("Location: dashboard_aluno.php");
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
        <input type="text" name="usuario" required>
        <label for="senha">Senha</label>
        <input type="password" name="senha" required>
        <label for="tipo">Tipo de acesso</label>
        <select name="tipo">
            <option value="admin">Administrador</option>
            <option value="professor">Professor</option>
            <option value="aluno">Aluno</option>
        </select>
        <button type="submit">Entrar</button>
    </form>
</div>
</body>
</html>