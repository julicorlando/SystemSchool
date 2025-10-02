<?php
session_start();
include "conexao.php";
include_once "funcoes.php";

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$erros = [];
$sucesso = "";
$matricula = "";
$nome = "";
$turma_id_aluno = "";
$cadastro_completo = 0;

// PASSO 1: Solicitar matrícula se ainda não foi informada
if (!isset($_SESSION['matricula']) && !isset($_POST['matricula_busca'])) {
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Auto Cadastro</title>
        <link rel="stylesheet" href="css/style.css">
    </head>
    <body>
    <div class="container">
        <h1>Auto Cadastro</h1>
        <form method="post" autocomplete="off">
            <div>
                <label>Digite sua Matrícula *</label>
                <input type="text" name="matricula_busca" required>
            </div>
            <button type="submit">Buscar</button>
        </form>
        <a href="index.php"><button type="button">Voltar</button></a>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// PASSO 2: Buscar matrícula informada
if (isset($_POST['matricula_busca'])) {
    $matricula = limpar_entrada($_POST['matricula_busca']);

    // Busca nome, turma_id e cadastro_completo
    $stmt = $conn->prepare("SELECT nome, turma_id, cadastro_completo FROM alunos WHERE matricula = ?");
    if (!$stmt) die("Erro ao preparar consulta de matrícula: " . $conn->error);
    $stmt->bind_param("s", $matricula);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows == 0) {
        $erros[] = "Matrícula não encontrada. Procure o setor responsável ou verifique se foi pré-cadastrado.";
    } else {
        $stmt->bind_result($nome, $turma_id_aluno, $cadastro_completo);
        $stmt->fetch();
        // Verifica se o cadastro já foi concluído
        if ($cadastro_completo) {
            ?>
            <!DOCTYPE html>
            <html lang="pt-br">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Cadastro já finalizado</title>
                <link rel="stylesheet" href="css/style.css">
            </head>
            <body>
            <div class="container">
                <h1>Cadastro já finalizado</h1>
                <p>Seu cadastro já foi concluído anteriormente.<br>
                Caso precise atualizar dados, procure a secretaria.</p>
                <a href="index.php"><button type="button">Voltar</button></a>
            </div>
            </body>
            </html>
            <?php
            exit;
        }
        $_SESSION['matricula'] = $matricula;
        $_SESSION['nome'] = $nome;
        $_SESSION['turma_id_aluno'] = $turma_id_aluno;
    }
    $stmt->close();
}

// PASSO 3: Se matrícula válida, liberar cadastro completo
if (isset($_SESSION['matricula'], $_SESSION['nome']) && empty($erros)) {
    $matricula      = $_SESSION['matricula'];
    $nome           = $_SESSION['nome'];
    $turma_id_aluno = $_SESSION['turma_id_aluno'] ?? "";

    // Cadastro de aluno: preencher informações adicionais
    if (isset($_POST['usuario'], $_POST['senha'], $_POST['turma_id'])) {
        $dados = limpar_entrada($_POST);

        // Validações
        if (empty($dados['usuario'])) $erros[] = 'Usuário é obrigatório';
        if (empty($dados['senha'])) $erros[] = 'Senha é obrigatória';
        elseif (strlen($dados['senha']) < 6) $erros[] = 'Senha deve ter pelo menos 6 caracteres';
        if (empty($dados['turma_id'])) $erros[] = 'Turma é obrigatória';
        if (!empty($dados['email']) && !validar_email($dados['email'])) $erros[] = 'Email inválido';

        // Verificar se usuário já existe
        if (empty($erros)) {
            $stmt = $conn->prepare("SELECT id FROM alunos WHERE usuario = ? AND matricula != ?");
            if (!$stmt) die("Erro ao preparar consulta de usuário: " . $conn->error);
            $stmt->bind_param("ss", $dados['usuario'], $matricula);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) $erros[] = 'Usuário já existe';
            $stmt->close();
        }
        // Verificar se email já existe (se fornecido)
        if (empty($erros) && !empty($dados['email'])) {
            $stmt = $conn->prepare("SELECT id FROM alunos WHERE email = ? AND matricula != ?");
            if (!$stmt) die("Erro ao preparar consulta de email: " . $conn->error);
            $stmt->bind_param("ss", $dados['email'], $matricula);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) $erros[] = 'Email já está em uso';
            $stmt->close();
        }

        if (empty($erros)) {
            try {
                // Salva senha em texto puro
                $senha_pura = $dados['senha'];

                // Campos opcionais tratados (null se vazio)
                $email = !empty($dados['email']) ? $dados['email'] : null;
                $telefone = !empty($dados['telefone']) ? $dados['telefone'] : null;
                $endereco = !empty($dados['endereco']) ? $dados['endereco'] : null;

                // Atualiza o registro do aluno já existente e marca cadastro_completo = 1
                $sql = "UPDATE alunos SET usuario = ?, senha = ?, turma_id = ?, email = ?, telefone = ?, endereco = ?, cadastro_completo = 1 WHERE matricula = ?";
                $stmt = $conn->prepare($sql);
                if (!$stmt) die("Erro ao preparar statement de atualização: " . $conn->error . "<br>SQL: " . $sql);

                $stmt->bind_param("ssissss",
                    $dados['usuario'],
                    $senha_pura,
                    $dados['turma_id'],
                    $email,
                    $telefone,
                    $endereco,
                    $matricula
                );

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

    // LISTA DE TURMAS
    $turmas = $conn->query("SELECT id, nome FROM turmas WHERE ativo = 1 ORDER BY nome");
    if (!$turmas || $turmas->num_rows == 0) {
        $turmas = $conn->query("SELECT id, nome FROM turmas ORDER BY nome");
    }

    // Seleção do campo turma (preenchido do banco, ou valor submetido no POST)
    $selectedTurma = $_POST['turma_id'] ?? $turma_id_aluno;

    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Auto Cadastro</title>
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
            var nome = "<?php echo htmlspecialchars($nome); ?>";
            var matricula = "<?php echo htmlspecialchars($matricula); ?>";
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
        window.onload = preencherUsuarioSenha;
        </script>
    </head>
    <body>
    <div class="container">
        <h1>Auto Cadastro</h1>
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
                    <input type="text" name="matricula" value="<?= htmlspecialchars($matricula) ?>" disabled>
                </div>
                <div>
                    <label>Nome Completo *</label>
                    <input type="text" name="nome" value="<?= htmlspecialchars($nome) ?>" disabled>
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
                        <?php while($t = $turmas->fetch_assoc()) {
                            $selected = ($selectedTurma == $t['id']) ? 'selected' : '';
                            echo '<option value="'.intval($t['id']).'" '.$selected.'>'.htmlspecialchars($t['nome']).'</option>';
                        } ?>
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
            <button type="submit">Finalizar Cadastro</button>
        </form>
        <a href="index.php"><button type="button">Voltar</button></a>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// PASSO 4: Matrícula não localizada, exibe erro e campo para buscar novamente
if (!empty($erros)) {
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Auto Cadastro</title>
        <link rel="stylesheet" href="css/style.css">
    </head>
    <body>
    <div class="container">
        <h1>Auto Cadastro</h1>
        <div class="msg-erro">
            <ul style="margin: 0; padding-left: 20px;">
                <?php foreach ($erros as $erro): ?>
                    <li><?= htmlspecialchars($erro) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <form method="post" autocomplete="off">
            <div>
                <label>Digite sua Matrícula *</label>
                <input type="text" name="matricula_busca" required>
            </div>
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