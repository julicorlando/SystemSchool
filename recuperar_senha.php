<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'PHPMailer-6.11.1/src/PHPMailer.php';
require 'PHPMailer-6.11.1/src/SMTP.php';
require 'PHPMailer-6.11.1/src/Exception.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function gerarCodigo($length = 6) {
    return str_pad(random_int(0, pow(10, $length)-1), $length, '0', STR_PAD_LEFT);
}

function enviarEmailCodigo($email, $nome, $codigo) {
    $mail = new PHPMailer(true);
    try {
        $mail->CharSet = 'UTF-8'; // Garante acentuação
        $mail->isSMTP();
        $mail->Host       = 'mail.josdev.com.br';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'noreply@josdev.com.br';
        $mail->Password   = 'Bento121021@';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        // Para diagnóstico, descomente para ver detalhes do SMTP
        // $mail->SMTPDebug = 2;

        $mail->setFrom('noreply@josdev.com.br', 'SystemSchool');
        $mail->addAddress($email, $nome);

        $mail->isHTML(false);
        $mail->Subject = "Código de verificação - SystemSchool";
        $mail->Body    = "Olá $nome,\n\nSeu código de verificação para recuperação de senha é:\n\n$codigo\n\nDigite esse código na página para continuar.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Erro ao enviar email: {$mail->ErrorInfo}");
        // Exibe o erro na tela para diagnóstico
        echo "<script>alert('Erro ao enviar e-mail: " . addslashes($mail->ErrorInfo) . "');window.location='recuperar_senha.php';</script>";
        return false;
    }
}

include "conexao.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['matricula'])) {
    $matricula = $_POST['matricula'];
    $stmt = $conn->prepare("SELECT nome, email FROM alunos WHERE matricula = ?");
    $stmt->bind_param("s", $matricula);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $nome  = $row['nome'];
        $email = $row['email'];

        // Gerar código e salvar no banco
        $codigo = gerarCodigo();
        $stmt2 = $conn->prepare("UPDATE alunos SET codigo_recuperacao=? WHERE matricula=?");
        $stmt2->bind_param("ss", $codigo, $matricula);
        $stmt2->execute();
        $stmt2->close();

        // Enviar o código por e-mail
        if (enviarEmailCodigo($email, $nome, $codigo)) {
            header("Location: validar_codigo.php?matricula=$matricula");
            exit;
        }
        // O erro já é tratado na função enviarEmailCodigo
    } else {
        echo "<script>alert('Matrícula não encontrada!');window.location='recuperar_senha.php';</script>";
    }
    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Recuperação de Senha</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div style="max-width:400px;margin:auto;padding-top:40px;">
    <h2>Recuperação de Senha</h2>
    <form method="post" action="recuperar_senha.php" autocomplete="off">
        <label for="matricula">Informe sua matrícula:</label>
        <input type="text" name="matricula" id="matricula" required>
        <button type="submit">Receber código por e-mail</button>
    </form>
</div>
</body>
</html>