<?php
require 'PHPMailer-6.11.1/src/PHPMailer.php';
require 'PHPMailer-6.11.1/src/SMTP.php';
require 'PHPMailer-6.11.1/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function gerarToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function enviarEmailRecuperacao($email, $nome, $link) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'mail.josdev.com.br'; // Confirmado pelo MXToolbox
        $mail->SMTPAuth   = true;
        $mail->Username   = 'noreply@josdev.com.br';
        $mail->Password   = 'Bento121021@';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Ou 'ssl'
        $mail->Port       = 465;

        $mail->setFrom('noreply@josdev.com.br', 'SystemSchool');
        $mail->addAddress($email, $nome);

        $mail->isHTML(false);
        $mail->Subject = "Recuperação de senha - SystemSchool";
        $mail->Body    = "Olá $nome,\n\nRecebemos uma solicitação para redefinir sua senha.\nPara continuar, clique no link abaixo:\n$link\n\nSe não foi você, ignore este email.";

        // Ative o debug para diagnóstico
        $mail->SMTPDebug = 2;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Erro ao enviar email: {$mail->ErrorInfo}");
        echo "Erro ao enviar email: {$mail->ErrorInfo}";
        return false;
    }
}
?>