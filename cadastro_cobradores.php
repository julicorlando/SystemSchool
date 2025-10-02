<?php
// cadastro_cobradores.php
// Página para cadastrar, editar, listar, resetar senha e excluir cobradores.
// Requer: conexao.php (define $conn) e funcoes.php (opcional: limpar_entrada, gerar_senha, formatar_data_br)
// Permissão: somente admin

session_start();
include "conexao.php";
include_once "funcoes.php";

// Exibir erros em ambiente de desenvolvimento (remova em produção)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Verifica permissões (apenas admin)
if (!isset($_SESSION['tipo']) || $_SESSION['tipo'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// CSRF token
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_token'];

// Flash messages
$msg_sucesso = $_SESSION['msg_sucesso'] ?? '';
$msg_erro = $_SESSION['msg_erro'] ?? '';
unset($_SESSION['msg_sucesso'], $_SESSION['msg_erro']);

// Helpers
function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
function redirect_self($q = '') {
    $loc = 'cadastro_cobradores.php' . ($q ? ('?' . $q) : '');
    header("Location: $loc");
    exit;
}

// Processar ações POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['msg_erro'] = 'Token inválido. Atualize a página e tente novamente.';
        redirect_self();
    }

    $acao = $_POST['acao'] ?? '';
    if ($acao === 'criar') {
        $nome = trim($_POST['nome'] ?? '');
        $usuario = trim($_POST['usuario'] ?? '');
        $senha = trim($_POST['senha'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $ativo = isset($_POST['ativo']) && $_POST['ativo'] ? 1 : 0;

        // Validações
        $erros = [];
        if ($nome === '') $erros[] = 'Nome é obrigatório.';
        if ($usuario === '') $erros[] = 'Usuário é obrigatório.';
        if ($senha === '') $erros[] = 'Senha é obrigatória.';

        if (!empty($erros)) {
            $_SESSION['msg_erro'] = implode(' ', $erros);
            redirect_self();
        }

        // Checar usuário único
        $q = $conn->prepare("SELECT id FROM cobradores WHERE usuario = ? LIMIT 1");
        $q->bind_param("s", $usuario);
        $q->execute();
        $r = $q->get_result();
        if ($r && $r->num_rows > 0) {
            $_SESSION['msg_erro'] = 'Usuário já existe. Escolha outro.';
            $q->close();
            redirect_self();
        }
        $q->close();

        // Hash da senha
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

        $ins = $conn->prepare("INSERT INTO cobradores (nome, usuario, senha, email, telefone, ativo, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $ins->bind_param("sssssi", $nome, $usuario, $senha_hash, $email, $telefone, $ativo);
        if ($ins->execute()) {
            $_SESSION['msg_sucesso'] = 'Cobrador criado com sucesso.';
        } else {
            $_SESSION['msg_erro'] = 'Erro ao criar cobrador: ' . $ins->error;
        }
        $ins->close();
        redirect_self();

    } elseif ($acao === 'editar') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) { $_SESSION['msg_erro'] = 'ID inválido.'; redirect_self(); }
        $nome = trim($_POST['nome'] ?? '');
        $usuario = trim($_POST['usuario'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $ativo = isset($_POST['ativo']) && $_POST['ativo'] ? 1 : 0;
        $nova_senha = trim($_POST['nova_senha'] ?? '');

        $erros = [];
        if ($nome === '') $erros[] = 'Nome é obrigatório.';
        if ($usuario === '') $erros[] = 'Usuário é obrigatório.';
        if (!empty($erros)) {
            $_SESSION['msg_erro'] = implode(' ', $erros);
            redirect_self("edit=$id");
        }

        // Verificar duplicidade de usuario em outro id
        $q = $conn->prepare("SELECT id FROM cobradores WHERE usuario = ? AND id <> ? LIMIT 1");
        $q->bind_param("si", $usuario, $id);
        $q->execute();
        $rr = $q->get_result();
        if ($rr && $rr->num_rows > 0) {
            $_SESSION['msg_erro'] = 'Outro cobrador já usa esse usuário.';
            $q->close();
            redirect_self("edit=$id");
        }
        $q->close();

        // Update base
        $u = $conn->prepare("UPDATE cobradores SET nome=?, usuario=?, email=?, telefone=?, ativo=? WHERE id=?");
        $u->bind_param("ssssii", $nome, $usuario, $email, $telefone, $ativo, $id);
        if (!$u->execute()) {
            $_SESSION['msg_erro'] = 'Erro ao atualizar: ' . $u->error;
            $u->close();
            redirect_self("edit=$id");
        }
        $u->close();

        // Atualizar senha se informou
        if ($nova_senha !== '') {
            $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
            $up = $conn->prepare("UPDATE cobradores SET senha = ? WHERE id = ?");
            $up->bind_param("si", $senha_hash, $id);
            if (!$up->execute()) {
                $_SESSION['msg_erro'] = 'Erro ao atualizar senha: ' . $up->error;
                $up->close();
                redirect_self("edit=$id");
            }
            $up->close();
        }

        $_SESSION['msg_sucesso'] = 'Cobrador atualizado com sucesso.';
        redirect_self();

    } elseif ($acao === 'deletar') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) { $_SESSION['msg_erro'] = 'ID inválido.'; redirect_self(); }

        // Você pode checar dependências aqui (logs, vinculações)
        $d = $conn->prepare("DELETE FROM cobradores WHERE id = ?");
        $d->bind_param("i", $id);
        if ($d->execute()) {
            $_SESSION['msg_sucesso'] = 'Cobrador removido.';
        } else {
            $_SESSION['msg_erro'] = 'Erro ao remover cobrador: ' . $d->error;
        }
        $d->close();
        redirect_self();

    } elseif ($acao === 'reset_senha') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) { $_SESSION['msg_erro'] = 'ID inválido.'; redirect_self(); }

        $nova = function_exists('gerar_senha') ? gerar_senha(8) : substr(bin2hex(random_bytes(4)),0,8);
        $hash = password_hash($nova, PASSWORD_DEFAULT);
        $u = $conn->prepare("UPDATE cobradores SET senha = ? WHERE id = ?");
        $u->bind_param("si", $hash, $id);
        if ($u->execute()) {
            $_SESSION['msg_sucesso'] = "Senha resetada. Nova senha: <strong>" . e($nova) . "</strong>";
        } else {
            $_SESSION['msg_erro'] = 'Erro ao resetar senha: ' . $u->error;
        }
        $u->close();
        redirect_self();
    }
}

