<?php
session_start();
include "conexao.php";
include_once "funcoes.php"; // Certifique-se que funcoes.php está incluindo password_hash

// Torna o erro visível em ambiente de desenvolvimento
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if($_SESSION['tipo'] !== "admin") { header("Location: index.php"); exit; }

// Inicializa variáveis para evitar warnings
$erros = [];
$sucesso = "";

// Cadastro de aluno
if(isset($_POST['matricula'], $_POST['nome'], $_POST['usuario'], $_POST['senha'], $_POST['turma_id'])) {
    $dados = limpar_entrada($_POST);

    // Validação dos campos obrigatórios
    if (empty($dados['matricula'])) $erros[] = 'Matrícula é obrigatória';
    if (empty($dados['nome'])) $erros[] = 'Nome é obrigatório';
    if (empty($dados['usuario'])) $erros[] = 'Usuário é obrigatório';
    if (empty($dados['senha'])) $erros[] = 'Senha é obrigatória';
    elseif (strlen($dados['senha']) < 6) $erros[] = 'Senha deve ter pelo menos 6 caracteres';
    if (empty($dados['turma_id'])) $erros[] = 'Turma é obrigatória';
    if (!empty($dados['email']) && !validar_email($dados['email'])) $erros[] = 'Email inválido';

    // Verificar se matrícula já existe
    if (empty($erros)) {
        $stmt = $conn->prepare("SELECT id FROM alunos WHERE matricula = ?");
        if (!$stmt) die("Erro ao preparar consulta de matrícula: " . $conn->error);
        $stmt->bind_param("s", $dados['matricula']);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) $erros[] = 'Matrícula já existe';
        $stmt->close();
    }
    // Verificar se usuário já existe
    if (empty($erros)) {
        $stmt = $conn->prepare("SELECT id FROM alunos WHERE usuario = ?");
        if (!$stmt) die("Erro ao preparar consulta de usuário: " . $conn->error);
        $stmt->bind_param("s", $dados['usuario']);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) $erros[] = 'Usuário já existe';
        $stmt->close();
    }
    // Verificar se email já existe (se fornecido)
    if (empty($erros) && !empty($dados['email'])) {
        $stmt = $conn->prepare("SELECT id FROM alunos WHERE email = ?");
        if (!$stmt) die("Erro ao preparar consulta de email: " . $conn->error);
        $stmt->bind_param("s", $dados['email']);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) $erros[] = 'Email já está em uso';
        $stmt->close();
    }

    if (empty($erros)) {
        try {
            $senha_hash = password_hash($dados['senha'], PASSWORD_DEFAULT);

            // Campos opcionais tratados (null se vazio)
            $email = !empty($dados['email']) ? $dados['email'] : null;
            $telefone = !empty($dados['telefone']) ? $dados['telefone'] : null;
            $endereco = !empty($dados['endereco']) ? $dados['endereco'] : null;

            $sql = "INSERT INTO alunos (matricula, nome, usuario, senha, turma_id, email, telefone, endereco) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) die("Erro ao preparar statement de inserção: " . $conn->error . "<br>SQL: " . $sql);

            $stmt->bind_param("ssssisss", 
                $dados['matricula'], 
                $dados['nome'], 
                $dados['usuario'], 
                $senha_hash,
                $dados['turma_id'],
                $email,
                $telefone,
                $endereco
            );
            
            if ($stmt->execute()) {
                $sucesso = "Aluno cadastrado com sucesso!";
                $_POST = [];
            } else {
                $erros[] = 'Erro ao cadastrar aluno: ' . $stmt->error;
            }
            $stmt->close();
        } catch (Exception $e) {
            $erros[] = 'Erro interno: ' . $e->getMessage();
        }
    }
}

// LISTA DE TURMAS - Exibe todas as turmas ativas, se não houver, exibe todas
$turmas = $conn->query("SELECT id, nome FROM turmas WHERE ativo = 1 ORDER BY nome");
if (!$turmas || $turmas->num_rows == 0) {
    $turmas = $conn->query("SELECT id, nome FROM turmas ORDER BY nome");
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Aluno</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .form-row { display: flex; gap: 15px; }
        .form-row > div { flex: 1; }
        .msg-erro { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin: 10px 0; border: 1px solid #f5c6cb; }
        .msg-sucesso { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin: 10px 0; border: 1px solid #c3e6cb; }
        .auto-fill-help { font-size: 0.9em; color: #666; margin-top: 5px; }
    </style>
    <script>
    function preencherUsuarioSenha() {
        var nome = document.getElementById('nome').value.trim();
        var matricula = document.getElementById('matricula').value.trim();
        var usuario = '';
        if (nome.length > 0) {
            var nomes = nome.split(' ');
            usuario = nomes[0] || '';
            if (nomes.length > 1) usuario += '.' + nomes[1];
            usuario = usuario.toLowerCase().replace(/[^a-z0-9.]/g, '');
            document.getElementById('usuario').value = usuario;
        }
        if (matricula.length > 0) {
            document.getElementById('senha').value = matricula;
        }
    }
    function gerarSenhaAleatoria() {
        var chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        var senha = '';
        for (var i = 0; i < 8; i++) senha += chars.charAt(Math.floor(Math.random() * chars.length));
        document.getElementById('senha').value = senha;
    }
    </script>
</head>
<body>
<div class="container">
    <h1>Cadastrar Aluno</h1>
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
        <div class="form-row">
            <div>
                <label>Matrícula *</label>
                <input type="text" name="matricula" id="matricula" value="<?= htmlspecialchars($_POST['matricula'] ?? '') ?>" required oninput="preencherUsuarioSenha()">
            </div>
            <div>
                <label>Nome Completo *</label>
                <input type="text" name="nome" id="nome" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required oninput="preencherUsuarioSenha()">
            </div>
        </div>
        <div class="form-row">
            <div>
                <label>Usuário *</label>
                <input type="text" name="usuario" id="usuario" value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>" required>
                <div class="auto-fill-help">Preenchido automaticamente baseado no nome</div>
            </div>
            <div>
                <label>Senha *</label>
                <div style="display: flex; gap: 5px;">
                    <input type="text" name="senha" id="senha" value="<?= htmlspecialchars($_POST['senha'] ?? '') ?>" required style="flex: 1;">
                    <button type="button" onclick="gerarSenhaAleatoria()" style="width: auto; padding: 5px 10px;">Gerar</button>
                </div>
                <div class="auto-fill-help">Padrão: matrícula | Ou clique em "Gerar"</div>
            </div>
        </div>
        <div>
            <label>Turma *</label>
            <select name="turma_id" required>
                <option value="">Selecione uma turma</option>
                <?php if ($turmas && $turmas->num_rows > 0): ?>
                    <?php while($t = $turmas->fetch_assoc()) { ?>
                        <option value="<?= $t['id'] ?>" <?= (($_POST['turma_id'] ?? '') == $t['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['nome']) ?>
                        </option>
                    <?php } ?>
                <?php else: ?>
                    <option value="" disabled>Nenhuma turma disponível</option>
                <?php endif; ?>
            </select>
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
        <div>
            <label>Endereço</label>
            <textarea name="endereco" rows="3" placeholder="Endereço completo (opcional)"><?= htmlspecialchars($_POST['endereco'] ?? '') ?></textarea>
        </div>
        <button type="submit">Salvar</button>
    </form>
    <a href="dashboard_admin.php"><button type="button">Voltar</button></a>
</div>
</body>
</html>