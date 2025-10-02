<?php
// Exibir erros para debug (remova em produção)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include "conexao.php";
include_once "funcoes.php";

// Verifica login
if (!isset($_SESSION['tipo']) || !isset($_SESSION['id'])) {
    header("Location: index.php");
    exit;
}

$usuario_tipo = $_SESSION['tipo'];
$usuario_id   = intval($_SESSION['id']);
$acao         = $_GET['acao'] ?? 'listar';

$erros        = [];
$msg_sucesso  = $_SESSION['msg_sucesso'] ?? '';
unset($_SESSION['msg_sucesso']);

// Marca como lida ao abrir mensagem
if ($acao === 'ver' && isset($_GET['id'])) {
    $mensagem_id = intval($_GET['id']);
    $marcar_stmt = $conn->prepare("UPDATE mensagens SET lida = 1, data_leitura = NOW() WHERE id = ? AND destinatario_id = ? AND destinatario_tipo = ?");
    if ($marcar_stmt) {
        $marcar_stmt->bind_param("iis", $mensagem_id, $usuario_id, $usuario_tipo);
        $marcar_stmt->execute();
        $marcar_stmt->close();
    } else {
        error_log("Prepare failed (marcar lida): " . $conn->error);
    }
}

// ENVIO DE MENSAGEM (restrições conforme perfil)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'enviar') {
    // sanitização simples se função não existir
    $dados = function_exists('limpar_entrada') ? limpar_entrada($_POST) : array_map(function($v){ return is_string($v) ? trim($v) : $v; }, $_POST);

    $dest_tipo = $dados['destinatario_tipo'] ?? '';
    $envio_tipo = $dados['envio_tipo'] ?? '';
    $dest_id = isset($dados['destinatario_id']) ? intval($dados['destinatario_id']) : null;
    $assunto = $dados['assunto'] ?? '';
    $mensagem = $dados['mensagem'] ?? '';

    if ($dest_tipo === '') $erros[] = 'Tipo de destinatário é obrigatório';
    if ($envio_tipo === '') $erros[] = 'Tipo de envio é obrigatório';
    if ($envio_tipo !== 'todos' && $envio_tipo !== 'turma' && $envio_tipo !== 'professor' && (is_null($dest_id) || $dest_id === 0)) $erros[] = 'Destinatário é obrigatório';
    if ($assunto === '') $erros[] = 'Assunto é obrigatório';
    if ($mensagem === '') $erros[] = 'Mensagem é obrigatória';

    // RESTRIÇÃO DE ENVIO
    $permitido = false;
    if ($usuario_tipo === 'admin') {
        $permitido = true;
    } elseif ($usuario_tipo === 'professor') {
        if ($dest_tipo === 'admin' || $dest_tipo === 'professor') {
            $permitido = true;
        } elseif ($dest_tipo === 'aluno' && $dest_id) {
            // verificar se o aluno pertence a alguma turma do professor
            $prof_id = $usuario_id;
            $q = $conn->prepare("SELECT turma_id FROM alunos WHERE id = ?");
            if ($q) {
                $q->bind_param("i", $dest_id);
                $q->execute();
                $row = $q->get_result()->fetch_assoc();
                $q->close();
                if ($row) {
                    $aluno_turma = intval($row['turma_id']);
                    $q2 = $conn->prepare("SELECT COUNT(*) as cnt FROM turmas WHERE id = ? AND professor_id = ?");
                    if ($q2) {
                        $q2->bind_param("ii", $aluno_turma, $prof_id);
                        $q2->execute();
                        $cnt = intval($q2->get_result()->fetch_assoc()['cnt'] ?? 0);
                        $q2->close();
                        if ($cnt > 0) $permitido = true;
                    }
                }
            }
        } elseif ($envio_tipo === 'professor' && $dest_id === $usuario_id) {
            // professor sending to "professor-<self>" means "all my students" — allow
            $permitido = true;
        }
    } elseif ($usuario_tipo === 'aluno') {
        if ($dest_tipo === 'admin') {
            $permitido = true;
        } elseif ($dest_tipo === 'professor' && $dest_id) {
            $s = $conn->prepare("SELECT t.professor_id FROM turmas t JOIN alunos a ON a.turma_id = t.id WHERE a.id = ?");
            if ($s) {
                $s->bind_param("i", $usuario_id);
                $s->execute();
                $prof_turma = $s->get_result()->fetch_assoc();
                $s->close();
                if ($prof_turma && intval($prof_turma['professor_id']) == intval($dest_id)) $permitido = true;
            }
        } elseif ($dest_tipo === 'turma' && $dest_id) {
            $s = $conn->prepare("SELECT turma_id FROM alunos WHERE id = ?");
            if ($s) {
                $s->bind_param("i", $usuario_id);
                $s->execute();
                $turma = $s->get_result()->fetch_assoc();
                $s->close();
                if ($turma && intval($turma['turma_id']) == intval($dest_id)) $permitido = true;
            }
        }
    }

    if (!$permitido) $erros[] = 'Você não tem permissão para enviar mensagens para este destinatário.';

    if (empty($erros)) {
        // envio por tipo
        if ($envio_tipo === 'turma') {
            $turma_id_send = intval($dest_id);
            $alunos_q = $conn->prepare("SELECT id FROM alunos WHERE turma_id = ?");
            if ($alunos_q) {
                $alunos_q->bind_param("i", $turma_id_send);
                $alunos_q->execute();
                $res_alunos = $alunos_q->get_result();
                $alunos_q->close();

                $stmt = $conn->prepare("INSERT INTO mensagens (remetente_id, remetente_tipo, destinatario_id, destinatario_tipo, assunto, mensagem, created_at) VALUES (?, ?, ?, 'aluno', ?, ?, NOW())");
                if (!$stmt) {
                    $erros[] = "Erro prepare (inserir turma): " . $conn->error;
                } else {
                    while ($row = $res_alunos->fetch_assoc()) {
                        $aluno_id = intval($row['id']);
                        // tipos: int, string, int, string, string => "isiss"
                        $stmt->bind_param("isiss", $usuario_id, $usuario_tipo, $aluno_id, $assunto, $mensagem);
                        $stmt->execute();
                    }
                    $stmt->close();
                    $_SESSION['msg_sucesso'] = 'Mensagem enviada para todos os alunos da turma!';
                    header("Location: comunicacao.php?acao=enviadas");
                    exit;
                }
            } else {
                $erros[] = "Erro ao buscar alunos: " . $conn->error;
            }
        } elseif ($envio_tipo === 'professor') {
            $prof_id_send = intval($dest_id);
            // Note: 'professor-<id>' is used both for 1:1 professor chats and 1:many (all students of that professor).
            // When the intent is to send to all students of a professor, we query students where turma.professor_id = prof_id
            $alunos_q = $conn->prepare("SELECT a.id FROM alunos a JOIN turmas t ON a.turma_id = t.id WHERE t.professor_id = ?");
            if ($alunos_q) {
                $alunos_q->bind_param("i", $prof_id_send);
                $alunos_q->execute();
                $res_alunos = $alunos_q->get_result();
                $alunos_q->close();

                $stmt = $conn->prepare("INSERT INTO mensagens (remetente_id, remetente_tipo, destinatario_id, destinatario_tipo, assunto, mensagem, created_at) VALUES (?, ?, ?, 'aluno', ?, ?, NOW())");
                if (!$stmt) {
                    $erros[] = "Erro prepare (inserir professor): " . $conn->error;
                } else {
                    while ($row = $res_alunos->fetch_assoc()) {
                        $aluno_id = intval($row['id']);
                        $stmt->bind_param("isiss", $usuario_id, $usuario_tipo, $aluno_id, $assunto, $mensagem);
                        $stmt->execute();
                    }
                    $stmt->close();
                    $_SESSION['msg_sucesso'] = 'Mensagem enviada para todos os alunos do professor!';
                    header("Location: comunicacao.php?acao=enviadas");
                    exit;
                }
            } else {
                $erros[] = "Erro ao buscar alunos do professor: " . $conn->error;
            }
        } elseif ($envio_tipo === 'todos') {
            $alunos_q = $conn->query("SELECT id FROM alunos");
            if ($alunos_q) {
                $stmt = $conn->prepare("INSERT INTO mensagens (remetente_id, remetente_tipo, destinatario_id, destinatario_tipo, assunto, mensagem, created_at) VALUES (?, ?, ?, 'aluno', ?, ?, NOW())");
                if (!$stmt) {
                    $erros[] = "Erro prepare (inserir todos): " . $conn->error;
                } else {
                    while ($row = $alunos_q->fetch_assoc()) {
                        $aluno_id = intval($row['id']);
                        $stmt->bind_param("isiss", $usuario_id, $usuario_tipo, $aluno_id, $assunto, $mensagem);
                        $stmt->execute();
                    }
                    $stmt->close();
                    $_SESSION['msg_sucesso'] = 'Mensagem enviada para todos os alunos!';
                    header("Location: comunicacao.php?acao=enviadas");
                    exit;
                }
            } else {
                $erros[] = "Erro ao buscar alunos: " . $conn->error;
            }
        } else {
            // envio individual
            $dest_id_single = intval($dest_id);
            $stmt = $conn->prepare("INSERT INTO mensagens (remetente_id, remetente_tipo, destinatario_id, destinatario_tipo, assunto, mensagem, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            if ($stmt) {
                // tipos: int,string,int,string,string,string => "isisss"
                $stmt->bind_param("isisss", $usuario_id, $usuario_tipo, $dest_id_single, $dest_tipo, $assunto, $mensagem);
                if ($stmt->execute()) {
                    $_SESSION['msg_sucesso'] = 'Mensagem enviada com sucesso!';
                    header("Location: comunicacao.php?acao=enviadas");
                    exit;
                } else {
                    $erros[] = 'Erro ao enviar mensagem: ' . $stmt->error;
                }
                $stmt->close();
            } else {
                $erros[] = 'Erro prepare (inserir individual): ' . $conn->error;
            }
        }
    }
}

