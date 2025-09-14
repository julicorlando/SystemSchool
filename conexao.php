<?php
$host = "localhost";
$user = "root";
$pass = "";
$db = "escola";

// Configurações de segurança
$conn = new mysqli($host, $user, $pass, $db);

// Verificar conexão
if ($conn->connect_error) {
    die("Erro de conexão: " . $conn->connect_error);
}

// Configurar charset
$conn->set_charset("utf8mb4");

// Incluir funções auxiliares
require_once 'funcoes.php';
?>