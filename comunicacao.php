<?php
session_start();
include "conexao.php";

// Verificar se está logado
if (!isset($_SESSION['tipo']) || !isset($_SESSION['id'])) {
    header("Location: index.php");
    exit;
}

$usuario_tipo = $_SESSION['tipo'];
$usuario_id = $_SESSION['id'];
$acao = $_GET['acao'] ?? 'listar';

// Processar envio de mensagem
if ($_POST && isset($_POST['acao']) && $_POST['acao'] === 'enviar') {
    $dados = limpar_entrada($_POST);
    
    $erros = [];
    if (empty($dados['destinatario_id'])) $erros[] = 'Destinatário é obrigatório';
    if (empty($dados['destinatario_tipo'])) $erros[] = 'Tipo de destinatário é obrigatório';
    if (empty($dados['assunto'])) $erros[] = 'Assunto é obrigatório';
    if (empty($dados['mensagem'])) $erros[] = 'Mensagem é obrigatória';
    
    if (empty($erros)) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO mensagens (remetente_id, remetente_tipo, destinatario_id, destinatario_tipo, assunto, mensagem) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("isisss", 
                $usuario_id, 
                $usuario_tipo,
                $dados['destinatario_id'],
                $dados['destinatario_tipo'],
                $dados['assunto'],
                $dados['mensagem']
            );
            
            if ($stmt->execute()) {
                // Criar notificação para o destinatário
                criar_notificacao($conn, $dados['destinatario_id'], $dados['destinatario_tipo'], 
                    'Nova Mensagem', 
                    'Você recebeu uma nova mensagem: ' . $dados['assunto'], 'info');
                
                $_SESSION['msg_sucesso'] = 'Mensagem enviada com sucesso!';
                header("Location: comunicacao.php");
                exit;
            } else {
                $erros[] = 'Erro ao enviar mensagem';
            }
        } catch (Exception $e) {
            $erros[] = 'Erro interno: ' . $e->getMessage();
        }
    }
}

// Marcar mensagem como lida
if (isset($_GET['marcar_lida']) && $acao === 'ver') {
    $mensagem_id = intval($_GET['id']);
    $stmt = $conn->prepare("UPDATE mensagens SET lida=1, data_leitura=NOW() WHERE id=? AND destinatario_id=? AND destinatario_tipo=?");
    $stmt->bind_param("iis", $mensagem_id, $usuario_id, $usuario_tipo);
    $stmt->execute();
}

// Buscar mensagens conforme ação
$mensagens = null;
$mensagem_detalhes = null;
$destinatarios = [];

