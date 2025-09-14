<?php
session_start();
include "conexao.php";

// Verificar se é admin
if (!isset($_SESSION['tipo']) || $_SESSION['tipo'] !== "admin") {
    header("Location: index.php");
    exit;
}

$acao = $_GET['acao'] ?? 'listar';
$tipo = $_GET['tipo'] ?? 'alunos';
$id = intval($_GET['id'] ?? 0);

// Validar tipo de usuário
$tipos_validos = ['alunos', 'professores', 'admins'];
if (!in_array($tipo, $tipos_validos)) {
    header("Location: gerenciar_usuarios.php");
    exit;
}

// Processar ações
if ($_POST) {
    switch ($acao) {
        case 'editar':
            editar_usuario($conn, $tipo, $id, $_POST);
            break;
        case 'deletar':
            deletar_usuario($conn, $tipo, $id);
            break;
        case 'resetar_senha':
            resetar_senha($conn, $tipo, $id);
            break;
    }
}

// Funções
function editar_usuario($conn, $tipo, $id, $dados) {
    $dados = limpar_entrada($dados);
    
    try {
        $conn->begin_transaction();
        
        if ($tipo === 'alunos') {
            $stmt = $conn->prepare("UPDATE alunos SET nome=?, email=?, telefone=?, endereco=?, turma_id=? WHERE id=?");
            $stmt->bind_param("ssssii", 
                $dados['nome'], 
                $dados['email'], 
                $dados['telefone'], 
                $dados['endereco'], 
                $dados['turma_id'], 
                $id
            );
        } elseif ($tipo === 'professores') {
            $stmt = $conn->prepare("UPDATE professores SET nome=?, email=?, telefone=? WHERE id=?");
            $stmt->bind_param("sssi", 
                $dados['nome'], 
                $dados['email'], 
                $dados['telefone'], 
                $id
            );
        } else { // admins
            $stmt = $conn->prepare("UPDATE admins SET nome=? WHERE id=?");
            $stmt->bind_param("si", $dados['nome'], $id);
        }
        
        $stmt->execute();
        
        // Atualizar senha se fornecida
        if (!empty($dados['nova_senha'])) {
            $senha_hash = criptografar_senha($dados['nova_senha']);
            $stmt = $conn->prepare("UPDATE $tipo SET senha=? WHERE id=?");
            $stmt->bind_param("si", $senha_hash, $id);
            $stmt->execute();
        }
        
        $conn->commit();
        $_SESSION['msg_sucesso'] = 'Usuário atualizado com sucesso!';
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['msg_erro'] = 'Erro ao atualizar usuário: ' . $e->getMessage();
    }
    
    header("Location: gerenciar_usuarios.php?acao=ver&tipo=$tipo&id=$id");
    exit;
}

