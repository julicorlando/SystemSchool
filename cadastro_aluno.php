<?php
session_start();
include "conexao.php";
if($_SESSION['tipo'] !== "admin") header("Location: index.php");

// Cadastro de aluno
if(isset($_POST['matricula']) && isset($_POST['nome']) && isset($_POST['usuario']) && isset($_POST['senha']) && isset($_POST['turma_id'])) {
    $matricula = $_POST['matricula'];
    $nome = $_POST['nome'];
    $usuario = $_POST['usuario'];
    $senha = $_POST['senha'];
    $turma_id = $_POST['turma_id'];
    $conn->query("INSERT INTO alunos (matricula, nome, usuario, senha, turma_id) VALUES ('$matricula', '$nome', '$usuario', '$senha', $turma_id)");
    echo "<script>alert('Aluno cadastrado!');window.location='cadastro_aluno.php';</script>";
}

// Lista de turmas
$turmas = $conn->query("SELECT id, nome FROM turmas");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Cadastrar Aluno</title>
    <link rel="stylesheet" href="css/style.css">
    <script>
    // Função para gerar usuário e senha automaticamente
    function preencherUsuarioSenha() {
        var nome = document.getElementById('nome').value.trim();
        var matricula = document.getElementById('matricula').value.trim();
        var usuario = '';
        if (nome.length > 0) {
            var nomes = nome.split(' ');
            usuario = nomes[0] || '';
            if (nomes.length > 1) usuario += '.' + nomes[1];
            usuario = usuario.toLowerCase().replace(/[^a-z0-9.]/g, '');
            document.getElementById('usuario').value = usuario;
        }
        if (matricula.length > 0) {
            document.getElementById('senha').value = matricula;
        }
    }
    </script>
</head>
<body>
<div class="container">
    <h1>Cadastrar Aluno</h1>
    <form method="post" autocomplete="off">
        <label>Matrícula</label>
        <input type="text" name="matricula" id="matricula" required oninput="preencherUsuarioSenha()">
        <label>Nome Completo</label>
        <input type="text" name="nome" id="nome" required oninput="preencherUsuarioSenha()">
        <label>Usuário</label>
        <input type="text" name="usuario" id="usuario" readonly required>
        <label>Senha</label>
        <input type="text" name="senha" id="senha" readonly required>
        <label>Turma</label>
        <select name="turma_id">
            <?php while($t = $turmas->fetch_assoc()) { ?>
                <option value="<?=$t['id']?>"><?=$t['nome']?></option>
            <?php } ?>
        </select>
        <button type="submit">Salvar</button>
    </form>
    <a href="dashboard_admin.php"><button>Voltar</button></a>
</div>
</body>
</html>