<?php
$host = 'localhost';
$user = 'josdevco_info';
$pass = 'Bento121021@';
$db_name = 'josdevco_info'; // CORREÇÃO: ponto-e-vírgula no final

// Configurações de segurança
$conn = new mysqli($host, $user, $pass, $db_name); // CORREÇÃO: $db_name no lugar de $db

// Verificar conexão
if ($conn->connect_error) {
    die("Erro de conexão: " . $conn->connect_error);
}

// Configurar charset
$conn->set_charset("utf8mb4");

// Incluir funções auxiliares
require_once 'funcoes.php';
?>