<?php
session_start();
include "conexao.php";
if($_SESSION['tipo'] !== "admin") header("Location: index.php");

// Cadastro de professor
if(isset($_POST['nome']) && isset($_POST['usuario']) && isset($_POST['senha'])) {
    $dados = limpar_entrada($_POST);
    
    // Validações
    $erros = [];
    
    if (empty($dados['nome'])) {
        $erros[] = 'Nome é obrigatório';
    }
    
    if (empty($dados['usuario'])) {
        $erros[] = 'Usuário é obrigatório';
    }
    
    if (empty($dados['senha'])) {
        $erros[] = 'Senha é obrigatória';
    } elseif (strlen($dados['senha']) < 6) {
        $erros[] = 'Senha deve ter pelo menos 6 caracteres';
    }
    
    if (!empty($dados['email']) && !validar_email($dados['email'])) {
        $erros[] = 'Email inválido';
    }
    
    // Verificar se usuário já existe
    if (empty($erros)) {
        $stmt = $conn->prepare("SELECT id FROM professores WHERE usuario = ?");
        $stmt->bind_param("s", $dados['usuario']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $erros[] = 'Usuário já existe';
        }
    }
    
    // Verificar se email já existe (se fornecido)
    if (empty($erros) && !empty($dados['email'])) {
        $stmt = $conn->prepare("SELECT id FROM professores WHERE email = ?");
        $stmt->bind_param("s", $dados['email']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $erros[] = 'Email já está em uso';
        }
    }
    
    if (empty($erros)) {
        try {
            $senha_hash = criptografar_senha($dados['senha']);
            $stmt = $conn->prepare("INSERT INTO professores (nome, usuario, senha, email, telefone) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", 
                $dados['nome'], 
                $dados['usuario'], 
                $senha_hash,
                $dados['email'] ?: null,
                $dados['telefone'] ?: null
            );
            
            if ($stmt->execute()) {
                $sucesso = "Professor cadastrado com sucesso!";
                // Limpar campos após sucesso
                $_POST = [];
            } else {
                $erros[] = 'Erro ao cadastrar professor';
            }
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
        .form-row {
            display: flex;
            gap: 15px;
        }
        .form-row > div {
            flex: 1;
        }
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
        .password-help {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }
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
    
    <?php if (isset($sucesso)): ?>
        <div class="msg-sucesso"><?= htmlspecialchars($sucesso) ?></div>
    <?php endif; ?>
    
    <form method="post">
        <div>
            <label>Nome Completo *</label>
            <input type="text" name="nome" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
        </div>
        
        <div class="form-row">
            <div>
                <label>Usuário *</label>
                <input type="text" name="usuario" value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>" required>
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
    
    <a href="dashboard_admin.php"><button>Voltar</button></a>
</div>
</body>
</html>