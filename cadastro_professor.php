<?php
session_start();
include "conexao.php";
include_once "funcoes.php";

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$erros = [];
$sucesso = "";
$tipo_cadastro = $_GET['tipo'] ?? 'aluno'; // 'aluno' ou 'professor'
$nome = "";
$matricula = "";
$turma_id = "";
$cadastro_completo = 0;

// PASSO 1: Solicitar matrícula ou nome
if ($tipo_cadastro === 'aluno' && !isset($_SESSION['matricula']) && !isset($_POST['matricula_busca'])) {
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <title>Cadastro de Aluno</title>
        <link rel="stylesheet" href="css/style.css">
    </head>
    <body>
    <div class="container">
        <h1>Cadastro de Aluno</h1>
        <form method="post" autocomplete="off">
            <label>Matrícula *</label>
            <input type="text" name="matricula_busca" required>
            <button type="submit">Buscar</button>
        </form>
        <a href="index.php"><button type="button">Voltar</button></a>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// Para professor, busca por nome
if ($tipo_cadastro === 'professor' && !isset($_SESSION['nome']) && !isset($_POST['nome_busca'])) {
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <title>Cadastro de Professor</title>
        <link rel="stylesheet" href="css/style.css">
    </head>
    <body>
    <div class="container">
        <h1>Cadastro de Professor</h1>
        <form method="post" autocomplete="off">
            <label>Nome Completo *</label>
            <input type="text" name="nome_busca" required>
            <button type="submit">Buscar</button>
        </form>
        <a href="index.php"><button type="button">Voltar</button></a>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// PASSO 2: Buscar aluno ou professor
if ($tipo_cadastro === 'aluno' && isset($_POST['matricula_busca'])) {
    $matricula = limpar_entrada($_POST['matricula_busca']);
    $stmt = $conn->prepare("SELECT nome, turma_id, cadastro_completo FROM alunos WHERE matricula = ?");
    if (!$stmt) die("Erro ao preparar consulta: " . $conn->error);
    $stmt->bind_param("s", $matricula);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows == 0) {
        $erros[] = "Matrícula não encontrada.";
    } else {
        $stmt->bind_result($nome, $turma_id, $cadastro_completo);
        $stmt->fetch();
        if ($cadastro_completo) {
            echo "<div class='container'><h1>Cadastro já finalizado</h1><p>Procure a secretaria para atualizar dados.</p></div>"; exit;
        }
        $_SESSION['matricula'] = $matricula;
        $_SESSION['nome'] = $nome;
        $_SESSION['turma_id'] = $turma_id;
    }
    $stmt->close();
}

if ($tipo_cadastro === 'professor' && isset($_POST['nome_busca'])) {
    $nome_busca = limpar_entrada($_POST['nome_busca']);
    $stmt = $conn->prepare("SELECT id, nome FROM professores WHERE nome = ?");
    if (!$stmt) die("Erro ao preparar consulta: " . $conn->error);
    $stmt->bind_param("s", $nome_busca);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows == 0) {
        $erros[] = "Nome não encontrado.";
    } else {
        $stmt->bind_result($prof_id, $nome);
        $stmt->fetch();
        $_SESSION['prof_id'] = $prof_id;
        $_SESSION['nome'] = $nome;
    }
    $stmt->close();
}

