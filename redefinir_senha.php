<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include "conexao.php";

$matricula = $_GET['matricula'] ?? '';
$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $matricula  = $_POST['matricula'] ?? '';
    $nova_senha = $_POST['nova_senha'] ?? '';
    $conf_senha = $_POST['conf_senha'] ?? '';

    if (empty($nova_senha) || empty($conf_senha)) {
        $erro = "Preencha todos os campos.";
    } elseif ($nova_senha !== $conf_senha) {
        $erro = "As senhas não conferem.";
    } elseif (strlen($nova_senha) < 6) {
        $erro = "A senha deve ter ao menos 6 caracteres.";
    } else {
        // Salvar a senha pura (SEM HASH) no banco
        $stmt = $conn->prepare("UPDATE alunos SET senha = ?, codigo_recuperacao = NULL WHERE matricula = ?");
        $stmt->bind_param("ss", $nova_senha, $matricula);
        if ($stmt->execute()) {
            $sucesso = "Senha redefinida com sucesso!";
        } else {
            $erro = "Erro ao atualizar senha.";
        }
        $stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Redefinir Senha - SystemSchool</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div style="max-width:400px;margin:auto;padding-top:40px;">
    <h2>Redefinir Senha</h2>
    <?php if ($erro): ?><div style="color:red;font-weight:bold;"><?= $erro ?></div><?php endif; ?>
    <?php if ($sucesso): ?>
        <div style="color:green;font-weight:bold;"><?= $sucesso ?></div>
        <a href="login.php">Ir para login</a>
    <?php else: ?>
        <form method="post" action="redefinir_senha.php?matricula=<?= htmlspecialchars($matricula) ?>">
            <input type="hidden" name="matricula" value="<?= htmlspecialchars($matricula) ?>">
            <label for="nova_senha">Nova senha:</label>
            <input type="password" name="nova_senha" id="nova_senha" required>
            <label for="conf_senha">Confirme a nova senha:</label>
            <input type="password" name="conf_senha" id="conf_senha" required>
            <button type="submit">Redefinir senha</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>