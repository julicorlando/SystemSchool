<?php
session_start();
include "conexao.php";

$usuario = limpar_entrada($_POST['usuario'] ?? '');
$senha = $_POST['senha'] ?? '';
$tipo = $_POST['tipo'] ?? '';

if (empty($usuario) || empty($senha) || empty($tipo)) {
    echo "<script>alert('Preencha todos os campos');window.location='index.php';</script>";
    exit;
}

$tabela = '';
switch($tipo) {
    case 'admin':
        $tabela = 'admins';
        break;
    case 'professor':
        $tabela = 'professores';
        break;
    case 'aluno':
        $tabela = 'alunos';
        break;
    default:
        echo "<script>alert('Tipo de usuário inválido');window.location='index.php';</script>";
        exit;
}

// Usar prepared statement para segurança
$stmt = $conn->prepare("SELECT id, nome, senha FROM $tabela WHERE usuario = ?");
$stmt->bind_param("s", $usuario);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    
    // Verificar se a senha é hash ou texto puro (para compatibilidade)
    $senha_valida = false;
    if (password_get_info($row['senha'])['algo']) {
        // Senha criptografada
        $senha_valida = verificar_senha($senha, $row['senha']);
    } else {
        // Senha em texto puro (sistema antigo)
        $senha_valida = ($senha === $row['senha']);
        
        // Atualizar para senha criptografada
        if ($senha_valida) {
            $nova_senha = criptografar_senha($senha);
            $update_stmt = $conn->prepare("UPDATE $tabela SET senha = ? WHERE id = ?");
            $update_stmt->bind_param("si", $nova_senha, $row['id']);
            $update_stmt->execute();
        }
    }
    
    if ($senha_valida) {
        $_SESSION['usuario'] = $usuario;
        $_SESSION['tipo'] = $tipo;
        $_SESSION['id'] = $row['id'];
        $_SESSION['nome'] = $row['nome'];
        
        // Log de acesso (opcional - pode ser implementado depois)
        // log_acesso($conn, $row['id'], $tipo);
        
        header("Location: dashboard_{$tipo}.php");
        exit;
    }
}

echo "<script>alert('Login inválido');window.location='index.php';</script>";
?>