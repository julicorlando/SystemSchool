<?php
session_start();
include "conexao.php";
include_once "funcoes.php";

// Cadastro de administrador (NÃO exige estar logado como admin)
if(isset($_POST['usuario']) && isset($_POST['senha']) && isset($_POST['nome'])) {
    $dados = limpar_entrada($_POST);

    // Validações
    $erros = [];

    if (empty($dados['usuario'])) {
        $erros[] = 'Usuário é obrigatório';
    }

    if (empty($dados['senha'])) {
        $erros[] = 'Senha é obrigatória';
    } elseif (strlen($dados['senha']) < 6) {
        $erros[] = 'Senha deve ter pelo menos 6 caracteres';
    }

    if (empty($dados['nome'])) {
        $erros[] = 'Nome é obrigatório';
    }

    // Verificar se usuário já existe
    if (empty($erros)) {
        $stmt = $conn->prepare("SELECT id FROM admins WHERE usuario = ?");
        $stmt->bind_param("s", $dados['usuario']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $erros[] = 'Usuário já existe';
        }
        $stmt->close();
    }

    if (empty($erros)) {
        try {
            $senha_hash = password_hash($dados['senha'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO admins (usuario, senha, nome) VALUES (?, ?, ?)");
            if (!$stmt) {
                die("Erro na preparação da query: " . $conn->error);
            }
            $stmt->bind_param("sss", 
                $dados['usuario'], 
                $senha_hash,
                $dados['nome']
            );
            if ($stmt->execute()) {
                $sucesso = "Administrador cadastrado com sucesso!";
                $_POST = [];
            } else {
                $erros[] = 'Erro ao cadastrar administrador';
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
    <title>Cadastrar Administrador</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .msg-erro {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            border: 1px solid #f5c6cb;
        }
        .msg-sucesso {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Cadastrar Administrador</h1>
    
    <?php if (!empty($erros)): ?>
        <div class="msg-erro">
            <ul>
                <?php foreach ($erros as $erro): ?>
                    <li><?= htmlspecialchars($erro) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <?php if (isset($sucesso)): ?>
        <div class="msg-sucesso"><?= htmlspecialchars($sucesso) ?></div>
    <?php endif; ?>
    
    <form method="post" autocomplete="off">
        <div>
            <label>Nome Completo *</label>
            <input type="text" name="nome" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
        </div>
        <div>
            <label>Usuário *</label>
            <input type="text" name="usuario" value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>" required>
        </div>
        <div>
            <label>Senha *</label>
            <input type="password" name="senha" required>
            <div style="font-size: 0.9em; color: #666;">Mínimo 6 caracteres</div>
        </div>
        <button type="submit">Salvar</button>
    </form>
    <a href="index.php"><button>Voltar</button></a>
</div>
</body>
</html>