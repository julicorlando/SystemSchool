<?php
session_start();
include "conexao.php";
include_once "funcoes.php";

if (!isset($_SESSION['tipo']) || $_SESSION['tipo'] !== "professor") {
    header("Location: index.php");
    exit;
}

$professor_id = $_SESSION['id'];

// Busca turmas do professor que estão liberadas para cadastro de alunos
$turmas = $conn->query("SELECT id, nome, professor_pode_cadastrar FROM turmas WHERE professor_id=$professor_id AND finalizada=0");

// Processa cadastro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $turma_id = intval($_POST['turma_id'] ?? 0);
    $nome = limpar_entrada($_POST['nome'] ?? '');
    $usuario = limpar_entrada($_POST['usuario'] ?? '');
    $matricula = limpar_entrada($_POST['matricula'] ?? '');
    $senha = criptografar_senha($_POST['senha'] ?? '');

    // Checa se o professor pode cadastrar aluno nessa turma
    $turma = $conn->query("SELECT professor_pode_cadastrar FROM turmas WHERE id=$turma_id AND professor_id=$professor_id")->fetch_assoc();
    if ($turma && $turma['professor_pode_cadastrar']) {
        // Verifica se já existe o usuário ou matrícula
        $ja_existe = $conn->query("SELECT id FROM alunos WHERE usuario='$usuario' OR matricula='$matricula'")->num_rows > 0;
        if ($ja_existe) {
            $msg_erro = "Já existe um aluno com esse usuário ou matrícula.";
        } else {
            $stmt = $conn->prepare("INSERT INTO alunos (nome, usuario, matricula, senha, turma_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssi", $nome, $usuario, $matricula, $senha, $turma_id);
            if ($stmt->execute()) {
                $msg_sucesso = "Aluno cadastrado com sucesso!";
            } else {
                $msg_erro = "Erro ao cadastrar aluno.";
            }
            $stmt->close();
        }
    } else {
        $msg_erro = "Cadastro de alunos bloqueado para esta turma pelo administrador.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Cadastrar Aluno (Professor)</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container">
    <h1>Cadastrar Aluno</h1>
    <?php if (isset($msg_sucesso)): ?>
        <div style="background:#d4edda;color:#155724;padding:10px;border-radius:4px;margin-bottom:20px;">
            <?= $msg_sucesso ?>
        </div>
    <?php endif; ?>
    <?php if (isset($msg_erro)): ?>
        <div style="background:#f8d7da;color:#721c24;padding:10px;border-radius:4px;margin-bottom:20px;">
            <?= $msg_erro ?>
        </div>
    <?php endif; ?>
    <?php if ($turmas && $turmas->num_rows > 0): ?>
        <form method="post">
            <label>Turma</label>
            <select name="turma_id" required>
                <option value="">Selecione uma turma</option>
                <?php while ($turma = $turmas->fetch_assoc()): ?>
                    <?php if ($turma['professor_pode_cadastrar']): ?>
                        <option value="<?= $turma['id'] ?>"><?= htmlspecialchars($turma['nome']) ?></option>
                    <?php endif; ?>
                <?php endwhile; ?>
            </select>
            <label>Nome Completo</label>
            <input type="text" name="nome" required>
            <label>Usuário</label>
            <input type="text" name="usuario" required>
            <label>Matrícula</label>
            <input type="text" name="matricula" required>
            <label>Senha</label>
            <input type="password" name="senha" required>
            <button type="submit">Cadastrar Aluno</button>
        </form>
    <?php else: ?>
        <p>Você não possui turmas liberadas para cadastro de alunos.<br>
        Solicite ao administrador a liberação dessa função.</p>
    <?php endif; ?>
    <a href="dashboard_professor.php"><button>Voltar</button></a>
</div>
</body>
</html>