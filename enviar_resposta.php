<?php
session_start();
include "conexao.php";
if($_SESSION['tipo'] !== "aluno") header("Location: index.php");
$id = $_SESSION['id'];
$atividade_id = $_GET['atividade_id'] ?? 0;

// Verifica se já existe resposta para esta atividade/aluno
$resposta_existente = $conn->query("SELECT id FROM respostas WHERE atividade_id=$atividade_id AND aluno_id=$id")->num_rows > 0;

// Envio de resposta
if(!$resposta_existente && isset($_FILES['pdf'])) {
    $arquivo = $_FILES['pdf'];
    if($arquivo['type'] == "application/pdf") {
        $nome_arquivo = uniqid().".pdf";
        move_uploaded_file($arquivo['tmp_name'], "uploads/".$nome_arquivo);
        $conn->query("INSERT INTO respostas (atividade_id, aluno_id, arquivo, data_envio) VALUES ($atividade_id, $id, '$nome_arquivo', NOW())");
        echo "<script>alert('Resposta enviada!');window.location='atividades.php';</script>";
    } else {
        echo "<script>alert('Envie apenas PDF!');window.location='enviar_resposta.php?atividade_id=$atividade_id';</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Enviar Resposta</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container">
    <h1>Enviar Resposta (PDF)</h1>
    <?php if($resposta_existente) { ?>
        <p style="color:red;"><b>Você já enviou uma resposta para esta atividade. Só é permitido um envio.</b></p>
    <?php } else { ?>
        <form method="post" enctype="multipart/form-data">
            <label>Selecione o PDF</label>
            <input type="file" name="pdf" accept="application/pdf" required>
            <button type="submit">Enviar</button>
        </form>
    <?php } ?>
    <a href="atividades.php"><button>Voltar</button></a>
</div>
</body>
</html>