if ($acao === 'listar') {
    // Buscar mensagens recebidas
    $stmt = $conn->prepare("
        SELECT m.*, 
               CASE m.remetente_tipo
                   WHEN 'admin' THEN (SELECT nome FROM admins WHERE id = m.remetente_id)
                   WHEN 'professor' THEN (SELECT nome FROM professores WHERE id = m.remetente_id)
                   WHEN 'aluno' THEN (SELECT nome FROM alunos WHERE id = m.remetente_id)
               END as remetente_nome
        FROM mensagens m
        WHERE m.destinatario_id = ? AND m.destinatario_tipo = ?
        ORDER BY m.created_at DESC
    ");
    $stmt->bind_param("is", $usuario_id, $usuario_tipo);
    $stmt->execute();
    $mensagens = $stmt->get_result();
    
} elseif ($acao === 'enviadas') {
    // Buscar mensagens enviadas
    $stmt = $conn->prepare("
        SELECT m.*, 
               CASE m.destinatario_tipo
                   WHEN 'admin' THEN (SELECT nome FROM admins WHERE id = m.destinatario_id)
                   WHEN 'professor' THEN (SELECT nome FROM professores WHERE id = m.destinatario_id)
                   WHEN 'aluno' THEN (SELECT nome FROM alunos WHERE id = m.destinatario_id)
               END as destinatario_nome
        FROM mensagens m
        WHERE m.remetente_id = ? AND m.remetente_tipo = ?
        ORDER BY m.created_at DESC
    ");
    $stmt->bind_param("is", $usuario_id, $usuario_tipo);
    $stmt->execute();
    $mensagens = $stmt->get_result();
    
} elseif ($acao === 'ver') {
    // Ver mensagem específica
    $mensagem_id = intval($_GET['id']);
    $stmt = $conn->prepare("
        SELECT m.*, 
               CASE m.remetente_tipo
                   WHEN 'admin' THEN (SELECT nome FROM admins WHERE id = m.remetente_id)
                   WHEN 'professor' THEN (SELECT nome FROM professores WHERE id = m.remetente_id)
                   WHEN 'aluno' THEN (SELECT nome FROM alunos WHERE id = m.remetente_id)
               END as remetente_nome,
               CASE m.destinatario_tipo
                   WHEN 'admin' THEN (SELECT nome FROM admins WHERE id = m.destinatario_id)
                   WHEN 'professor' THEN (SELECT nome FROM professores WHERE id = m.destinatario_id)
                   WHEN 'aluno' THEN (SELECT nome FROM alunos WHERE id = m.destinatario_id)
               END as destinatario_nome
        FROM mensagens m
        WHERE m.id = ? AND (
            (m.destinatario_id = ? AND m.destinatario_tipo = ?) OR
            (m.remetente_id = ? AND m.remetente_tipo = ?)
        )
    ");
    $stmt->bind_param("iisis", $mensagem_id, $usuario_id, $usuario_tipo, $usuario_id, $usuario_tipo);
    $stmt->execute();
    $mensagem_detalhes = $stmt->get_result()->fetch_assoc();
    
} elseif ($acao === 'nova') {
    // Buscar possíveis destinatários conforme tipo de usuário
    if ($usuario_tipo === 'admin') {
        // Admin pode enviar para todos
        $destinatarios['professores'] = $conn->query("SELECT id, nome FROM professores ORDER BY nome");
        $destinatarios['alunos'] = $conn->query("SELECT id, nome FROM alunos ORDER BY nome");
        $destinatarios['admins'] = $conn->query("SELECT id, nome FROM admins WHERE id != $usuario_id ORDER BY nome");
    } elseif ($usuario_tipo === 'professor') {
        // Professor pode enviar para admin e seus alunos
        $destinatarios['admins'] = $conn->query("SELECT id, nome FROM admins ORDER BY nome");
        $stmt = $conn->prepare("SELECT DISTINCT a.id, a.nome FROM alunos a JOIN turmas t ON a.turma_id = t.id WHERE t.professor_id = ? ORDER BY a.nome");
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
        $destinatarios['alunos'] = $stmt->get_result();
    } elseif ($usuario_tipo === 'aluno') {
        // Aluno pode enviar para admin e seu professor
        $destinatarios['admins'] = $conn->query("SELECT id, nome FROM admins ORDER BY nome");
        $stmt = $conn->prepare("SELECT DISTINCT p.id, p.nome FROM professores p JOIN turmas t ON p.id = t.professor_id JOIN alunos a ON a.turma_id = t.id WHERE a.id = ?");
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
        $destinatarios['professores'] = $stmt->get_result();
    }
}

// Contar mensagens não lidas
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM mensagens WHERE destinatario_id = ? AND destinatario_tipo = ? AND lida = 0");
$stmt->bind_param("is", $usuario_id, $usuario_tipo);
$stmt->execute();
$mensagens_nao_lidas = $stmt->get_result()->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comunicação Interna</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .tab-buttons {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 2px solid #f4f5f7;
        }
        .tab-button {
            padding: 10px 20px;
            background: #f4f5f7;
            border: none;
            cursor: pointer;
            text-decoration: none;
            color: #666;
            margin-right: 5px;
            position: relative;
        }
        .tab-button.active {
            background: #b0b8c1;
            color: white;
        }
        .badge {
            background: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.8em;
            position: absolute;
            top: 5px;
            right: 5px;
        }
        .mensagem-item {
            background: #f9fafb;
            padding: 15px;
            margin: 10px 0;
            border-radius: 6px;
            border-left: 4px solid #b0b8c1;
            cursor: pointer;
            transition: background 0.2s;
        }
        .mensagem-item:hover {
            background: #f1f3f4;
        }
        .mensagem-item.nao-lida {
            border-left-color: #dc3545;
            background: #fff5f5;
        }
        .mensagem-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        .mensagem-remetente {
            font-weight: bold;
            color: #333;
        }
        .mensagem-data {
            color: #666;
            font-size: 0.9em;
        }
        .mensagem-assunto {
            font-size: 1.1em;
            margin-bottom: 5px;
            color: #555;
        }
        .mensagem-preview {
            color: #666;
            font-size: 0.9em;
        }
        .mensagem-detalhes {
            background: #ffffff;
            padding: 25px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
            margin: 20px 0;
        }
        .form-row {
            display: flex;
            gap: 15px;
            align-items: start;
        }
        .form-row > div {
            flex: 1;
        }
        .msg-sucesso {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            border: 1px solid #c3e6cb;
        }
        .msg-erro {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            border: 1px solid #f5c6cb;
        }
        .status-lida {
            color: #28a745;
            font-size: 0.9em;
        }
        .status-nao-lida {
            color: #dc3545;
            font-size: 0.9em;
            font-weight: bold;
        }
    </style>
    <script>
        function verMensagem(id) {
            window.location.href = 'comunicacao.php?acao=ver&id=' + id + '&marcar_lida=1';
        }
        
        function atualizarDestinatarios() {
            const select = document.getElementById('destinatario_tipo');
            const destinatarioId = document.getElementById('destinatario_id');
            const tipo = select.value;
            
            // Limpar opções
            destinatarioId.innerHTML = '<option value="">Selecione...</option>';
            
            // Adicionar opções baseadas no tipo
            <?php if (!empty($destinatarios)): ?>
                <?php foreach ($destinatarios as $tipo_dest => $lista): ?>
                    if (tipo === '<?= $tipo_dest ?>') {
                        <?php while ($dest = $lista->fetch_assoc()): ?>
                            destinatarioId.innerHTML += '<option value="<?= $dest['id'] ?>"><?= htmlspecialchars($dest['nome']) ?></option>';
                        <?php endwhile; ?>
                    }
                <?php endforeach; ?>
            <?php endif; ?>
        }
    </script>
</head>
<body>
<div class="container">
    <h1>Comunicação Interna</h1>
    
    <?php if (isset($_SESSION['msg_sucesso'])): ?>
        <div class="msg-sucesso"><?= $_SESSION['msg_sucesso'] ?></div>
        <?php unset($_SESSION['msg_sucesso']); ?>
    <?php endif; ?>
    
    <?php if (!empty($erros)): ?>
        <div class="msg-erro">
            <ul style="margin: 0; padding-left: 20px;">
                <?php foreach ($erros as $erro): ?>
                    <li><?= htmlspecialchars($erro) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <!-- Abas de navegação -->
    <div class="tab-buttons">
        <a href="comunicacao.php?acao=listar" class="tab-button <?= $acao === 'listar' ? 'active' : '' ?>">
            Caixa de Entrada
            <?php if ($mensagens_nao_lidas > 0): ?>
                <span class="badge"><?= $mensagens_nao_lidas ?></span>
            <?php endif; ?>
        </a>
        <a href="comunicacao.php?acao=enviadas" class="tab-button <?= $acao === 'enviadas' ? 'active' : '' ?>">
            Mensagens Enviadas
        </a>
        <a href="comunicacao.php?acao=nova" class="tab-button <?= $acao === 'nova' ? 'active' : '' ?>">
            Nova Mensagem
        </a>
    </div>
    
    <?php if ($acao === 'listar' || $acao === 'enviadas'): ?>
        <!-- Lista de mensagens -->
        <h2><?= $acao === 'listar' ? 'Mensagens Recebidas' : 'Mensagens Enviadas' ?></h2>
        
        <?php if ($mensagens && $mensagens->num_rows > 0): ?>
            <?php while ($msg = $mensagens->fetch_assoc()): ?>
                <div class="mensagem-item <?= !$msg['lida'] && $acao === 'listar' ? 'nao-lida' : '' ?>" 
                     onclick="verMensagem(<?= $msg['id'] ?>)">
                    <div class="mensagem-header">
                        <span class="mensagem-remetente">
                            <?php if ($acao === 'listar'): ?>
                                De: <?= htmlspecialchars($msg['remetente_nome']) ?>
                            <?php else: ?>
                                Para: <?= htmlspecialchars($msg['destinatario_nome']) ?>
                            <?php endif; ?>
                        </span>
                        <span class="mensagem-data"><?= formatar_data_hora_br($msg['created_at']) ?></span>
                    </div>
                    <div class="mensagem-assunto"><?= htmlspecialchars($msg['assunto']) ?></div>
                    <div class="mensagem-preview"><?= htmlspecialchars(substr($msg['mensagem'], 0, 100)) ?>...</div>
                    <?php if ($acao === 'listar'): ?>
                        <div style="margin-top: 8px;">
                            <?php if ($msg['lida']): ?>
                                <span class="status-lida">✓ Lida em <?= formatar_data_hora_br($msg['data_leitura']) ?></span>
                            <?php else: ?>
                                <span class="status-nao-lida">● Não lida</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>Nenhuma mensagem encontrada.</p>
        <?php endif; ?>
        
    <?php elseif ($acao === 'ver' && $mensagem_detalhes): ?>
        <!-- Detalhes da mensagem -->
        <div class="mensagem-detalhes">
            <h3><?= htmlspecialchars($mensagem_detalhes['assunto']) ?></h3>
            
            <div style="margin: 15px 0; padding: 10px; background: #f8f9fa; border-radius: 4px;">
                <strong>De:</strong> <?= htmlspecialchars($mensagem_detalhes['remetente_nome']) ?><br>
                <strong>Para:</strong> <?= htmlspecialchars($mensagem_detalhes['destinatario_nome']) ?><br>
                <strong>Data:</strong> <?= formatar_data_hora_br($mensagem_detalhes['created_at']) ?>
                <?php if ($mensagem_detalhes['lida'] && $mensagem_detalhes['data_leitura']): ?>
                    <br><strong>Lida em:</strong> <?= formatar_data_hora_br($mensagem_detalhes['data_leitura']) ?>
                <?php endif; ?>
            </div>
            
            <div style="margin: 20px 0; padding: 15px; background: #ffffff; border: 1px solid #dee2e6; border-radius: 4px;">
                <?= nl2br(htmlspecialchars($mensagem_detalhes['mensagem'])) ?>
            </div>
            
            <?php if ($mensagem_detalhes['remetente_id'] != $usuario_id || $mensagem_detalhes['remetente_tipo'] != $usuario_tipo): ?>
                <a href="comunicacao.php?acao=nova&responder=<?= $mensagem_detalhes['id'] ?>"><button>Responder</button></a>
            <?php endif; ?>
        </div>
        
    <?php elseif ($acao === 'nova'): ?>
        <!-- Nova mensagem -->
        <h2>Nova Mensagem</h2>
        
        <form method="post">
            <input type="hidden" name="acao" value="enviar">
            
            <div class="form-row">
                <div>
                    <label>Tipo de Destinatário</label>
                    <select name="destinatario_tipo" id="destinatario_tipo" onchange="atualizarDestinatarios()" required>
                        <option value="">Selecione o tipo</option>
                        <?php if (!empty($destinatarios['admins'])): ?>
                            <option value="admin">Administrador</option>
                        <?php endif; ?>
                        <?php if (!empty($destinatarios['professores'])): ?>
                            <option value="professor">Professor</option>
                        <?php endif; ?>
                        <?php if (!empty($destinatarios['alunos'])): ?>
                            <option value="aluno">Aluno</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div>
                    <label>Destinatário</label>
                    <select name="destinatario_id" id="destinatario_id" required>
                        <option value="">Primeiro selecione o tipo</option>
                    </select>
                </div>
            </div>
            
            <div>
                <label>Assunto</label>
                <input type="text" name="assunto" required maxlength="255">
            </div>
            
            <div>
                <label>Mensagem</label>
                <textarea name="mensagem" rows="8" required placeholder="Digite sua mensagem..."></textarea>
            </div>
            
            <button type="submit">Enviar Mensagem</button>
        </form>
        
    <?php elseif ($acao === 'ver'): ?>
        <p>Mensagem não encontrada ou você não tem permissão para visualizá-la.</p>
    <?php endif; ?>
    
    <div style="margin-top: 30px;">
        <a href="<?= 
            $usuario_tipo === 'admin' ? 'dashboard_admin.php' : 
            ($usuario_tipo === 'professor' ? 'dashboard_professor.php' : 'dashboard_aluno.php') 
        ?>"><button>Voltar ao Dashboard</button></a>
    </div>
</div>
</body>
</html>