<?php
session_start();
include "conexao.php";
if($_SESSION['tipo'] !== "admin") header("Location: index.php");

// Cadastro de turma
if(isset($_POST['nome']) && isset($_POST['turno']) && isset($_POST['professor_id'])) {
    $nome = $_POST['nome'];
    $turno = $_POST['turno'];
    $prof = $_POST['professor_id'];
    $conn->query("INSERT INTO turmas (nome, turno, professor_id) VALUES ('$nome', '$turno', $prof)");
    echo "<script>alert('Turma cadastrada!');window.location='cadastro_turma.php';</script>";
}

// Lista de professores
$profs = $conn->query("SELECT id, nome FROM professores");

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Cadastrar Turma</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container">
    <h1>Cadastrar Turma</h1>
    <form method="post">
        <label>Nome da turma</label>
        <input type="text" name="nome" required>
        <label>Turno</label>
        <select name="turno">
            <option value="Manhã">Manhã</option>
            <option value="Tarde">Tarde</option>
            <option value="Noite">Noite</option>
        </select>
        <label>Professor</label>
        <select name="professor_id">
            <?php while($p = $profs->fetch_assoc()) { ?>
                <option value="<?=$p['id']?>"><?=$p['nome']?></option>
            <?php } ?>
        </select>
        <button type="submit">Salvar</button>
    </form>
    <a href="dashboard_admin.php"><button>Voltar</button></a>
</div>
</body>
</html>