function deletar_usuario($conn, $tipo, $id) {
    try {
        $conn->begin_transaction();
        
        // Verificar se pode deletar (sem relacionamentos críticos)
        if ($tipo === 'professores') {
            $turmas = $conn->prepare("SELECT COUNT(*) as total FROM turmas WHERE professor_id=?");
            $turmas->bind_param("i", $id);
            $turmas->execute();
            $resultado = $turmas->get_result()->fetch_assoc();
            
            if ($resultado['total'] > 0) {
                $_SESSION['msg_erro'] = 'Não é possível deletar professor com turmas associadas.';
                header("Location: gerenciar_usuarios.php?acao=ver&tipo=$tipo&id=$id");
                exit;
            }
        }
        
        if ($tipo === 'alunos') {
            // Deletar registros relacionados
            $conn->prepare("DELETE FROM notas_faltas WHERE aluno_id=?")->execute([$id]);
            $conn->prepare("DELETE FROM respostas WHERE aluno_id=?")->execute([$id]);
            $conn->prepare("DELETE FROM frequencia WHERE aluno_id=?")->execute([$id]);
        }
        
        $stmt = $conn->prepare("DELETE FROM $tipo WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        $conn->commit();
        $_SESSION['msg_sucesso'] = 'Usuário deletado com sucesso!';
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['msg_erro'] = 'Erro ao deletar usuário: ' . $e->getMessage();
    }
    
    header("Location: gerenciar_usuarios.php?tipo=$tipo");
    exit;
}

function resetar_senha($conn, $tipo, $id) {
    $nova_senha = gerar_senha(8);
    $senha_hash = criptografar_senha($nova_senha);
    
    $stmt = $conn->prepare("UPDATE $tipo SET senha=? WHERE id=?");
    $stmt->bind_param("si", $senha_hash, $id);
    
    if ($stmt->execute()) {
        $_SESSION['msg_sucesso'] = "Senha resetada com sucesso! Nova senha: <strong>$nova_senha</strong>";
    } else {
        $_SESSION['msg_erro'] = 'Erro ao resetar senha.';
    }
    
    header("Location: gerenciar_usuarios.php?acao=ver&tipo=$tipo&id=$id");
    exit;
}

// Buscar dados conforme a ação
$dados = null;
$turmas = null;

if ($acao === 'ver' || $acao === 'editar') {
    if ($tipo === 'alunos') {
        $stmt = $conn->prepare("SELECT a.*, t.nome as turma_nome FROM alunos a LEFT JOIN turmas t ON t.id=a.turma_id WHERE a.id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $dados = $stmt->get_result()->fetch_assoc();
        
        // Buscar turmas para edição
        if ($acao === 'editar') {
            $turmas = $conn->query("SELECT id, nome FROM turmas ORDER BY nome");
        }
    } elseif ($tipo === 'professores') {
        $stmt = $conn->prepare("SELECT * FROM professores WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $dados = $stmt->get_result()->fetch_assoc();
    } else {
        $stmt = $conn->prepare("SELECT * FROM admins WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $dados = $stmt->get_result()->fetch_assoc();
    }
    
    if (!$dados) {
        $_SESSION['msg_erro'] = 'Usuário não encontrado.';
        header("Location: gerenciar_usuarios.php?tipo=$tipo");
        exit;
    }
}

// Listar usuários
$usuarios = null;
if ($acao === 'listar') {
    if ($tipo === 'alunos') {
        $usuarios = $conn->query("SELECT a.id, a.matricula, a.nome, a.email, t.nome as turma FROM alunos a LEFT JOIN turmas t ON t.id=a.turma_id ORDER BY a.nome");
    } elseif ($tipo === 'professores') {
        $usuarios = $conn->query("SELECT id, nome, email, telefone FROM professores ORDER BY nome");
    } else {
        $usuarios = $conn->query("SELECT id, nome, usuario FROM admins ORDER BY nome");
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários</title>
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
        }
        .tab-button.active {
            background: #b0b8c1;
            color: white;
        }
        .user-card {
            background: #f9fafb;
            padding: 15px;
            margin: 10px 0;
            border-radius: 6px;
            border-left: 4px solid #b0b8c1;
        }
        .actions {
            margin-top: 10px;
        }
        .actions a {
            margin-right: 10px;
            display: inline-block;
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
        .form-row {
            display: flex;
            gap: 15px;
        }
        .form-row > div {
            flex: 1;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Gerenciar Usuários</h1>
    
    <!-- Mensagens -->
    <?php if (isset($_SESSION['msg_sucesso'])): ?>
        <div class="msg-sucesso"><?= $_SESSION['msg_sucesso'] ?></div>
        <?php unset($_SESSION['msg_sucesso']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['msg_erro'])): ?>
        <div class="msg-erro"><?= $_SESSION['msg_erro'] ?></div>
        <?php unset($_SESSION['msg_erro']); ?>
    <?php endif; ?>
    
    <!-- Abas -->
    <div class="tab-buttons">
        <a href="?tipo=alunos" class="tab-button <?= $tipo === 'alunos' ? 'active' : '' ?>">Alunos</a>
        <a href="?tipo=professores" class="tab-button <?= $tipo === 'professores' ? 'active' : '' ?>">Professores</a>
        <a href="?tipo=admins" class="tab-button <?= $tipo === 'admins' ? 'active' : '' ?>">Administradores</a>
    </div>
    
    <?php if ($acao === 'listar'): ?>
        <h2>Lista de <?= ucfirst($tipo) ?></h2>
        
        <?php if ($usuarios && $usuarios->num_rows > 0): ?>
            <?php while ($usuario = $usuarios->fetch_assoc()): ?>
                <div class="user-card">
                    <h3><?= htmlspecialchars($usuario['nome']) ?></h3>
                    <?php if ($tipo === 'alunos'): ?>
                        <p><strong>Matrícula:</strong> <?= htmlspecialchars($usuario['matricula']) ?></p>
                        <p><strong>Turma:</strong> <?= htmlspecialchars($usuario['turma'] ?? 'Não definida') ?></p>
                        <p><strong>Email:</strong> <?= htmlspecialchars($usuario['email'] ?? 'Não informado') ?></p>
                    <?php elseif ($tipo === 'professores'): ?>
                        <p><strong>Email:</strong> <?= htmlspecialchars($usuario['email'] ?? 'Não informado') ?></p>
                        <p><strong>Telefone:</strong> <?= htmlspecialchars($usuario['telefone'] ?? 'Não informado') ?></p>
                    <?php else: ?>
                        <p><strong>Usuário:</strong> <?= htmlspecialchars($usuario['usuario']) ?></p>
                    <?php endif; ?>
                    
                    <div class="actions">
                        <a href="?acao=ver&tipo=<?= $tipo ?>&id=<?= $usuario['id'] ?>"><button>Ver Detalhes</button></a>
                        <a href="?acao=editar&tipo=<?= $tipo ?>&id=<?= $usuario['id'] ?>"><button>Editar</button></a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>Nenhum usuário encontrado.</p>
        <?php endif; ?>
        
    <?php elseif ($acao === 'ver'): ?>
        <h2>Detalhes do <?= ucfirst(substr($tipo, 0, -1)) ?></h2>
        
        <div class="user-card">
            <h3><?= htmlspecialchars($dados['nome']) ?></h3>
            
            <?php if ($tipo === 'alunos'): ?>
                <p><strong>Matrícula:</strong> <?= htmlspecialchars($dados['matricula']) ?></p>
                <p><strong>Usuário:</strong> <?= htmlspecialchars($dados['usuario']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($dados['email'] ?? 'Não informado') ?></p>
                <p><strong>Telefone:</strong> <?= htmlspecialchars($dados['telefone'] ?? 'Não informado') ?></p>
                <p><strong>Turma:</strong> <?= htmlspecialchars($dados['turma_nome'] ?? 'Não definida') ?></p>
                <p><strong>Endereço:</strong> <?= htmlspecialchars($dados['endereco'] ?? 'Não informado') ?></p>
                <p><strong>Cadastrado em:</strong> <?= formatar_data_hora_br($dados['created_at']) ?></p>
            <?php elseif ($tipo === 'professores'): ?>
                <p><strong>Usuário:</strong> <?= htmlspecialchars($dados['usuario']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($dados['email'] ?? 'Não informado') ?></p>
                <p><strong>Telefone:</strong> <?= htmlspecialchars($dados['telefone'] ?? 'Não informado') ?></p>
                <p><strong>Cadastrado em:</strong> <?= formatar_data_hora_br($dados['created_at']) ?></p>
            <?php else: ?>
                <p><strong>Usuário:</strong> <?= htmlspecialchars($dados['usuario']) ?></p>
                <p><strong>Cadastrado em:</strong> <?= formatar_data_hora_br($dados['created_at']) ?></p>
            <?php endif; ?>
            
            <div class="actions">
                <a href="?acao=editar&tipo=<?= $tipo ?>&id=<?= $id ?>"><button>Editar</button></a>
                <form method="post" style="display: inline;" onsubmit="return confirm('Deseja resetar a senha?')">
                    <input type="hidden" name="acao" value="resetar_senha">
                    <button type="submit">Resetar Senha</button>
                </form>
                <form method="post" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja deletar?')">
                    <input type="hidden" name="acao" value="deletar">
                    <button type="submit" style="background: #dc3545;">Deletar</button>
                </form>
            </div>
        </div>
        
    <?php elseif ($acao === 'editar'): ?>
        <h2>Editar <?= ucfirst(substr($tipo, 0, -1)) ?></h2>
        
        <form method="post">
            <div class="form-row">
                <div>
                    <label>Nome Completo</label>
                    <input type="text" name="nome" value="<?= htmlspecialchars($dados['nome']) ?>" required>
                </div>
            </div>
            
            <?php if ($tipo === 'alunos'): ?>
                <div class="form-row">
                    <div>
                        <label>Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($dados['email'] ?? '') ?>">
                    </div>
                    <div>
                        <label>Telefone</label>
                        <input type="text" name="telefone" value="<?= htmlspecialchars($dados['telefone'] ?? '') ?>">
                    </div>
                </div>
                <div>
                    <label>Turma</label>
                    <select name="turma_id">
                        <option value="">Selecione uma turma</option>
                        <?php while ($turma = $turmas->fetch_assoc()): ?>
                            <option value="<?= $turma['id'] ?>" <?= $turma['id'] == $dados['turma_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($turma['nome']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label>Endereço</label>
                    <textarea name="endereco"><?= htmlspecialchars($dados['endereco'] ?? '') ?></textarea>
                </div>
            <?php elseif ($tipo === 'professores'): ?>
                <div class="form-row">
                    <div>
                        <label>Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($dados['email'] ?? '') ?>">
                    </div>
                    <div>
                        <label>Telefone</label>
                        <input type="text" name="telefone" value="<?= htmlspecialchars($dados['telefone'] ?? '') ?>">
                    </div>
                </div>
            <?php endif; ?>
            
            <div>
                <label>Nova Senha (deixe em branco para manter a atual)</label>
                <input type="password" name="nova_senha" placeholder="Digite nova senha ou deixe em branco">
            </div>
            
            <button type="submit">Salvar Alterações</button>
        </form>
    <?php endif; ?>
    
    <a href="?tipo=<?= $tipo ?>"><button>Voltar à Lista</button></a>
    <a href="dashboard_admin.php"><button>Dashboard</button></a>
</div>
</body>
</html>