<?php
session_start();
include "conexao.php";
include_once "funcoes.php";

// Verifica login
if (!isset($_SESSION['tipo']) || !isset($_SESSION['id'])) {
    header("Location: index.php");
    exit;
}

$usuario_tipo = $_SESSION['tipo'];
$usuario_id   = $_SESSION['id'];
$acao         = $_GET['acao'] ?? 'listar';

$erros        = [];
$msg_sucesso  = $_SESSION['msg_sucesso'] ?? '';
unset($_SESSION['msg_sucesso']);

// Marca como lida ao abrir mensagem
if ($acao === 'ver' && isset($_GET['id'])) {
    $mensagem_id = intval($_GET['id']);
    $marcar_stmt = $conn->prepare("UPDATE mensagens SET lida = 1, data_leitura = NOW() WHERE id = ? AND destinatario_id = ? AND destinatario_tipo = ?");
    $marcar_stmt->bind_param("iis", $mensagem_id, $usuario_id, $usuario_tipo);
    $marcar_stmt->execute();
}

// ENVIO DE MENSAGEM
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'enviar') {
    $dados = limpar_entrada($_POST);

    if (empty($dados['destinatario_tipo'])) $erros[] = 'Tipo de destinatário é obrigatório';
    if (empty($dados['envio_tipo'])) $erros[] = 'Tipo de envio é obrigatório';
    if ($dados['envio_tipo'] !== 'todos' && empty($dados['destinatario_id'])) $erros[] = 'Destinatário é obrigatório';
    if (empty($dados['assunto'])) $erros[] = 'Assunto é obrigatório';
    if (empty($dados['mensagem'])) $erros[] = 'Mensagem é obrigatória';

    if (empty($erros)) {
        if ($dados['envio_tipo'] === 'turma') {
            $turma_id = intval($dados['destinatario_id']);
            $alunos   = $conn->query("SELECT id FROM alunos WHERE turma_id = $turma_id");
            while ($aluno = $alunos->fetch_assoc()) {
                $stmt = $conn->prepare("INSERT INTO mensagens (remetente_id, remetente_tipo, destinatario_id, destinatario_tipo, assunto, mensagem, created_at) VALUES (?, ?, ?, 'aluno', ?, ?, NOW())");
                $stmt->bind_param("iisss", $usuario_id, $usuario_tipo, $aluno['id'], $dados['assunto'], $dados['mensagem']);
                $stmt->execute();
            }
            $_SESSION['msg_sucesso'] = 'Mensagem enviada para todos os alunos da turma!';
            header("Location: comunicacao.php?acao=enviadas");
            exit;
        } elseif ($dados['envio_tipo'] === 'professor') {
            $prof_id = intval($dados['destinatario_id']);
            $alunos = $conn->query("SELECT a.id FROM alunos a JOIN turmas t ON a.turma_id = t.id WHERE t.professor_id = $prof_id");
            while ($aluno = $alunos->fetch_assoc()) {
                $stmt = $conn->prepare("INSERT INTO mensagens (remetente_id, remetente_tipo, destinatario_id, destinatario_tipo, assunto, mensagem, created_at) VALUES (?, ?, ?, 'aluno', ?, ?, NOW())");
                $stmt->bind_param("iisss", $usuario_id, $usuario_tipo, $aluno['id'], $dados['assunto'], $dados['mensagem']);
                $stmt->execute();
            }
            $_SESSION['msg_sucesso'] = 'Mensagem enviada para todos os alunos do professor!';
            header("Location: comunicacao.php?acao=enviadas");
            exit;
        } elseif ($dados['envio_tipo'] === 'todos') {
            $alunos = $conn->query("SELECT id FROM alunos");
            while ($aluno = $alunos->fetch_assoc()) {
                $stmt = $conn->prepare("INSERT INTO mensagens (remetente_id, remetente_tipo, destinatario_id, destinatario_tipo, assunto, mensagem, created_at) VALUES (?, ?, ?, 'aluno', ?, ?, NOW())");
                $stmt->bind_param("iisss", $usuario_id, $usuario_tipo, $aluno['id'], $dados['assunto'], $dados['mensagem']);
                $stmt->execute();
            }
            $_SESSION['msg_sucesso'] = 'Mensagem enviada para todos os alunos!';
            header("Location: comunicacao.php?acao=enviadas");
            exit;
        } else {
            $stmt = $conn->prepare("INSERT INTO mensagens (remetente_id, remetente_tipo, destinatario_id, destinatario_tipo, assunto, mensagem, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("iissss", $usuario_id, $usuario_tipo, $dados['destinatario_id'], $dados['destinatario_tipo'], $dados['assunto'], $dados['mensagem']);
            if ($stmt->execute()) {
                $_SESSION['msg_sucesso'] = 'Mensagem enviada com sucesso!';
                header("Location: comunicacao.php?acao=enviadas");
                exit;
            } else {
                $erros[] = 'Erro ao enviar mensagem';
            }
        }
    }
}

// Contagem de não lidas
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM mensagens WHERE destinatario_id = ? AND destinatario_tipo = ? AND lida = 0");
$stmt->bind_param("is", $usuario_id, $usuario_tipo);
$stmt->execute();
$mensagens_nao_lidas = $stmt->get_result()->fetch_assoc()['total'];

// Buscar destinatários
$turmas = $professores = $alunos = $admins = false;

if ($usuario_tipo === 'admin') {
    $turmas      = $conn->query("SELECT id, nome FROM turmas ORDER BY nome");
    $professores = $conn->query("SELECT id, nome FROM professores ORDER BY nome");
    $alunos      = $conn->query("SELECT id, nome FROM alunos ORDER BY nome");
    $admins      = $conn->query("SELECT id, usuario FROM admins WHERE id != $usuario_id ORDER BY usuario");
} elseif ($usuario_tipo === 'professor') {
    $turmas      = $conn->query("SELECT id, nome FROM turmas WHERE professor_id = $usuario_id ORDER BY nome");
    $alunos      = $conn->query("SELECT a.id, a.nome FROM alunos a JOIN turmas t ON a.turma_id = t.id WHERE t.professor_id = $usuario_id ORDER BY a.nome");
    $admins      = $conn->query("SELECT id, usuario FROM admins ORDER BY usuario");
} elseif ($usuario_tipo === 'aluno') {
    $admins      = $conn->query("SELECT id, usuario FROM admins ORDER BY usuario");
    $professores = $conn->query("SELECT p.id, p.nome FROM professores p JOIN turmas t ON p.id = t.professor_id JOIN alunos a ON a.turma_id = t.id WHERE a.id = $usuario_id");
    $turmas      = $conn->query("SELECT t.id, t.nome FROM turmas t JOIN alunos a ON t.id = a.turma_id WHERE a.id = $usuario_id");
}

// Buscar mensagens recebidas/enviadas
function buscarMensagens($conn, $tipo, $id, $is_enviadas = false) {
    if ($is_enviadas) {
        $sql = "
            SELECT m.*,
                   COALESCE(a.usuario, p.nome, al.nome) AS destinatario_nome
            FROM mensagens m
            LEFT JOIN admins a ON m.destinatario_tipo = 'admin' AND m.destinatario_id = a.id
            LEFT JOIN professores p ON m.destinatario_tipo = 'professor' AND m.destinatario_id = p.id
            LEFT JOIN alunos al ON m.destinatario_tipo = 'aluno' AND m.destinatario_id = al.id
            WHERE m.remetente_id = ? AND m.remetente_tipo = ?
            ORDER BY m.created_at DESC
        ";
    } else {
        $sql = "
            SELECT m.*,
                   COALESCE(a.usuario, p.nome, al.nome) AS remetente_nome
            FROM mensagens m
            LEFT JOIN admins a ON m.remetente_tipo = 'admin' AND m.remetente_id = a.id
            LEFT JOIN professores p ON m.remetente_tipo = 'professor' AND m.remetente_id = p.id
            LEFT JOIN alunos al ON m.remetente_tipo = 'aluno' AND m.remetente_id = al.id
            WHERE m.destinatario_id = ? AND m.destinatario_tipo = ?
            ORDER BY m.created_at DESC
        ";
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $id, $tipo);
    $stmt->execute();
    return $stmt->get_result();
}
if ($acao === 'listar') {
    $mensagens = buscarMensagens($conn, $usuario_tipo, $usuario_id, false);
} elseif ($acao === 'enviadas') {
    $mensagens = buscarMensagens($conn, $usuario_tipo, $usuario_id, true);
}

// Exibir mensagem detalhada
if ($acao === 'ver' && isset($_GET['id'])) {
    $mensagem_id = intval($_GET['id']);
    $sql = "
        SELECT m.*,
               COALESCE(a.usuario, p.nome, al.nome) AS remetente_nome,
               COALESCE(ad.usuario, pd.nome, ald.nome) AS destinatario_nome
        FROM mensagens m
        LEFT JOIN admins a ON m.remetente_tipo = 'admin' AND m.remetente_id = a.id
        LEFT JOIN professores p ON m.remetente_tipo = 'professor' AND m.remetente_id = p.id
        LEFT JOIN alunos al ON m.remetente_tipo = 'aluno' AND m.remetente_id = al.id
        LEFT JOIN admins ad ON m.destinatario_tipo = 'admin' AND m.destinatario_id = ad.id
        LEFT JOIN professores pd ON m.destinatario_tipo = 'professor' AND m.destinatario_id = pd.id
        LEFT JOIN alunos ald ON m.destinatario_tipo = 'aluno' AND m.destinatario_id = ald.id
        WHERE m.id = ? AND (
            (m.destinatario_id = ? AND m.destinatario_tipo = ?) OR
            (m.remetente_id = ? AND m.remetente_tipo = ?)
        )
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisis", $mensagem_id, $usuario_id, $usuario_tipo, $usuario_id, $usuario_tipo);
    $stmt->execute();
    $mensagem_detalhes = $stmt->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Comunicação Interna</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto:400,500&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Roboto', Arial, sans-serif; background: #f4f5f7; }
        .container { max-width: 850px; margin: 40px auto; background: #fff; padding: 30px 40px; border-radius: 10px; box-shadow: 0 4px 24px #0001; }
        .tab-buttons { display: flex; gap: 10px; border-bottom: 2px solid #eee; margin-bottom: 24px; }
        .tab-button { background: none; border: none; font-size: 1.1em; color: #666; padding: 10px 24px; cursor: pointer; border-radius: 8px 8px 0 0; }
        .tab-button.active, .tab-button:hover { background: #e6f0fa; color: #007bff; font-weight: 500; }
        .badge { background: #dc3545; color: #fff; border-radius: 50%; padding: 2px 8px; font-size: 0.8em; margin-left: 6px; }
        .msg-sucesso, .msg-erro { margin-bottom: 20px; padding: 12px; border-radius: 6px; font-size: 1em; }
        .msg-sucesso { background: #d1f5d9; color: #146c43; border: 1px solid #bff0c6; }
        .msg-erro { background: #ffd6d6; color: #961514; border: 1px solid #f5c2c7; }
        .mensagem-card { background: #f8fafc; border-radius: 8px; box-shadow: 0 1px 4px #0001; padding: 18px 22px; margin-bottom: 18px; transition: box-shadow 0.2s; cursor: pointer;}
        .mensagem-card:hover { box-shadow: 0 4px 24px #0002; background: #eef6fc;}
        .mensagem-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
        .mensagem-remetente, .mensagem-destinatario { font-weight: 500; color: #007bff; }
        .mensagem-assunto { font-size: 1.1em; color: #146c43; margin-bottom: 4px;}
        .mensagem-data { color: #888; font-size: 0.95em; }
        .mensagem-preview { color: #555; font-size: 0.96em; margin-bottom: 2px;}
        .status-nao-lida { color: #dc3545; font-weight: bold; font-size: 0.9em;}
        .status-lida { color: #28a745; font-size: 0.9em;}
        .form-row { display: flex; gap: 16px; margin-bottom: 18px; }
        .form-row > div { flex: 1; }
        select, input, textarea { width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #cfd8dc; margin-top: 4px; font-size: 1em; }
        button[type=submit], .btn { background: #007bff; color: #fff; border: none; padding: 10px 26px; border-radius: 6px; font-size: 1.1em; margin-top: 16px; cursor: pointer; transition: background 0.2s;}
        button[type=submit]:hover, .btn:hover { background: #0056b3;}
        .btn { margin-top: 0; }
        .btn-secondary { background: #e6f0fa; color: #007bff;}
        .btn-secondary:hover { background: #d0e3f7;}
        @media (max-width: 600px) { .container { padding: 16px;} .form-row { flex-direction: column; gap: 6px;} }
    </style>
    <script>
        function verMensagem(id) {
            window.location.href = 'comunicacao.php?acao=ver&id=' + id;
        }
        function responderMensagem(id) {
            window.location.href = 'comunicacao.php?acao=nova&responder=' + id;
        }
        function atualizarDestinatarios() {
            const tipo = document.getElementById('destinatario_tipo').value;
            const envio = document.getElementById('envio_tipo').value;
            const destinatarioId = document.getElementById('destinatario_id');
            destinatarioId.innerHTML = '<option value="">Selecione...</option>';
            if (envio === 'turma') {
                <?php if ($turmas && $turmas->num_rows > 0): ?>
                    <?php $turmas->data_seek(0); while ($turma = $turmas->fetch_assoc()): ?>
                        destinatarioId.innerHTML += '<option value="<?= $turma['id'] ?>"><?= htmlspecialchars($turma['nome']) ?></option>';
                    <?php endwhile; ?>
                <?php endif; ?>
            } else if (envio === 'professor') {
                <?php if ($professores && $professores->num_rows > 0): ?>
                    <?php $professores->data_seek(0); while ($prof = $professores->fetch_assoc()): ?>
                        destinatarioId.innerHTML += '<option value="<?= $prof['id'] ?>"><?= htmlspecialchars($prof['nome']) ?></option>';
                    <?php endwhile; ?>
                <?php endif; ?>
            } else if (envio === 'todos') {
                destinatarioId.innerHTML = '<option value="0">Todos os alunos</option>';
            } else {
                if (tipo === 'admin') {
                    <?php if ($admins && $admins->num_rows > 0): ?>
                        <?php $admins->data_seek(0); while ($adm = $admins->fetch_assoc()): ?>
                            destinatarioId.innerHTML += '<option value="<?= $adm['id'] ?>"><?= htmlspecialchars($adm['usuario']) ?></option>';
                        <?php endwhile; ?>
                    <?php endif; ?>
                }
                if (tipo === 'professor') {
                    <?php if ($professores && $professores->num_rows > 0): ?>
                        <?php $professores->data_seek(0); while ($prof = $professores->fetch_assoc()): ?>
                            destinatarioId.innerHTML += '<option value="<?= $prof['id'] ?>"><?= htmlspecialchars($prof['nome']) ?></option>';
                        <?php endwhile; ?>
                    <?php endif; ?>
                }
                if (tipo === 'aluno') {
                    <?php if ($alunos && $alunos->num_rows > 0): ?>
                        <?php $alunos->data_seek(0); while ($aluno = $alunos->fetch_assoc()): ?>
                            destinatarioId.innerHTML += '<option value="<?= $aluno['id'] ?>"><?= htmlspecialchars($aluno['nome']) ?></option>';
                        <?php endwhile; ?>
                    <?php endif; ?>
                }
            }
        }
    </script>
</head>
<body>
<div class="container">
    <h1 style="margin-bottom: 0; font-weight: 500; color: #007bff;"><i class="fa-solid fa-comments"></i> Comunicação Interna</h1>
    <div class="tab-buttons">
        <a href="comunicacao.php?acao=listar" class="tab-button <?= $acao === 'listar' ? 'active' : '' ?>">
            <i class="fa-solid fa-inbox"></i> Caixa de Entrada
            <?php if ($mensagens_nao_lidas > 0): ?>
                <span class="badge"><?= $mensagens_nao_lidas ?></span>
            <?php endif; ?>
        </a>
        <a href="comunicacao.php?acao=enviadas" class="tab-button <?= $acao === 'enviadas' ? 'active' : '' ?>">
            <i class="fa-solid fa-paper-plane"></i> Mensagens Enviadas
        </a>
        <a href="comunicacao.php?acao=nova" class="tab-button <?= $acao === 'nova' ? 'active' : '' ?>">
            <i class="fa-solid fa-plus"></i> Nova Mensagem
        </a>
    </div>
    <?php if ($msg_sucesso): ?><div class="msg-sucesso"><?= $msg_sucesso ?></div><?php endif; ?>
    <?php if (!empty($erros)): ?><div class="msg-erro"><ul><?php foreach ($erros as $erro): ?><li><?= htmlspecialchars($erro) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <?php if ($acao === 'listar'): ?>
        <h2><i class="fa-solid fa-inbox"></i> Caixa de Entrada</h2>
        <?php if ($mensagens && $mensagens->num_rows > 0): ?>
            <?php while ($msg = $mensagens->fetch_assoc()): ?>
                <div class="mensagem-card" onclick="verMensagem(<?= $msg['id'] ?>)">
                    <div class="mensagem-header">
                        <span class="mensagem-remetente"><i class="fa-solid fa-user"></i>
                            Remetente: <?= htmlspecialchars($msg['remetente_nome']) ?>
                        </span>
                        <span class="mensagem-data"><i class="fa-regular fa-clock"></i> <?= formatar_data_hora_br($msg['created_at']) ?></span>
                    </div>
                    <div class="mensagem-assunto"><b><?= htmlspecialchars($msg['assunto']) ?></b></div>
                    <div class="mensagem-preview"><?= htmlspecialchars(substr($msg['mensagem'], 0, 90)) ?>...</div>
                    <div style="margin-top: 8px;">
                        <?php if ($msg['lida']): ?>
                            <span class="status-lida"><i class="fa-solid fa-check"></i> Lida</span>
                        <?php else: ?>
                            <span class="status-nao-lida"><i class="fa-solid fa-circle-exclamation"></i> Não lida</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="color: #aaa; margin-top: 32px;"><i class="fa-solid fa-ghost"></i> Nenhuma mensagem encontrada.</div>
        <?php endif; ?>
    <?php elseif ($acao === 'enviadas'): ?>
        <h2><i class="fa-solid fa-paper-plane"></i> Mensagens Enviadas</h2>
        <?php if ($mensagens && $mensagens->num_rows > 0): ?>
            <?php while ($msg = $mensagens->fetch_assoc()): ?>
                <div class="mensagem-card" onclick="verMensagem(<?= $msg['id'] ?>)">
                    <div class="mensagem-header">
                        <span class="mensagem-destinatario"><i class="fa-solid fa-user-check"></i>
                            Para: <?= htmlspecialchars($msg['destinatario_nome']) ?>
                        </span>
                        <span class="mensagem-data"><i class="fa-regular fa-clock"></i> <?= formatar_data_hora_br($msg['created_at']) ?></span>
                    </div>
                    <div class="mensagem-assunto"><b><?= htmlspecialchars($msg['assunto']) ?></b></div>
                    <div class="mensagem-preview"><?= htmlspecialchars(substr($msg['mensagem'], 0, 90)) ?>...</div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="color: #aaa; margin-top: 32px;"><i class="fa-solid fa-ghost"></i> Nenhuma mensagem enviada encontrada.</div>
        <?php endif; ?>
    <?php elseif ($acao === 'nova'): ?>
        <?php
        // Responder: Preenche destinatário se veio com ?responder
        $assunto_resposta = "";
        if (isset($_GET['responder'])) {
            $msg_id = intval($_GET['responder']);
            $sql = "
                SELECT m.*, COALESCE(a.usuario, p.nome, al.nome) AS remetente_nome, m.remetente_id, m.remetente_tipo
                FROM mensagens m
                LEFT JOIN admins a ON m.remetente_tipo = 'admin' AND m.remetente_id = a.id
                LEFT JOIN professores p ON m.remetente_tipo = 'professor' AND m.remetente_id = p.id
                LEFT JOIN alunos al ON m.remetente_tipo = 'aluno' AND m.remetente_id = al.id
                WHERE m.id = ?
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $msg_id);
            $stmt->execute();
            $resp_msg = $stmt->get_result()->fetch_assoc();
            $assunto_resposta = "RE: " . $resp_msg['assunto'];
        }
        ?>
        <h2><i class="fa-solid fa-plus"></i> Nova Mensagem</h2>
        <form method="post" autocomplete="off">
            <input type="hidden" name="acao" value="enviar">
            <div class="form-row">
                <div>
                    <label for="destinatario_tipo"><i class="fa-solid fa-users"></i> Tipo de Destinatário</label>
                    <select name="destinatario_tipo" id="destinatario_tipo" onchange="atualizarDestinatarios()" required>
                        <option value="">Selecione...</option>
                        <option value="admin" <?= isset($resp_msg) && $resp_msg['remetente_tipo'] == 'admin' ? 'selected' : '' ?>>Administrador</option>
                        <option value="professor" <?= isset($resp_msg) && $resp_msg['remetente_tipo'] == 'professor' ? 'selected' : '' ?>>Professor</option>
                        <option value="aluno" <?= isset($resp_msg) && $resp_msg['remetente_tipo'] == 'aluno' ? 'selected' : '' ?>>Aluno</option>
                    </select>
                </div>
                <div>
                    <label for="envio_tipo"><i class="fa-solid fa-share-nodes"></i> Tipo de envio</label>
                    <select name="envio_tipo" id="envio_tipo" onchange="atualizarDestinatarios()" required>
                        <option value="individual">Individual</option>
                        <option value="turma">Por Turma</option>
                        <option value="professor">Por Professor</option>
                        <option value="todos">Todos os alunos</option>
                    </select>
                </div>
                <div>
                    <label for="destinatario_id"><i class="fa-solid fa-user"></i> Destinatário</label>
                    <select name="destinatario_id" id="destinatario_id" required>
                        <option value="">Selecione...</option>
                        <?php if (isset($resp_msg)): ?>
                            <option value="<?= $resp_msg['remetente_id'] ?>" selected><?= htmlspecialchars($resp_msg['remetente_nome']) ?></option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
            <div>
                <label for="assunto"><i class="fa-solid fa-heading"></i> Assunto</label>
                <input type="text" name="assunto" id="assunto" required maxlength="255" value="<?= htmlspecialchars($assunto_resposta) ?>">
            </div>
            <div>
                <label for="mensagem"><i class="fa-solid fa-message"></i> Mensagem</label>
                <textarea name="mensagem" id="mensagem" rows="6" required placeholder="Digite sua mensagem..."></textarea>
            </div>
            <button type="submit"><i class="fa-solid fa-paper-plane"></i> Enviar Mensagem</button>
        </form>
    <?php elseif ($acao === 'ver' && !empty($mensagem_detalhes)): ?>
        <h2><i class="fa-solid fa-envelope-open-text"></i> Mensagem</h2>
        <div class="mensagem-card" style="background:#eef6fc;box-shadow:0 2px 16px #007bff22;">
            <div class="mensagem-header">
                <span class="mensagem-remetente"><i class="fa-solid fa-user"></i>
                    De: <?= htmlspecialchars($mensagem_detalhes['remetente_nome']) ?>
                </span>
                <span class="mensagem-destinatario"><i class="fa-solid fa-user-check"></i>
                    Para: <?= htmlspecialchars($mensagem_detalhes['destinatario_nome']) ?>
                </span>
            </div>
            <div class="mensagem-assunto"><b>Assunto:</b> <?= htmlspecialchars($mensagem_detalhes['assunto']) ?></div>
            <div style="margin:14px 0 8px 0;">
                <i class="fa-regular fa-clock"></i>
                <?= formatar_data_hora_br($mensagem_detalhes['created_at']) ?>
                <?php if ($mensagem_detalhes['lida'] && $mensagem_detalhes['data_leitura']): ?>
                    &nbsp; <span class="status-lida"><i class="fa-solid fa-check"></i> Lida em <?= formatar_data_hora_br($mensagem_detalhes['data_leitura']) ?></span>
                <?php else: ?>
                    &nbsp; <span class="status-nao-lida"><i class="fa-solid fa-circle-exclamation"></i> Não lida</span>
                <?php endif; ?>
            </div>
            <div style="background:#fff;padding:18px;border-radius:7px;border:1px solid #ddd;">
                <?= nl2br(htmlspecialchars($mensagem_detalhes['mensagem'])) ?>
            </div>
            <?php if ($mensagem_detalhes['remetente_id'] != $usuario_id || $mensagem_detalhes['remetente_tipo'] != $usuario_tipo): ?>
                <div style="margin-top:22px;">
                    <button class="btn btn-secondary" onclick="responderMensagem(<?= $mensagem_detalhes['id'] ?>)">
                        <i class="fa-solid fa-reply"></i> Responder
                    </button>
                    <a href="comunicacao.php?acao=listar">
                        <button type="button" class="btn btn-secondary">
                            <i class="fa-solid fa-arrow-left"></i> Voltar à Caixa de Entrada
                        </button>
                    </a>
                </div>
            <?php else: ?>
                <div style="margin-top:22px;">
                    <a href="comunicacao.php?acao=listar">
                        <button type="button" class="btn btn-secondary">
                            <i class="fa-solid fa-arrow-left"></i> Voltar à Caixa de Entrada
                        </button>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php elseif ($acao === 'ver'): ?>
        <div class="msg-erro">Mensagem não encontrada ou você não tem permissão para visualizá-la.</div>
    <?php endif; ?>
    <div style="margin-top: 30px;">
        <a href="<?= 
            $usuario_tipo === 'admin' ? 'dashboard_admin.php' : 
            ($usuario_tipo === 'professor' ? 'dashboard_professor.php' : 'dashboard_aluno.php') 
        ?>"><button type="button" class="btn btn-secondary">
        <i class="fa-solid fa-arrow-left"></i> Voltar ao Dashboard</button></a>
    </div>
</div>
</body>
</html>