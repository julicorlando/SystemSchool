<?php
session_start();
include "conexao.php";
include_once "funcoes.php";

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Somente admin pode acessar
if (!isset($_SESSION['tipo']) || $_SESSION['tipo'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$erros = [];
$sucesso = "";

// Buscar lista de turmas
$turmas = $conn->query("SELECT id, nome FROM turmas WHERE ativo = 1 ORDER BY nome");
if (!$turmas || $turmas->num_rows == 0) {
    $turmas = $conn->query("SELECT id, nome FROM turmas ORDER BY nome");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dados = limpar_entrada($_POST);

    // Matrícula e nome obrigatórios
    if (empty($dados['matricula'])) $erros[] = 'Matrícula é obrigatória';
    if (empty($dados['nome'])) $erros[] = 'Nome é obrigatório';

    // Turma: converte para inteiro e verifica se é maior que zero
    $turma_id = isset($dados['turma_id']) ? intval($dados['turma_id']) : 0;
    if ($turma_id < 1) {
        $erros[] = 'Turma é obrigatória';
    }

    // Verificar se matrícula já existe
    if (empty($erros)) {
        $stmt = $conn->prepare("SELECT id FROM alunos WHERE matricula = ?");
        if (!$stmt) $erros[] = "Erro ao preparar consulta de matrícula: " . $conn->error;
        else {
            $stmt->bind_param("s", $dados['matricula']);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) $erros[] = 'Matrícula já existe';
            $stmt->close();
        }
    }

    // Se não houver erros, insere no banco
    if (empty($erros)) {
        $sql = "INSERT INTO alunos (matricula, nome, turma_id) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) $erros[] = "Erro ao preparar statement de inserção: " . $conn->error;
        else {
            $stmt->bind_param("ssi", $dados['matricula'], $dados['nome'], $turma_id);
            if ($stmt->execute()) {
                $sucesso = "Aluno pré-cadastrado com sucesso!";
                $_POST = [];
            } else {
                $erros[] = 'Erro ao inserir aluno: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pré-cadastro de Aluno</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container">
    <h1>Pré-cadastro de Aluno (admin)</h1>
    <?php if (!empty($erros)): ?>
        <div class="msg-erro">
            <ul style="margin: 0; padding-left: 20px;">
                <?php foreach ($erros as $erro): ?>
                    <li><?= htmlspecialchars($erro) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <?php if (!empty($sucesso)): ?>
        <div class="msg-sucesso"><?= htmlspecialchars($sucesso) ?></div>
    <?php endif; ?>
    <form method="post" autocomplete="off">
        <div>
            <label>Matrícula *</label>
            <input type="text" name="matricula" required value="<?= htmlspecialchars($_POST['matricula'] ?? '') ?>">
        </div>
        <div>
            <label>Nome Completo *</label>
            <input type="text" name="nome" required value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>">
        </div>
        <div>
            <label>Turma *</label>
            <select name="turma_id" required>
                <option value="0">Selecione uma turma</option>
                <?php
                $selectedTurma = $_POST['turma_id'] ?? '';
                if ($turmas && $turmas->num_rows > 0):
                    while($t = $turmas->fetch_assoc()) {
                        $selected = ($selectedTurma == $t['id']) ? 'selected' : '';
                        echo '<option value="'.intval($t['id']).'" '.$selected.'>'.htmlspecialchars($t['nome']).'</option>';
                    }
                else:
                ?>
                    <option value="0" disabled>Nenhuma turma disponível</option>
                <?php endif; ?>
            </select>
        </div>
        <button type="submit">Pré-cadastrar</button>
    </form>
    <a href="dashboard_admin.php"><button type="button">Voltar</button></a>
</div>
</body>
</html>