<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include "conexao.php";
include_once "funcoes.php";

// Captura e limpa entradas
$usuario = isset($_POST['usuario']) ? limpar_entrada($_POST['usuario']) : '';
$senha   = isset($_POST['senha']) ? $_POST['senha'] : '';

// Validação
if (empty($usuario) || empty($senha)) {
    echo "<script>alert('Preencha todos os campos');window.location='index.php';</script>";
    exit;
}

// Array de tipos/tabelas
$tipos = [
    'admin'     => "SELECT id, usuario, senha, nome FROM admins WHERE usuario = ?",
    'professor' => "SELECT id, usuario, senha, nome FROM professores WHERE usuario = ?",
    'aluno'     => "SELECT id, usuario, senha, nome FROM alunos WHERE usuario = ?"
];

foreach ($tipos as $tipo => $sql) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        // Mostra erro se prepare falhar
        echo "<script>alert('Erro no prepare da tabela $tipo');window.location='index.php';</script>";
        exit;
    }
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $hash = $row['senha'];

        // VERIFICA: hash ou texto puro
        $valid = false;
        if (strlen($hash) > 0 && strpos($hash, '$2y$') === 0) {
            // Senha está em hash
            if (password_verify($senha, $hash)) {
                $valid = true;
            }
        } else {
            // Senha salva em texto puro
            if ($senha === $hash) {
                $valid = true;
            }
        }
        if ($valid) {
            // Login OK
            $_SESSION['usuario'] = $row['usuario'];
            $_SESSION['tipo']    = $tipo;
            $_SESSION['id']      = $row['id'];
            $_SESSION['nome']    = (!empty($row['nome'])) ? $row['nome'] : $row['usuario'];
            $stmt->close();
            $conn->close();
            header("Location: dashboard_{$tipo}.php");
            exit;
        }
    }
    $stmt->close();
}
$conn->close();
echo "<script>alert('Usuário ou senha inválidos');window.location='index.php';</script>";
exit;
?>