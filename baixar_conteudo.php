<?php
session_start();
include "conexao.php";
$id = $_SESSION['id'];
$atividade_id = intval($_GET['atividade_id'] ?? 0);

if(!$id || !$atividade_id) die("Acesso inválido");

// Busca o arquivo
$atv = $conn->query("SELECT arquivo FROM atividades WHERE id=$atividade_id")->fetch_assoc();
if(!$atv || !$atv['arquivo'] || !file_exists("uploads/".$atv['arquivo'])) die("Arquivo não encontrado");

// Registra o acesso
$conn->query("INSERT INTO conteudo_acesso (aluno_id, atividade_id) VALUES ($id, $atividade_id)");

// Faz download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="'.$atv['arquivo'].'"');
readfile("uploads/".$atv['arquivo']);
exit;
?>