<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'PHPMailer-6.11.1/src/PHPMailer.php';
require 'PHPMailer-6.11.1/src/SMTP.php';
require 'PHPMailer-6.11.1/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.josdev.com.br';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'noreply@josdev.com.br';
    $mail->Password   = 'Bento121021@';
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;
    $mail->SMTPDebug  = 2; // Ativa debug

    $mail->setFrom('noreply@josdev.com.br', 'Test');
    $mail->addAddress('jos121021@gmail.com', 'Julio');

    $mail->isHTML(true);
    $mail->Subject = 'Teste PHPMailer';
    $mail->Body    = 'Teste de envio de email com PHPMailer.';

    $mail->send();
    echo 'Email enviado com sucesso!';
} catch (Exception $e) {
    echo "Erro ao enviar email: {$mail->ErrorInfo}";
}
?>