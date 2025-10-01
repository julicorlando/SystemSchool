<?php
session_start();
include "conexao.php";
include_once "funcoes.php";

// Exibir erros para depuração (remova em produção)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Verificar se está logado
if (!isset($_SESSION['tipo']) || !isset($_SESSION['id'])) {
    header("Location: index.php");
    exit;
}

$usuario_tipo = $_SESSION['tipo'];
$usuario_id = $_SESSION['id'];
$acao = $_GET['acao'] ?? 'calendario';

// Processar ações POST
if ($_POST && isset($_POST['acao'])) {
    switch ($_POST['acao']) {
        case 'criar_evento':
            if (verificar_permissao($usuario_tipo, ['admin', 'professor'])) {
                $dados = limpar_entrada($_POST);

                $professor_id = $usuario_tipo === 'professor' ? $usuario_id : ($dados['professor_id'] ?? null);
                $turma_id = !empty($dados['turma_id']) ? intval($dados['turma_id']) : null;
                $data_fim = !empty($dados['data_fim']) ? $dados['data_fim'] : $dados['data_inicio'];
                $publico = isset($dados['publico']) ? 1 : 0;

                $stmt = $conn->prepare("
                    INSERT INTO eventos (titulo, descricao, data_inicio, data_fim, tipo, turma_id, professor_id, publico) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                if (!$stmt) {
                    $_SESSION['msg_erro'] = 'Erro na preparação da query: ' . $conn->error;
                    break;
                }
                $stmt->bind_param("sssssiis",
                    $dados['titulo'],
                    $dados['descricao'],
                    $dados['data_inicio'],
                    $data_fim,
                    $dados['tipo'],
                    $turma_id,
                    $professor_id,
                    $publico
                );

                if ($stmt->execute()) {
                    $_SESSION['msg_sucesso'] = 'Evento criado com sucesso!';
                    // Criar notificações se necessário
                    if ($publico && $turma_id) {
                        $titulo_notif = "Novo Evento: " . $dados['titulo'];
                        $mensagem_notif = "Um novo evento foi criado para " . formatar_data_br($dados['data_inicio']);
                        $alunos = $conn->prepare("SELECT id FROM alunos WHERE turma_id = ?");
                        $alunos->bind_param("i", $turma_id);
                        $alunos->execute();
                        $resultado = $alunos->get_result();
                        while ($aluno = $resultado->fetch_assoc()) {
                            criar_notificacao($conn, $aluno['id'], 'aluno', $titulo_notif, $mensagem_notif, 'info');
                        }
                        $alunos->close();
                    }
                } else {
                    $_SESSION['msg_erro'] = 'Erro ao criar evento: ' . $stmt->error;
                }
                $stmt->close();
            }
            break;

        case 'editar_evento':
            if (verificar_permissao($usuario_tipo, ['admin', 'professor'])) {
                $dados = limpar_entrada($_POST);
                $evento_id = intval($dados['evento_id']);

                $where_permissao = $usuario_tipo === 'professor' ? "AND professor_id = $usuario_id" : "";
                $verificar = $conn->query("SELECT id FROM eventos WHERE id = $evento_id $where_permissao");

                if ($verificar && $verificar->num_rows > 0) {
                    $turma_id = !empty($dados['turma_id']) ? intval($dados['turma_id']) : null;
                    $data_fim = !empty($dados['data_fim']) ? $dados['data_fim'] : $dados['data_inicio'];
                    $publico = isset($dados['publico']) ? 1 : 0;

                    $stmt = $conn->prepare("
                        UPDATE eventos 
                        SET titulo = ?, descricao = ?, data_inicio = ?, data_fim = ?, tipo = ?, turma_id = ?, publico = ?
                        WHERE id = ?
                    ");
                    if (!$stmt) {
                        $_SESSION['msg_erro'] = 'Erro na preparação da query: ' . $conn->error;
                        break;
                    }
                    $stmt->bind_param("sssssiis",
                        $dados['titulo'],
                        $dados['descricao'],
                        $dados['data_inicio'],
                        $data_fim,
                        $dados['tipo'],
                        $turma_id,
                        $publico,
                        $evento_id
                    );

                    if ($stmt->execute()) {
                        $_SESSION['msg_sucesso'] = 'Evento atualizado com sucesso!';
                    } else {
                        $_SESSION['msg_erro'] = 'Erro ao atualizar evento: ' . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $_SESSION['msg_erro'] = 'Você não tem permissão para editar este evento.';
                }
                if ($verificar) $verificar->close();
            }
            break;
    }

    header("Location: calendario.php?acao=" . $acao);
    exit;
}

// Buscar eventos conforme permissão e filtros
$mes = isset($_GET['mes']) ? intval($_GET['mes']) : intval(date('n'));
$ano = isset($_GET['ano']) ? intval($_GET['ano']) : intval(date('Y'));
$turma_filtro = isset($_GET['turma_id']) ? $_GET['turma_id'] : '';

$where_permissao = "";
if ($usuario_tipo === 'professor') {
    $where_permissao = "AND (e.professor_id = $usuario_id OR e.publico = 1)";
} elseif ($usuario_tipo === 'aluno') {
    $turma_aluno = $conn->prepare("SELECT turma_id FROM alunos WHERE id = ?");
    $turma_aluno->bind_param("i", $usuario_id);
    $turma_aluno->execute();
    $resultado = $turma_aluno->get_result()->fetch_assoc();
    $turma_aluno_id = $resultado['turma_id'] ?? 0;
    $turma_aluno->close();
    $where_permissao = "AND (e.turma_id = $turma_aluno_id OR e.publico = 1)";
}

$where_turma = $turma_filtro ? "AND e.turma_id = " . intval($turma_filtro) : "";

// Buscar eventos do mês
$sql_eventos = "
    SELECT e.*, t.nome as turma_nome, p.nome as professor_nome
    FROM eventos e
    LEFT JOIN turmas t ON e.turma_id = t.id
    LEFT JOIN professores p ON e.professor_id = p.id
    WHERE MONTH(e.data_inicio) = $mes AND YEAR(e.data_inicio) = $ano
    $where_permissao $where_turma
    ORDER BY e.data_inicio ASC, e.titulo ASC
";
$eventos = $conn->query($sql_eventos);

// Buscar turmas para filtros (se admin ou professor)
$turmas = null;
if ($usuario_tipo === 'admin') {
    $turmas = $conn->query("SELECT id, nome FROM turmas ORDER BY nome");
} elseif ($usuario_tipo === 'professor') {
    $stmt = $conn->prepare("SELECT id, nome FROM turmas WHERE professor_id = ? ORDER BY nome");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $turmas = $stmt->get_result();
    $stmt->close();
}

// Buscar evento específico para edição
$evento_editar = null;
if ($acao === 'editar' && isset($_GET['id'])) {
    $evento_id = intval($_GET['id']);
    $where_edit_permissao = $usuario_tipo === 'professor' ? "AND professor_id = $usuario_id" : "";

    $stmt = $conn->prepare("
        SELECT * FROM eventos 
        WHERE id = ? $where_edit_permissao
    ");
    $stmt->bind_param("i", $evento_id);
    $stmt->execute();
    $evento_editar = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Gerar calendário HTML
function gerar_calendario($mes, $ano, $eventos_array) {
    $primeiro_dia = mktime(0, 0, 0, $mes, 1, $ano);
    $nome_mes = date('F Y', $primeiro_dia);
    $dias_no_mes = date('t', $primeiro_dia);
    $dia_semana_inicio = date('w', $primeiro_dia);

    $html = "<div class='calendario'>";
    $html .= "<div class='calendario-header'>";
    $html .= "<h3>$nome_mes</h3>";
    $html .= "</div>";

    $html .= "<div class='calendario-grid'>";

    $dias_semana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'];
    foreach ($dias_semana as $dia) {
        $html .= "<div class='dia-semana'>$dia</div>";
    }

    for ($i = 0; $i < $dia_semana_inicio; $i++) {
        $html .= "<div class='dia vazio'></div>";
    }

    for ($dia = 1; $dia <= $dias_no_mes; $dia++) {
        $data_completa = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
        $eventos_dia = array_filter($eventos_array, function($evento) use ($data_completa) {
            return substr($evento['data_inicio'], 0, 10) === $data_completa;
        });

        $classe_hoje = (date('Y-m-d') === $data_completa) ? ' hoje' : '';
        $html .= "<div class='dia$classe_hoje'>";
        $html .= "<div class='numero-dia'>$dia</div>";

        foreach ($eventos_dia as $evento) {
            $classe_tipo = 'evento-' . $evento['tipo'];
            $html .= "<div class='evento $classe_tipo' title='" . htmlspecialchars($evento['titulo']) . "'>";
            $html .= htmlspecialchars(substr($evento['titulo'], 0, 15));
            if (strlen($evento['titulo']) > 15) $html .= '...';
            $html .= "</div>";
        }

        $html .= "</div>";
    }

    $html .= "</div></div>";
    return $html;
}

$eventos_array = [];
if ($eventos && $eventos->num_rows > 0) {
    while ($evento = $eventos->fetch_assoc()) {
        $eventos_array[] = $evento;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendário Escolar</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* (estilos iguais ao anterior) */
        .calendario { background: white; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; margin: 20px 0;}
        .calendario-header { background: #b0b8c1; color: white; padding: 15px; text-align: center;}
        .calendario-grid { display: grid; grid-template-columns: repeat(7, 1fr);}
        .dia-semana { background: #f8f9fa; padding: 10px; text-align: center; font-weight: bold; border-bottom: 1px solid #dee2e6;}
        .dia { min-height: 100px; padding: 5px; border-right: 1px solid #eee; border-bottom: 1px solid #eee; position: relative;}
        .dia.hoje { background: #e3f2fd;}
        .dia.vazio { background: #f8f9fa;}
        .numero-dia { font-weight: bold; margin-bottom: 5px;}
        .evento { background: #b0b8c1; color: white; padding: 2px 5px; margin: 2px 0; border-radius: 3px; font-size: 0.8em; cursor: pointer;}
        .evento-prova { background: #dc3545;}
        .evento-reuniao { background: #007bff;}
        .evento-evento { background: #28a745;}
        .evento-feriado { background: #ffc107; color: #212529;}
        .evento-aula { background: #17a2b8;}
        .navegacao-mes { display: flex; justify-content: space-between; align-items: center; margin: 20px 0;}
        .filtros-calendario { background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0;}
        .eventos-lista { background: white; border-radius: 8px; padding: 20px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1);}
        .evento-item { padding: 10px; border-left: 4px solid #b0b8c1; margin: 10px 0; background: #f8f9fa; border-radius: 0 4px 4px 0;}
        .evento-item.prova { border-left-color: #dc3545;}
        .evento-item.reuniao { border-left-color: #007bff;}
        .evento-item.evento { border-left-color: #28a745;}
        .evento-item.feriado { border-left-color: #ffc107;}
        .evento-item.aula { border-left-color: #17a2b8;}
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);}
        .modal-content { background-color: #fefefe; margin: 5% auto; padding: 20px; border-radius: 10px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto;}
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;}
        .close:hover { color: black;}
        .legenda { display: flex; gap: 15px; flex-wrap: wrap; margin: 15px 0; align-items: center;}
        .legenda-item { display: flex; align-items: center; gap: 5px;}
        .legenda-cor { width: 15px; height: 15px; border-radius: 3px;}
    </style>
    <script>
        function abrirModal(id) {
            document.getElementById(id).style.display = 'block';
        }
        function fecharModal(id) {
            document.getElementById(id).style.display = 'none';
        }
        function editarEvento(eventoId) {
            window.location.href = 'calendario.php?acao=editar&id=' + eventoId;
        }
    </script>
</head>
<body>
<div class="container">
    <h1>Calendário Escolar</h1>
    <?php if (isset($_SESSION['msg_sucesso'])): ?>
        <div class="alert alert-success"><?= $_SESSION['msg_sucesso'] ?></div>
        <?php unset($_SESSION['msg_sucesso']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['msg_erro'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['msg_erro'] ?></div>
        <?php unset($_SESSION['msg_erro']); ?>
    <?php endif; ?>
    <!-- Navegação e Filtros -->
    <div class="navegacao-mes">
        <a href="?mes=<?= $mes == 1 ? 12 : $mes - 1 ?>&ano=<?= $mes == 1 ? $ano - 1 : $ano ?>&turma_id=<?= $turma_filtro ?>">
            <button>← Mês Anterior</button>
        </a>
        <div>
            <?php if (verificar_permissao($usuario_tipo, ['admin', 'professor'])): ?>
                <button onclick="abrirModal('modalEvento')">Novo Evento</button>
            <?php endif; ?>
        </div>
        <a href="?mes=<?= $mes == 12 ? 1 : $mes + 1 ?>&ano=<?= $mes == 12 ? $ano + 1 : $ano ?>&turma_id=<?= $turma_filtro ?>">
            <button>Próximo Mês →</button>
        </a>
    </div>
    <!-- Filtros -->
    <?php if ($turmas): ?>
        <div class="filtros-calendario">
            <form method="get" class="form-row">
                <input type="hidden" name="mes" value="<?= $mes ?>">
                <input type="hidden" name="ano" value="<?= $ano ?>">
                <div>
                    <label>Filtrar por Turma</label>
                    <select name="turma_id" onchange="this.form.submit()">
                        <option value="">Todas as turmas</option>
                        <?php
                        $turmas->data_seek(0);
                        while ($turma = $turmas->fetch_assoc()):
                        ?>
                            <option value="<?= $turma['id'] ?>" <?= $turma['id'] == $turma_filtro ? 'selected' : '' ?>>
                                <?= htmlspecialchars($turma['nome']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </form>
        </div>
    <?php endif; ?>
    <!-- Legenda -->
    <div class="legenda">
        <strong>Legenda:</strong>
        <div class="legenda-item"><div class="legenda-cor" style="background: #dc3545;"></div><span>Prova</span></div>
        <div class="legenda-item"><div class="legenda-cor" style="background: #007bff;"></div><span>Reunião</span></div>
        <div class="legenda-item"><div class="legenda-cor" style="background: #28a745;"></div><span>Evento</span></div>
        <div class="legenda-item"><div class="legenda-cor" style="background: #ffc107;"></div><span>Feriado</span></div>
        <div class="legenda-item"><div class="legenda-cor" style="background: #17a2b8;"></div><span>Aula</span></div>
    </div>
    <!-- Calendário -->
    <?= gerar_calendario($mes, $ano, $eventos_array) ?>
    <!-- Lista de Eventos -->
    <?php if (!empty($eventos_array)): ?>
        <div class="eventos-lista">
            <h3>Eventos de <?= date('F Y', mktime(0, 0, 0, $mes, 1, $ano)) ?></h3>
            <?php foreach ($eventos_array as $evento): ?>
                <div class="evento-item <?= $evento['tipo'] ?>">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div>
                            <h4 style="margin: 0 0 5px 0;"><?= htmlspecialchars($evento['titulo']) ?></h4>
                            <p style="margin: 5px 0; color: #666;">
                                <strong>Data:</strong> <?= formatar_data_br($evento['data_inicio']) ?>
                                <?php if ($evento['data_fim'] && $evento['data_fim'] !== $evento['data_inicio']): ?>
                                    até <?= formatar_data_br($evento['data_fim']) ?>
                                <?php endif; ?>
                            </p>
                            <p style="margin: 5px 0; color: #666;">
                                <strong>Tipo:</strong> <?= ucfirst($evento['tipo']) ?>
                                <?php if ($evento['turma_nome']): ?>
                                    | <strong>Turma:</strong> <?= htmlspecialchars($evento['turma_nome']) ?>
                                <?php endif; ?>
                                <?php if ($evento['professor_nome']): ?>
                                    | <strong>Professor:</strong> <?= htmlspecialchars($evento['professor_nome']) ?>
                                <?php endif; ?>
                            </p>
                            <?php if ($evento['descricao']): ?>
                                <p style="margin: 5px 0;"><?= nl2br(htmlspecialchars($evento['descricao'])) ?></p>
                            <?php endif; ?>
                        </div>
                        <?php if (verificar_permissao($usuario_tipo, ['admin', 'professor'])): ?>
                            <button onclick="editarEvento(<?= $evento['id'] ?>)" style="width: auto; padding: 5px 10px;">
                                Editar
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <!-- Modal Novo/Editar Evento -->
    <?php if (verificar_permissao($usuario_tipo, ['admin', 'professor'])): ?>
        <div id="modalEvento" class="modal">
            <div class="modal-content">
                <span class="close" onclick="fecharModal('modalEvento')">&times;</span>
                <h3><?= $evento_editar ? 'Editar Evento' : 'Novo Evento' ?></h3>
                <form method="post">
                    <input type="hidden" name="acao" value="<?= $evento_editar ? 'editar_evento' : 'criar_evento' ?>">
                    <?php if ($evento_editar): ?>
                        <input type="hidden" name="evento_id" value="<?= $evento_editar['id'] ?>">
                    <?php endif; ?>
                    <div>
                        <label>Título *</label>
                        <input type="text" name="titulo" value="<?= htmlspecialchars($evento_editar['titulo'] ?? '') ?>" required>
                    </div>
                    <div>
                        <label>Descrição</label>
                        <textarea name="descricao" rows="3"><?= htmlspecialchars($evento_editar['descricao'] ?? '') ?></textarea>
                    </div>
                    <div class="form-row">
                        <div>
                            <label>Data Início *</label>
                            <input type="datetime-local" name="data_inicio" value="<?= $evento_editar ? date('Y-m-d\TH:i', strtotime($evento_editar['data_inicio'])) : '' ?>" required>
                        </div>
                        <div>
                            <label>Data Fim</label>
                            <input type="datetime-local" name="data_fim" value="<?= $evento_editar && $evento_editar['data_fim'] ? date('Y-m-d\TH:i', strtotime($evento_editar['data_fim'])) : '' ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div>
                            <label>Tipo *</label>
                            <select name="tipo" required>
                                <option value="prova" <?= ($evento_editar['tipo'] ?? '') === 'prova' ? 'selected' : '' ?>>Prova</option>
                                <option value="reuniao" <?= ($evento_editar['tipo'] ?? '') === 'reuniao' ? 'selected' : '' ?>>Reunião</option>
                                <option value="evento" <?= ($evento_editar['tipo'] ?? '') === 'evento' ? 'selected' : '' ?>>Evento</option>
                                <option value="feriado" <?= ($evento_editar['tipo'] ?? '') === 'feriado' ? 'selected' : '' ?>>Feriado</option>
                                <option value="aula" <?= ($evento_editar['tipo'] ?? '') === 'aula' ? 'selected' : '' ?>>Aula</option>
                            </select>
                        </div>
                        <?php if ($turmas && $turmas->num_rows > 0): ?>
                            <div>
                                <label>Turma</label>
                                <select name="turma_id">
                                    <option value="">Geral (todas as turmas)</option>
                                    <?php
                                    $turmas->data_seek(0);
                                    while ($turma = $turmas->fetch_assoc()):
                                    ?>
                                        <option value="<?= $turma['id'] ?>" <?= ($evento_editar['turma_id'] ?? '') == $turma['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($turma['nome']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label>
                            <input type="checkbox" name="publico" <?= ($evento_editar['publico'] ?? 1) ? 'checked' : '' ?>>
                            Evento público (visível para todos)
                        </label>
                    </div>
                    <button type="submit"><?= $evento_editar ? 'Atualizar Evento' : 'Criar Evento' ?></button>
                </form>
            </div>
        </div>
        <?php if ($evento_editar): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    abrirModal('modalEvento');
                });
            </script>
        <?php endif; ?>
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