// Buscar cobradores para listagem
$list = $conn->query("SELECT id, nome, usuario, email, telefone, ativo, created_at FROM cobradores ORDER BY nome");

// Se edição solicitada por GET
$editing = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $prep = $conn->prepare("SELECT id, nome, usuario, email, telefone, ativo FROM cobradores WHERE id = ? LIMIT 1");
    $prep->bind_param("i", $edit_id);
    $prep->execute();
    $editing = $prep->get_result()->fetch_assoc();
    $prep->close();
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>Cadastro de Cobradores</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="app-style.css">
    <style>
        /* Página simples e organizada */
        body { font-family: Arial,Helvetica,sans-serif; background: #f4f6f8; color:#111; margin:0; padding:20px; }
        .container { max-width:1100px; margin:0 auto; }
        .card { background:#fff; padding:16px; border-radius:10px; box-shadow:0 6px 18px rgba(0,0,0,0.06); margin-bottom:16px; }
        .grid { display:grid; grid-template-columns: 1fr 360px; gap:16px; }
        label { display:block; font-weight:600; margin-bottom:6px; }
        input[type="text"], input[type="email"], input[type="password"] { width:100%; padding:8px 10px; border-radius:8px; border:1px solid #d6dae1; box-sizing:border-box; }
        .actions { display:flex; gap:8px; margin-top:12px; }
        .btn { padding:8px 12px; border-radius:8px; border:0; cursor:pointer; background:#2563eb; color:#fff; }
        .btn.secondary { background:#6b7280; }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:10px 12px; border-bottom:1px solid #eef2f6; text-align:left; }
        th { background:#f6f8fa; font-weight:700; }
        .small { font-size:.9rem; color:#666; }
        .msg-success { background:#ecfdf5; color:#065f46; padding:10px; border-radius:6px; margin-bottom:12px; border:1px solid #bbf7d0; }
        .msg-error { background:#fff1f2; color:#7f1d1d; padding:10px; border-radius:6px; margin-bottom:12px; border:1px solid #fbcaca; }
        @media (max-width:900px) { .grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<div class="container">
    <h1>Cadastro de Cobradores</h1>

    <?php if ($msg_sucesso): ?><div class="msg-success"><?= $msg_sucesso ?></div><?php endif; ?>
    <?php if ($msg_erro): ?><div class="msg-error"><?= $msg_erro ?></div><?php endif; ?>

    <div class="grid">
        <div class="card">
            <h3><?= $editing ? 'Editar Cobrador' : 'Novo Cobrador' ?></h3>
            <form method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <?php if ($editing): ?>
                    <input type="hidden" name="acao" value="editar">
                    <input type="hidden" name="id" value="<?= intval($editing['id']) ?>">
                <?php else: ?>
                    <input type="hidden" name="acao" value="criar">
                <?php endif; ?>

                <div>
                    <label>Nome</label>
                    <input type="text" name="nome" value="<?= e($editing['nome'] ?? '') ?>" required>
                </div>
                <div>
                    <label>Usuário (login)</label>
                    <input type="text" name="usuario" value="<?= e($editing['usuario'] ?? '') ?>" required>
                </div>
                <div>
                    <label>E-mail</label>
                    <input type="email" name="email" value="<?= e($editing['email'] ?? '') ?>">
                </div>
                <div>
                    <label>Telefone</label>
                    <input type="text" name="telefone" value="<?= e($editing['telefone'] ?? '') ?>">
                </div>
                <div>
                    <label><?= $editing ? 'Nova senha (opcional)' : 'Senha' ?></label>
                    <input type="password" name="<?= $editing ? 'nova_senha' : 'senha' ?>" <?= $editing ? '' : 'required' ?>>
                </div>
                <div style="margin-top:8px;">
                    <label>Ativo</label>
                    <select name="ativo">
                        <option value="1" <?= (!isset($editing['ativo']) || $editing['ativo']) ? 'selected' : '' ?>>Sim</option>
                        <option value="0" <?= (isset($editing['ativo']) && !$editing['ativo']) ? 'selected' : '' ?>>Não</option>
                    </select>
                </div>

                <div class="actions">
                    <button type="submit" class="btn"><?= $editing ? 'Salvar' : 'Criar Cobrador' ?></button>
                    <?php if ($editing): ?>
                        <a class="btn secondary" href="cadastro_cobradores.php">Cancelar</a>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Confirma remover este cobrador?')">
                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                            <input type="hidden" name="acao" value="deletar">
                            <input type="hidden" name="id" value="<?= intval($editing['id']) ?>">
                            <button type="submit" class="btn" style="background:#ef4444">Remover</button>
                        </form>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Resetar senha deste cobrador?')">
                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                            <input type="hidden" name="acao" value="reset_senha">
                            <input type="hidden" name="id" value="<?= intval($editing['id']) ?>">
                            <button type="submit" class="btn" style="background:#f59e0b">Resetar Senha</button>
                        </form>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="card">
            <h3>Lista de Cobradores</h3>
            <?php if ($list && $list->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr><th>Nome</th><th>Usuário</th><th>E-mail</th><th>Telefone</th><th>Ativo</th><th>Criado</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $list->fetch_assoc()): ?>
                            <tr>
                                <td><?= e($row['nome']) ?></td>
                                <td><?= e($row['usuario']) ?></td>
                                <td><?= e($row['email']) ?></td>
                                <td><?= e($row['telefone']) ?></td>
                                <td><?= $row['ativo'] ? 'Sim' : 'Não' ?></td>
                                <td class="small"><?= e($row['created_at']) ?></td>
                                <td>
                                    <a class="btn-ghost" href="cadastro_cobradores.php?edit=<?= intval($row['id']) ?>">Editar</a>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Confirma remover este cobrador?')">
                                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                        <input type="hidden" name="acao" value="deletar">
                                        <input type="hidden" name="id" value="<?= intval($row['id']) ?>">
                                        <button type="submit" class="btn-ghost" style="background:transparent;border:1px solid #f3f4f6;padding:6px 8px;border-radius:6px;">Remover</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="small">Nenhum cobrador cadastrado.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>