<?php
session_start();
include "conexao.php";
$usuario = $_POST['usuario'];
$senha = $_POST['senha'];
$tipo = $_POST['tipo'];
$tabela = $tipo == "admin" ? "admins" : ($tipo == "professor" ? "professores" : "alunos");
$sql = "SELECT * FROM $tabela WHERE usuario='$usuario' AND senha='$senha'";
$res = $conn->query($sql);
if ($res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $_SESSION['usuario'] = $usuario;
    $_SESSION['tipo'] = $tipo;
    $_SESSION['id'] = $row['id'];
    header("Location: dashboard_{$tipo}.php");
} else {
    echo "<script>alert('Login inválido');window.location='index.php';</script>";
}
?>