// Contagem de não lidas
$mensagens_nao_lidas = 0;
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM mensagens WHERE destinatario_id = ? AND destinatario_tipo = ? AND lida = 0");
if ($stmt) {
    $stmt->bind_param("is", $usuario_id, $usuario_tipo);
    $stmt->execute();
    $mensagens_nao_lidas = intval($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();
}

// Buscar destinatários
$turmas = $professores = $alunos = $admins = false;

if ($usuario_tipo === 'admin') {
    $turmas      = $conn->query("SELECT id, nome FROM turmas ORDER BY nome");
    $professores = $conn->query("SELECT id, nome FROM professores ORDER BY nome");
    $alunos      = $conn->query("SELECT id, nome FROM alunos ORDER BY nome");
    $admins      = $conn->query("SELECT id, usuario FROM admins WHERE id != {$usuario_id} ORDER BY usuario");
} elseif ($usuario_tipo === 'professor') {
    $turmas      = $conn->query("SELECT id, nome FROM turmas WHERE professor_id = {$usuario_id} ORDER BY nome");
    $alunos      = $conn->query("SELECT a.id, a.nome FROM alunos a JOIN turmas t ON a.turma_id = t.id WHERE t.professor_id = {$usuario_id} ORDER BY a.nome");
    $admins      = $conn->query("SELECT id, usuario FROM admins ORDER BY usuario");
    $professores = $conn->query("SELECT id, nome FROM professores ORDER BY nome");
} elseif ($usuario_tipo === 'aluno') {
    $admins      = $conn->query("SELECT id, usuario FROM admins ORDER BY usuario");
    $prof_q = $conn->prepare("SELECT p.id, p.nome FROM professores p JOIN turmas t ON p.id = t.professor_id JOIN alunos a ON a.turma_id = t.id WHERE a.id = ?");
    if ($prof_q) {
        $prof_q->bind_param("i", $usuario_id);
        $prof_q->execute();
        $professores = $prof_q->get_result();
        $prof_q->close();
    } else {
        $professores = false;
    }
    $tur_q = $conn->prepare("SELECT t.id, t.nome FROM turmas t JOIN alunos a ON t.id = a.turma_id WHERE a.id = ?");
    if ($tur_q) {
        $tur_q->bind_param("i", $usuario_id);
        $tur_q->execute();
        $turmas = $tur_q->get_result();
        $tur_q->close();
    } else {
        $turmas = false;
    }
}

// Funções do chat e exibição
function buscarConversas($conn, $usuario_tipo, $usuario_id) {
    $sql = "
        SELECT 
            CASE WHEN m.remetente_id = ? AND m.remetente_tipo = ?
                 THEN CONCAT(m.destinatario_tipo, '-', m.destinatario_id)
                 ELSE CONCAT(m.remetente_tipo, '-', m.remetente_id)
            END as chat_key,
            MAX(m.created_at) as ultima_data
        FROM mensagens m
        WHERE (m.remetente_id = ? AND m.remetente_tipo = ?) OR (m.destinatario_id = ? AND m.destinatario_tipo = ?)
        GROUP BY chat_key
        ORDER BY ultima_data DESC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    $stmt->bind_param("isisis", $usuario_id, $usuario_tipo, $usuario_id, $usuario_tipo, $usuario_id, $usuario_tipo);
    $stmt->execute();
    $res = $stmt->get_result();
    $chats = [];
    while ($row = $res->fetch_assoc()) $chats[] = $row['chat_key'];
    $stmt->close();
    return $chats;
}

function buscarMensagensChat($conn, $usuario_tipo, $usuario_id, $chat_key) {
    if (strpos($chat_key, '-') === false) return false;
    list($tipo2, $id2) = explode('-', $chat_key, 2);
    $tipo2 = $conn->real_escape_string($tipo2);
    $id2 = intval($id2);

    $sql = "
        SELECT m.*,
               COALESCE(adm.usuario, prof.nome, al.nome) AS remetente_nome,
               COALESCE(adm2.usuario, prof2.nome, al2.nome) AS destinatario_nome
        FROM mensagens m
        LEFT JOIN admins adm ON m.remetente_tipo = 'admin' AND m.remetente_id = adm.id
        LEFT JOIN professores prof ON m.remetente_tipo = 'professor' AND m.remetente_id = prof.id
        LEFT JOIN alunos al ON m.remetente_tipo = 'aluno' AND m.remetente_id = al.id
        LEFT JOIN admins adm2 ON m.destinatario_tipo = 'admin' AND m.destinatario_id = adm2.id
        LEFT JOIN professores prof2 ON m.destinatario_tipo = 'professor' AND m.destinatario_id = prof2.id
        LEFT JOIN alunos al2 ON m.destinatario_tipo = 'aluno' AND m.destinatario_id = al2.id
        WHERE (
            (m.remetente_id = ? AND m.remetente_tipo = ? AND m.destinatario_id = ? AND m.destinatario_tipo = ?)
            OR
            (m.remetente_id = ? AND m.remetente_tipo = ? AND m.destinatario_id = ? AND m.destinatario_tipo = ?)
        )
        ORDER BY m.created_at ASC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param("isisisis", $usuario_id, $usuario_tipo, $id2, $tipo2, $id2, $tipo2, $usuario_id, $usuario_tipo);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();
    return $res;
}

// Exibir chat por padrão, mas permite listar/enviadas/caixa antiga
$chat_key = $_GET['chat'] ?? '';
$chats = buscarConversas($conn, $usuario_tipo, $usuario_id);
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
        .container { max-width: 900px; margin: 30px auto; background: #fff; padding: 30px 40px; border-radius: 10px; box-shadow: 0 4px 24px #0001; }
        .top-actions { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
        .chat-sidebar { width: 240px; background: #f0f3f7; border-radius: 8px; padding: 18px 8px; margin-right: 30px; display: flex; flex-direction: column; gap: 16px;}
        .chat-list { list-style: none; padding: 0; margin: 0;}
        .chat-list li { padding: 9px 14px; border-radius: 7px; cursor: pointer; font-size: 1.05em; color: #007bff; transition: background 0.2s;}
        .chat-list li.active, .chat-list li:hover { background: #e6f0fa; font-weight: 500;}
        .chat-content { flex: 1; background: #f9fafb; border-radius: 8px; padding: 18px 22px;}
        .chat-header { font-weight: bold; color: #146c43; margin-bottom: 8px;}
        .chat-messages { max-height: 360px; overflow-y: auto; margin-bottom: 18px;}
        .chat-msg { margin-bottom: 14px; padding: 9px 14px; border-radius: 7px; background: #fff;}
        .chat-msg.me { background: #e6f0fa; text-align: right;}
        .chat-msg .chat-meta { font-size: 0.9em; color: #888;}
        .chat-form { display: flex; gap: 10px; margin-top: 12px;}
        .chat-form textarea { flex: 1; border-radius: 6px; border: 1px solid #cfd8dc; padding: 7px; }
        .chat-form button { background: #007bff; color: #fff; border: none; padding: 10px 26px; border-radius: 6px; font-size: 1.1em; cursor: pointer; }
        .chat-form button:hover { background: #0056b3;}
        .btn-voltar { background:#6b7280;color:#fff;padding:8px 12px;border-radius:6px;text-decoration:none; }
        @media (max-width: 800px) { .container { padding: 10px; } .chat-sidebar { width:100%; margin-right:0; } .chat-content{padding:10px;} }
        .msg-sucesso, .msg-erro { margin-bottom: 20px; padding: 12px; border-radius: 6px; font-size: 1em; }
        .msg-sucesso { background: #d1f5d9; color: #146c43; border: 1px solid #bff0c6; }
        .msg-erro { background: #ffd6d6; color: #961514; border: 1px solid #f5c2c7; }
    </style>
    <script>
        function abrirChat(chatkey) {
            window.location.href = 'comunicacao.php?chat=' + encodeURIComponent(chatkey);
        }
        function goBack() {
            // If user is admin or professor, go to admin dashboard; otherwise go to index
            var userType = '<?= addslashes($usuario_tipo) ?>';
            if (userType === 'admin' || userType === 'professor') {
                window.location.href = 'dashboard_admin.php';
            } else {
                window.location.href = 'index.php';
            }
        }
    </script>
</head>
<body>
<div class="container" style="display:flex;gap:30px;flex-direction:column;">
    <div class="top-actions">
        <div>
            <h1 style="margin:0">Comunicação Interna</h1>
            <div style="color:#666;font-size:0.95em;margin-top:6px;">Mensagens não lidas: <strong><?= intval($mensagens_nao_lidas) ?></strong></div>
        </div>
        <div>
            <button class="btn-voltar" onclick="goBack()">← Voltar</button>
        </div>
    </div>

    <div style="display:flex;gap:30px;">
        <div class="chat-sidebar">
            <h2 style="margin-bottom:10px;">Conversas</h2>

            <?php if (!empty($erros)): ?>
                <div class="msg-erro"><?php echo implode('<br>', array_map('htmlspecialchars', $erros)); ?></div>
            <?php endif; ?>
            <?php if ($msg_sucesso): ?>
                <div class="msg-sucesso"><?php echo htmlspecialchars($msg_sucesso); ?></div>
            <?php endif; ?>

            <ul class="chat-list">
                <?php foreach($chats as $ck):
                    if (strpos($ck, '-') === false) continue;
                    list($tipo2, $id2) = explode('-', $ck, 2);
                    $id2 = intval($id2);
                    $label = htmlspecialchars($ck);
                    $icon = '';
                    if ($tipo2 === 'admin') {
                        $r = $conn->query("SELECT usuario FROM admins WHERE id={$id2}");
                        $label = $r ? htmlspecialchars($r->fetch_assoc()['usuario'] ?? 'Admin') : 'Admin';
                        $icon = "<i class='fa-solid fa-user-shield'></i> ";
                    } elseif ($tipo2 === 'professor') {
                        $r = $conn->query("SELECT nome FROM professores WHERE id={$id2}");
                        $label = $r ? htmlspecialchars($r->fetch_assoc()['nome'] ?? 'Professor') : 'Professor';
                        $icon = "<i class='fa-solid fa-chalkboard-teacher'></i> ";
                    } elseif ($tipo2 === 'aluno') {
                        $r = $conn->query("SELECT nome FROM alunos WHERE id={$id2}");
                        $label = $r ? htmlspecialchars($r->fetch_assoc()['nome'] ?? 'Aluno') : 'Aluno';
                        $icon = "<i class='fa-solid fa-user-graduate'></i> ";
                    }
                ?>
                    <li <?= $ck === $chat_key ? 'class="active"' : '' ?> onclick="abrirChat('<?= htmlspecialchars($ck, ENT_QUOTES) ?>')">
                        <?= $icon . $label ?>
                    </li>
                <?php endforeach; ?>

                <?php
                // Group shortcuts available depending on user type
                if ($usuario_tipo === 'admin') {
                    // Admin: all students, turmas, professores
                    ?>
                    <li <?= $chat_key === 'todos-0' ? 'class="active"' : '' ?> onclick="abrirChat('todos-0')">
                        <i class="fa-solid fa-users"></i> Todos os alunos
                    </li>
                    <?php if ($turmas) { $turmas->data_seek(0); while ($turma = $turmas->fetch_assoc()): ?>
                        <li <?= $chat_key === 'turma-'. $turma['id'] ? 'class="active"' : '' ?> onclick="abrirChat('turma-<?= intval($turma['id']) ?>')">
                            <i class="fa-solid fa-users"></i> Turma <?= htmlspecialchars($turma['nome']) ?>
                        </li>
                    <?php endwhile; } ?>
                    <?php if ($professores) { $professores->data_seek(0); while ($prof = $professores->fetch_assoc()): ?>
                        <li <?= $chat_key === 'professor-'. $prof['id'] ? 'class="active"' : '' ?> onclick="abrirChat('professor-<?= intval($prof['id']) ?>')">
                            <i class="fa-solid fa-chalkboard-teacher"></i> Professor <?= htmlspecialchars($prof['nome']) ?>
                        </li>
                    <?php endwhile; } ?>
                <?php
                } elseif ($usuario_tipo === 'professor') {
                    // Professor: provide "Meus alunos" (professor-<me>) and own turmas + other professors/admins
                    ?>
                    <li <?= $chat_key === 'professor-'. $usuario_id ? 'class="active"' : '' ?> onclick="abrirChat('professor-<?= $usuario_id ?>')">
                        <i class="fa-solid fa-users"></i> Meus alunos
                    </li>
                    <?php if ($turmas) { $turmas->data_seek(0); while ($turma = $turmas->fetch_assoc()): ?>
                        <li <?= $chat_key === 'turma-'. $turma['id'] ? 'class="active"' : '' ?> onclick="abrirChat('turma-<?= intval($turma['id']) ?>')">
                            <i class="fa-solid fa-users"></i> Turma <?= htmlspecialchars($turma['nome']) ?>
                        </li>
                    <?php endwhile; } ?>
                    <?php if ($professores) { $professores->data_seek(0); while ($prof = $professores->fetch_assoc()): ?>
                        <li <?= $chat_key === 'professor-'. $prof['id'] ? 'class="active"' : '' ?> onclick="abrirChat('professor-<?= intval($prof['id']) ?>')">
                            <i class="fa-solid fa-chalkboard-teacher"></i> Professor <?= htmlspecialchars($prof['nome']) ?>
                        </li>
                    <?php endwhile; } ?>
                <?php
                } elseif ($usuario_tipo === 'aluno') {
                    // Student: show their professor(s) and their turma
                    if ($professores) { $professores->data_seek(0); while ($prof = $professores->fetch_assoc()): ?>
                        <li <?= $chat_key === 'professor-'. $prof['id'] ? 'class="active"' : '' ?> onclick="abrirChat('professor-<?= intval($prof['id']) ?>')">
                            <i class="fa-solid fa-chalkboard-teacher"></i> Professor <?= htmlspecialchars($prof['nome']) ?>
                        </li>
                    <?php endwhile; }
                    if ($turmas) { $turmas->data_seek(0); while ($turma = $turmas->fetch_assoc()): ?>
                        <li <?= $chat_key === 'turma-'. $turma['id'] ? 'class="active"' : '' ?> onclick="abrirChat('turma-<?= intval($turma['id']) ?>')">
                            <i class="fa-solid fa-users"></i> Turma <?= htmlspecialchars($turma['nome']) ?>
                        </li>
                    <?php endwhile; } ?>
                <?php } ?>
            </ul>
        </div>

        <div class="chat-content">
            <div class="chat-header">
                <?php
                if ($chat_key) {
                    list($tipo2, $id2) = explode('-', $chat_key, 2);
                    $id2 = intval($id2);
                    if ($tipo2 === 'todos') {
                        echo "<i class='fa-solid fa-users'></i> Todos os alunos";
                    } elseif ($tipo2 === 'turma') {
                        $r = $conn->query("SELECT nome FROM turmas WHERE id={$id2}");
                        echo "<i class='fa-solid fa-users'></i> Turma " . htmlspecialchars($r ? $r->fetch_assoc()['nome'] ?? '' : '');
                    } elseif ($tipo2 === 'professor') {
                        $r = $conn->query("SELECT nome FROM professores WHERE id={$id2}");
                        echo "<i class='fa-solid fa-chalkboard-teacher'></i> Professor " . htmlspecialchars($r ? $r->fetch_assoc()['nome'] ?? '' : '');
                    } elseif ($tipo2 === 'admin') {
                        $r = $conn->query("SELECT usuario FROM admins WHERE id={$id2}");
                        echo "<i class='fa-solid fa-user-shield'></i> " . htmlspecialchars($r ? $r->fetch_assoc()['usuario'] ?? '' : '');
                    } elseif ($tipo2 === 'aluno') {
                        $r = $conn->query("SELECT nome FROM alunos WHERE id={$id2}");
                        echo "<i class='fa-solid fa-user-graduate'></i> " . htmlspecialchars($r ? $r->fetch_assoc()['nome'] ?? '' : '');
                    } else {
                        echo htmlspecialchars($chat_key);
                    }
                } else {
                    echo "Selecione uma conversa";
                }
                ?>
            </div>

            <div class="chat-messages">
                <?php
                if ($chat_key) {
                    if ($chat_key === 'todos-0') {
                        $res = $conn->query("SELECT * FROM mensagens WHERE remetente_id = {$usuario_id} AND remetente_tipo = '{$conn->real_escape_string($usuario_tipo)}' AND destinatario_tipo = 'aluno' ORDER BY created_at ASC");
                    } elseif (strpos($chat_key, 'turma-') === 0) {
                        $turmaid = intval(explode('-', $chat_key, 2)[1]);
                        $alunos = $conn->query("SELECT id FROM alunos WHERE turma_id = $turmaid");
                        $ids = [];
                        if ($alunos) { while ($a = $alunos->fetch_assoc()) $ids[] = intval($a['id']); }
                        if (!empty($ids)) {
                            $idsStr = implode(',', $ids);
                            $res = $conn->query("SELECT * FROM mensagens WHERE destinatario_tipo='aluno' AND destinatario_id IN ($idsStr) ORDER BY created_at ASC");
                        } else { $res = false; }
                    } elseif (strpos($chat_key, 'professor-') === 0) {
                        $profid = intval(explode('-', $chat_key, 2)[1]);
                        $alunos = $conn->query("SELECT a.id FROM alunos a JOIN turmas t ON a.turma_id = t.id WHERE t.professor_id = $profid");
                        $ids = [];
                        if ($alunos) { while ($a = $alunos->fetch_assoc()) $ids[] = intval($a['id']); }
                        if (!empty($ids)) {
                            $idsStr = implode(',', $ids);
                            $res = $conn->query("SELECT * FROM mensagens WHERE destinatario_tipo='aluno' AND destinatario_id IN ($idsStr) ORDER BY created_at ASC");
                        } else { $res = false; }
                    } else {
                        $msgs = buscarMensagensChat($conn, $usuario_tipo, $usuario_id, $chat_key);
                        if ($msgs && $msgs->num_rows > 0) {
                            while ($msg = $msgs->fetch_assoc()) {
                                $is_me = ($msg['remetente_id'] == $usuario_id && $msg['remetente_tipo'] == $usuario_tipo);
                                echo "<div class='chat-msg ".($is_me ? "me" : "")."'>";
                                echo "<div class='chat-meta'>" . ($is_me ? "Você" : htmlspecialchars($msg['remetente_nome'])) . " &bull; " . formatar_data_hora_br($msg['created_at']) . "</div>";
                                echo "<div>" . nl2br(htmlspecialchars($msg['mensagem'])) . "</div>";
                                echo "</div>";
                            }
                        } else {
                            echo "<div style='color:#aaa'>Nenhuma mensagem neste chat.</div>";
                        }
                        $res = false;
                    }

                    if (isset($res) && $res && $res->num_rows > 0) {
                        while ($msg = $res->fetch_assoc()) {
                            $is_me = ($msg['remetente_id'] == $usuario_id && $msg['remetente_tipo'] == $usuario_tipo);
                            echo "<div class='chat-msg ".($is_me ? "me" : "")."'>";
                            echo "<div class='chat-meta'>" . ($is_me ? "Você" : "Grupo") . " &bull; " . formatar_data_hora_br($msg['created_at']) . "</div>";
                            echo "<div>" . nl2br(htmlspecialchars($msg['mensagem'])) . "</div>";
                            echo "</div>";
                        }
                    }
                }
                ?>
            </div>

            <?php if ($chat_key): ?>
            <form class="chat-form" method="post" autocomplete="off">
                <input type="hidden" name="acao" value="enviar">
                <?php
                if ($chat_key === 'todos-0') {
                    echo '<input type="hidden" name="envio_tipo" value="todos">';
                    echo '<input type="hidden" name="destinatario_tipo" value="aluno">';
                    echo '<input type="hidden" name="destinatario_id" value="0">';
                } elseif (strpos($chat_key, 'turma-') === 0) {
                    $tid = intval(explode('-', $chat_key, 2)[1]);
                    echo '<input type="hidden" name="envio_tipo" value="turma">';
                    echo '<input type="hidden" name="destinatario_tipo" value="turma">';
                    echo '<input type="hidden" name="destinatario_id" value="' . $tid . '">';
                } elseif (strpos($chat_key, 'professor-') === 0) {
                    $pid = intval(explode('-', $chat_key, 2)[1]);
                    echo '<input type="hidden" name="envio_tipo" value="professor">';
                    echo '<input type="hidden" name="destinatario_tipo" value="professor">';
                    echo '<input type="hidden" name="destinatario_id" value="' . $pid . '">';
                } else {
                    list($tipo2, $id2) = explode('-', $chat_key, 2);
                    echo '<input type="hidden" name="envio_tipo" value="individual">';
                    echo '<input type="hidden" name="destinatario_tipo" value="' . htmlspecialchars($tipo2) . '">';
                    echo '<input type="hidden" name="destinatario_id" value="' . intval($id2) . '">';
                }
                ?>
                <textarea name="mensagem" required rows="2" placeholder="Digite sua mensagem..."></textarea>
                <input type="hidden" name="assunto" value="(chat)">
                <button type="submit"><i class="fa-solid fa-paper-plane"></i> Enviar</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>