<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include "conexao.php";

$matricula = $_GET['matricula'] ?? '';
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $matricula = $_POST['matricula'] ?? '';
    $codigo    = $_POST['codigo'] ?? '';
    // Buscar código no banco
    $stmt = $conn->prepare("SELECT codigo_recuperacao FROM alunos WHERE matricula = ?");
    $stmt->bind_param("s", $matricula);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if ($codigo === $row['codigo_recuperacao']) {
            // Redireciona para redefinir senha
            header("Location: redefinir_senha.php?matricula=$matricula");
            exit;
        } else {
            $erro = "Código inválido!";
        }
    } else {
        $erro = "Matrícula não encontrada!";
    }
    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Validar Código - SystemSchool</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div style="max-width:400px;margin:auto;padding-top:40px;">
    <h2>Digite o código recebido por e-mail</h2>
    <?php if ($erro): ?><div style="color:red;font-weight:bold;"><?= $erro ?></div><?php endif; ?>
    <form method="post" action="validar_codigo.php?matricula=<?= htmlspecialchars($matricula) ?>">
        <input type="hidden" name="matricula" value="<?= htmlspecialchars($matricula) ?>">
        <label for="codigo">Código:</label>
        <input type="text" name="codigo" id="codigo" required maxlength="6">
        <button type="submit">Validar</button>
    </form>
</div>
</body>
</html>