// PASSO 3: Cadastro de dados
if (($tipo_cadastro === 'aluno' && isset($_SESSION['matricula'], $_SESSION['nome'])) ||
    ($tipo_cadastro === 'professor' && isset($_SESSION['prof_id'], $_SESSION['nome']))) {

    if (isset($_POST['usuario'], $_POST['senha'])) {
        $dados = limpar_entrada($_POST);

        if (empty($dados['usuario'])) $erros[] = 'Usuário é obrigatório';
        if (empty($dados['senha'])) $erros[] = 'Senha é obrigatória';
        elseif (strlen($dados['senha']) < 6) $erros[] = 'Senha deve ter pelo menos 6 caracteres';

        // Checa se usuário já existe
        if (empty($erros)) {
            if ($tipo_cadastro === 'aluno') {
                $stmt = $conn->prepare("SELECT id FROM alunos WHERE usuario = ? AND matricula != ?");
                $stmt->bind_param("ss", $dados['usuario'], $_SESSION['matricula']);
            } else {
                $stmt = $conn->prepare("SELECT id FROM professores WHERE usuario = ? AND id != ?");
                $stmt->bind_param("si", $dados['usuario'], $_SESSION['prof_id']);
            }
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) $erros[] = 'Usuário já existe';
            $stmt->close();
        }

        if (empty($erros)) {
            try {
                // Salva senha pura
                $senha_pura = $dados['senha'];
                if ($tipo_cadastro === 'aluno') {
                    $turma_id = $_SESSION['turma_id'];
                    $sql = "UPDATE alunos SET usuario = ?, senha = ?, turma_id = ?, cadastro_completo = 1 WHERE matricula = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssis", $dados['usuario'], $senha_pura, $turma_id, $_SESSION['matricula']);
                } else {
                    $sql = "UPDATE professores SET usuario = ?, senha = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssi", $dados['usuario'], $senha_pura, $_SESSION['prof_id']);
                }
                if ($stmt->execute()) {
                    $sucesso = "Cadastro concluído com sucesso!";
                    $_POST = [];
                    session_unset();
                } else {
                    $erros[] = 'Erro ao atualizar cadastro: ' . $stmt->error;
                }
                $stmt->close();
            } catch (Exception $e) {
                $erros[] = 'Erro interno: ' . $e->getMessage();
            }
        }
    }

    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <title>Cadastro <?= $tipo_cadastro === 'aluno' ? 'de Aluno' : 'de Professor' ?></title>
        <link rel="stylesheet" href="css/style.css">
        <style>
            .msg-erro { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin: 10px 0; border: 1px solid #f5c6cb; }
            .msg-sucesso { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin: 10px 0; border: 1px solid #c3e6cb; }
        </style>
    </head>
    <body>
    <div class="container">
        <h1>Cadastro <?= $tipo_cadastro === 'aluno' ? 'de Aluno' : 'de Professor' ?></h1>
        <?php if (!empty($erros)): ?>
            <div class="msg-erro"><ul><?php foreach ($erros as $erro): ?><li><?= htmlspecialchars($erro) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <?php if (!empty($sucesso)): ?>
            <div class="msg-sucesso"><?= htmlspecialchars($sucesso) ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off">
            <?php if ($tipo_cadastro === 'aluno'): ?>
                <label>Matrícula *</label>
                <input type="text" name="matricula" value="<?= htmlspecialchars($_SESSION['matricula']) ?>" disabled>
                <label>Nome *</label>
                <input type="text" name="nome" value="<?= htmlspecialchars($_SESSION['nome']) ?>" disabled>
            <?php else: ?>
                <label>Nome *</label>
                <input type="text" name="nome" value="<?= htmlspecialchars($_SESSION['nome']) ?>" disabled>
            <?php endif; ?>
            <label>Usuário *</label>
            <input type="text" name="usuario" value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>" required>
            <label>Senha *</label>
            <input type="text" name="senha" value="<?= htmlspecialchars($_POST['senha'] ?? '') ?>" required>
            <button type="submit">Finalizar Cadastro</button>
        </form>
        <a href="index.php"><button type="button">Voltar</button></a>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// PASSO 4: Matrícula/nome não localizada, exibe erro
if (!empty($erros)) {
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <title>Cadastro <?= $tipo_cadastro === 'aluno' ? 'de Aluno' : 'de Professor' ?></title>
        <link rel="stylesheet" href="css/style.css">
    </head>
    <body>
    <div class="container">
        <h1>Cadastro <?= $tipo_cadastro === 'aluno' ? 'de Aluno' : 'de Professor' ?></h1>
        <div class="msg-erro"><ul><?php foreach ($erros as $erro): ?><li><?= htmlspecialchars($erro) ?></li><?php endforeach; ?></ul></div>
        <form method="post" autocomplete="off">
            <?php if ($tipo_cadastro === 'aluno'): ?>
                <label>Matrícula *</label>
                <input type="text" name="matricula_busca" required>
            <?php else: ?>
                <label>Nome Completo *</label>
                <input type="text" name="nome_busca" required>
            <?php endif; ?>
            <button type="submit">Buscar</button>
        </form>
        <a href="index.php"><button type="button">Voltar</button></a>
    </div>
    </body>
    </html>
    <?php
    exit;
}
?>