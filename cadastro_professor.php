<?php
session_start();
include "conexao.php";
include_once "funcoes.php"; // Certifique-se que funcoes.php inclui a função limpar_entrada e validar_email

if (!isset($_SESSION['tipo']) || $_SESSION['tipo'] !== "admin") {
    header("Location: index.php");
    exit;
}

// Função para gerar usuário instantaneamente com base no nome
function gerar_usuario($nome) {
    $nome = strtolower(trim($nome));
    $nome = preg_replace('/[^a-z0-9 ]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $nome));
    $partes = explode(' ', $nome);
    $usuario = $partes[0];
    if (count($partes) > 1) {
        $usuario .= '.' . end($partes);
    }
    return $usuario;
}

// Inicializa variáveis para feedback
$erros = [];
$sucesso = "";

// Cadastro de professor
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dados = limpar_entrada($_POST);

    // Validações
    if (empty($dados['nome'])) {
        $erros[] = 'Nome é obrigatório';
    }

    // Gera usuário instantaneamente SEM opção de digitar
    if (!empty($dados['nome'])) {
        $dados['usuario'] = gerar_usuario($dados['nome']);
        // Garante que o usuário é único (adiciona número se já existe)
        $base_usuario = $dados['usuario'];
        $count = 1;
        $stmt = $conn->prepare("SELECT id FROM professores WHERE usuario = ?");
        $stmt->bind_param("s", $dados['usuario']);
        $stmt->execute();
        $stmt->store_result();
        while ($stmt->num_rows > 0) {
            $dados['usuario'] = $base_usuario . $count;
            $stmt->bind_param("s", $dados['usuario']);
            $stmt->execute();
            $stmt->store_result();
            $count++;
        }
        $stmt->close();
    } else {
        $dados['usuario'] = '';
    }

    if (empty($dados['usuario'])) {
        $erros[] = 'Usuário não pôde ser gerado. Verifique o nome.';
    }

    if (empty($dados['senha'])) {
        $erros[] = 'Senha é obrigatória';
    } elseif (strlen($dados['senha']) < 6) {
        $erros[] = 'Senha deve ter pelo menos 6 caracteres';
    }

    if (!empty($dados['email']) && !validar_email($dados['email'])) {
        $erros[] = 'Email inválido';
    }

    // Verificar se email já existe (se fornecido)
    if (empty($erros) && !empty($dados['email'])) {
        $stmt = $conn->prepare("SELECT id FROM professores WHERE email = ?");
        $stmt->bind_param("s", $dados['email']);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $erros[] = 'Email já está em uso';
        }
        $stmt->close();
    }

    // Cadastrar professor
    if (empty($erros)) {
        try {
            // Senha armazenada em texto puro (NÃO hash)
            $senha_pura = $dados['senha'];

            // Garante valores nulos para campos opcionais
            $email = !empty($dados['email']) ? $dados['email'] : null;
            $telefone = !empty($dados['telefone']) ? $dados['telefone'] : null;

            $stmt = $conn->prepare(
                "INSERT INTO professores (nome, usuario, senha, email, telefone) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "sssss",
                $dados['nome'],
                $dados['usuario'],
                $senha_pura,
                $email,
                $telefone
            );

            if ($stmt->execute()) {
                $sucesso = "Professor cadastrado com sucesso! Usuário gerado: <strong>" . htmlspecialchars($dados['usuario']) . "</strong>";
                $_POST = [];
            } else {
                $erros[] = 'Erro ao cadastrar professor: ' . $stmt->error;
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Professor</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .form-row { display: flex; gap: 15px; }
        .form-row > div { flex: 1; }
        .msg-erro {
            background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin: 10px 0; border: 1px solid #f5c6cb;
        }
        .msg-sucesso {
            background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin: 10px 0; border: 1px solid #c3e6cb;
        }
        .password-help { font-size: 0.9em; color: #666; margin-top: 5px; }
    </style>
</head>
<body>
<div class="container">
    <h1>Cadastrar Professor</h1>

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
        <div class="msg-sucesso"><?= $sucesso ?></div>
    <?php endif; ?>

    <form method="post">
        <div>
            <label>Nome Completo *</label>
            <input type="text" name="nome" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
        </div>

        <div class="form-row">
            <div>
                <label>Usuário (gerado automaticamente)</label>
                <input type="text" name="usuario" value="<?= !empty($_POST['nome']) ? htmlspecialchars(gerar_usuario($_POST['nome'])) : '' ?>" readonly>
            </div>
            <div>
                <label>Senha *</label>
                <input type="password" name="senha" required>
                <div class="password-help">Mínimo 6 caracteres</div>
            </div>
        </div>

        <div class="form-row">
            <div>
                <label>Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="email@exemplo.com">
            </div>
            <div>
                <label>Telefone</label>
                <input type="text" name="telefone" value="<?= htmlspecialchars($_POST['telefone'] ?? '') ?>" placeholder="(11) 99999-9999">
            </div>
        </div>

        <button type="submit">Salvar</button>
    </form>

    <a href="dashboard_admin.php"><button type="button">Voltar</button></a>
</div>
<script>
document.querySelector('input[name="nome"]').addEventListener('input', function() {
    var nome = this.value.trim().toLowerCase();
    nome = nome.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
    nome = nome.replace(/[^a-z0-9 ]/g, "");
    var partes = nome.split(" ");
    var usuario = partes[0];
    if (partes.length > 1) usuario += "." + partes[partes.length-1];
    document.querySelector('input[name="usuario"]').value = usuario;
});
</script>
</body>
</html>