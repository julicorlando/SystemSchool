<?php
session_start();
include "conexao.php";
include_once "funcoes.php";

// Verifica se é admin
if (!isset($_SESSION['tipo']) || $_SESSION['tipo'] !== "admin") {
    header("Location: index.php");
    exit;
}

$acao = $_GET['acao'] ?? 'listar';
$tipo = $_GET['tipo'] ?? 'alunos';
$id = intval($_GET['id'] ?? 0);

// Filtro por turma
$turma_filtro = intval($_GET['turma_filtro'] ?? 0);

// Valida tipo de usuário
$tipos_validos = ['alunos', 'professores', 'admins'];
if (!in_array($tipo, $tipos_validos)) {
    header("Location: gerenciar_usuarios.php");
    exit;
}

// Processa ações
if ($_POST) {
    switch ($_POST['acao'] ?? $acao) {
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
            $stmt = $conn->prepare("UPDATE alunos SET nome=?, usuario=?, matricula=?, turma_id=? WHERE id=?");
            $stmt->bind_param("sssii",
                $dados['nome'],
                $dados['usuario'],
                $dados['matricula'],
                $dados['turma_id'],
                $id
            );
        } elseif ($tipo === 'professores') {
            $stmt = $conn->prepare("UPDATE professores SET nome=?, usuario=? WHERE id=?");
            $stmt->bind_param("ssi",
                $dados['nome'],
                $dados['usuario'],
                $id
            );
        } else { // admins
            $stmt = $conn->prepare("UPDATE admins SET usuario=? WHERE id=?");
            $stmt->bind_param("si", $dados['usuario'], $id);
        }

        $stmt->execute();

        // Atualiza senha se fornecida
        if (!empty($dados['nova_senha'])) {
            $senha = $dados['nova_senha']; // senha não criptografada!
            $stmt = $conn->prepare("UPDATE $tipo SET senha=? WHERE id=?");
            $stmt->bind_param("si", $senha, $id);
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
            $stmt1 = $conn->prepare("DELETE FROM notas_faltas WHERE aluno_id=?");
            $stmt1->bind_param("i", $id);
            $stmt1->execute();

            $stmt2 = $conn->prepare("DELETE FROM respostas WHERE aluno_id=?");
            $stmt2->bind_param("i", $id);
            $stmt2->execute();

            $stmt3 = $conn->prepare("DELETE FROM frequencia WHERE aluno_id=?");
            $stmt3->bind_param("i", $id);
            $stmt3->execute();
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
    $senha = $nova_senha; // senha não criptografada!

    $stmt = $conn->prepare("UPDATE $tipo SET senha=? WHERE id=?");
    $stmt->bind_param("si", $senha, $id);

    if ($stmt->execute()) {
        $_SESSION['msg_sucesso'] = "Senha resetada com sucesso! Nova senha: <strong>$nova_senha</strong>";
    } else {
        $_SESSION['msg_erro'] = 'Erro ao resetar senha.';
    }

    header("Location: gerenciar_usuarios.php?acao=ver&tipo=$tipo&id=$id");
    exit;
}

// Busca dados conforme a ação
$dados = null;
$turmas = null;

// Busca lista de turmas para filtro
$lista_turmas = $conn->query("SELECT id, nome FROM turmas ORDER BY nome");

if ($acao === 'ver' || $acao === 'editar') {
    if ($tipo === 'alunos') {
        $stmt = $conn->prepare("SELECT a.*, t.nome as turma_nome FROM alunos a LEFT JOIN turmas t ON t.id=a.turma_id WHERE a.id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $dados = $stmt->get_result()->fetch_assoc();

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
        if ($turma_filtro > 0) {
            $usuarios = $conn->query("SELECT a.id, a.nome, a.usuario, a.matricula, a.turma_id, t.nome as turma FROM alunos a LEFT JOIN turmas t ON t.id=a.turma_id WHERE a.turma_id = $turma_filtro ORDER BY a.nome");
        } else {
            $usuarios = $conn->query("SELECT a.id, a.nome, a.usuario, a.matricula, a.turma_id, t.nome as turma FROM alunos a LEFT JOIN turmas t ON t.id=a.turma_id ORDER BY a.nome");
        }
    } elseif ($tipo === 'professores') {
        $usuarios = $conn->query("SELECT id, nome, usuario FROM professores ORDER BY nome");
    } else { // admins
        $usuarios = $conn->query("SELECT id, usuario FROM admins ORDER BY usuario");
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Usuários</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .tab-buttons { display: flex; margin-bottom: 20px; border-bottom: 2px solid #f4f5f7; }
        .tab-button { padding: 10px 20px; background: #f4f5f7; border: none; cursor: pointer; text-decoration: none; color: #666; margin-right: 5px; }
        .tab-button.active { background: #b0b8c1; color: white; }
        .user-card { background: #f9fafb; padding: 15px; margin: 10px 0; border-radius: 6px; border-left: 4px solid #b0b8c1; }
        .actions { margin-top: 10px; }
        .actions a { margin-right: 10px; display: inline-block; }
        .msg-sucesso { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin: 10px 0; border: 1px solid #c3e6cb; }
        .msg-erro { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin: 10px 0; border: 1px solid #f5c6cb; }
        .form-row { display: flex; gap: 15px; }
        .form-row > div { flex: 1; }
        .turma-filtro-form { margin-bottom: 20px;}
    </style>
</head>
<body>
<div class="container">
    <h1>Gerenciar Usuários</h1>

    <?php if (isset($_SESSION['msg_sucesso'])): ?>
        <div class="msg-sucesso"><?= $_SESSION['msg_sucesso'] ?></div>
        <?php unset($_SESSION['msg_sucesso']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['msg_erro'])): ?>
        <div class="msg-erro"><?= $_SESSION['msg_erro'] ?></div>
        <?php unset($_SESSION['msg_erro']); ?>
    <?php endif; ?>

    <div class="tab-buttons">
        <a href="?tipo=alunos" class="tab-button <?= $tipo === 'alunos' ? 'active' : '' ?>">Alunos</a>
        <a href="?tipo=professores" class="tab-button <?= $tipo === 'professores' ? 'active' : '' ?>">Professores</a>
        <a href="?tipo=admins" class="tab-button <?= $tipo === 'admins' ? 'active' : '' ?>">Administradores</a>
    </div>

    <?php if ($acao === 'listar' && $tipo === 'alunos'): ?>
        <form method="get" class="turma-filtro-form">
            <input type="hidden" name="tipo" value="alunos">
            <label for="turma_filtro">Visualizar por turma:</label>
            <select name="turma_filtro" id="turma_filtro" onchange="this.form.submit()">
                <option value="0">Todas as turmas</option>
                <?php if ($lista_turmas) while ($t = $lista_turmas->fetch_assoc()): ?>
                    <option value="<?= $t['id'] ?>" <?= $turma_filtro == $t['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['nome']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </form>
    <?php endif; ?>

    <?php if ($acao === 'listar'): ?>
        <h2>Lista de <?= ucfirst($tipo) ?></h2>
        <?php if ($usuarios && $usuarios->num_rows > 0): ?>
            <?php while ($usuario = $usuarios->fetch_assoc()): ?>
                <div class="user-card">
                    <?php if ($tipo === 'alunos'): ?>
                        <h3><?= htmlspecialchars($usuario['nome']) ?> (<?= htmlspecialchars($usuario['usuario']) ?>)</h3>
                        <p><strong>Matrícula:</strong> <?= htmlspecialchars($usuario['matricula']) ?></p>
                        <p><strong>Turma:</strong> <?= htmlspecialchars($usuario['turma'] ?? 'Não definida') ?></p>
                    <?php elseif ($tipo === 'professores'): ?>
                        <h3><?= htmlspecialchars($usuario['nome']) ?> (<?= htmlspecialchars($usuario['usuario']) ?>)</h3>
                    <?php else: ?>
                        <h3><?= htmlspecialchars($usuario['usuario']) ?></h3>
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
            <?php if ($tipo === 'alunos'): ?>
                <h3><?= htmlspecialchars($dados['nome']) ?> (<?= htmlspecialchars($dados['usuario']) ?>)</h3>
                <p><strong>Matrícula:</strong> <?= htmlspecialchars($dados['matricula']) ?></p>
                <p><strong>Turma:</strong> <?= htmlspecialchars($dados['turma_nome'] ?? 'Não definida') ?></p>
            <?php elseif ($tipo === 'professores'): ?>
                <h3><?= htmlspecialchars($dados['nome']) ?> (<?= htmlspecialchars($dados['usuario']) ?>)</h3>
            <?php else: ?>
                <h3><?= htmlspecialchars($dados['usuario']) ?></h3>
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
            <input type="hidden" name="acao" value="editar">
            <div class="form-row">
                <div>
                    <label>Nome Completo</label>
                    <input type="text" name="nome" value="<?= htmlspecialchars($dados['nome'] ?? '') ?>" required>
                </div>
                <div>
                    <label>Usuário</label>
                    <input type="text" name="usuario" value="<?= htmlspecialchars($dados['usuario'] ?? '') ?>" required>
                </div>
                <?php if ($tipo === 'alunos'): ?>
                <div>
                    <label>Matrícula</label>
                    <input type="text" name="matricula" value="<?= htmlspecialchars($dados['matricula'] ?? '') ?>" required>
                </div>
                <div>
                    <label>Turma</label>
                    <select name="turma_id">
                        <option value="">Selecione uma turma</option>
                        <?php if ($turmas) while ($turma = $turmas->fetch_assoc()): ?>
                            <option value="<?= $turma['id'] ?>" <?= $turma['id'] == ($dados['turma_id'] ?? 0) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($turma['nome']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>
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