<?php
session_start();
include "conexao.php";
include_once "funcoes.php"; // Use include_once para evitar duplicidade de funções

// Só admins e professores podem acessar
if (!isset($_SESSION['tipo']) || !verificar_permissao($_SESSION['tipo'], ['admin', 'professor'])) {
    header("Location: index.php?erro=permissao");
    exit;
}

$tipo_usuario = $_SESSION['tipo'];
$id_usuario = $_SESSION['id'];

// Processar marcação de frequência
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'marcar_frequencia') {
    $data = $_POST['data'];
    $turma_id = intval($_POST['turma_id']);
    $frequencias = $_POST['frequencia'] ?? [];
    try {
        $conn->begin_transaction();

        // Deletar frequências existentes para esta data/turma
        $stmt = $conn->prepare("DELETE FROM frequencia WHERE turma_id = ? AND data = ?");
        $stmt->bind_param("is", $turma_id, $data);
        $stmt->execute();
        $stmt->close();

        // Inserir novas frequências
        $stmt = $conn->prepare("INSERT INTO frequencia (aluno_id, turma_id, data, presente, observacao) VALUES (?, ?, ?, ?, ?)");
        foreach ($frequencias as $aluno_id => $dados) {
            $presente = isset($dados['presente']) ? 1 : 0;
            $observacao = $dados['observacao'] ?? null;
            $stmt->bind_param("iisis", $aluno_id, $turma_id, $data, $presente, $observacao);
            $stmt->execute();
        }
        $stmt->close();

        $conn->commit();
        $msg_sucesso = "Frequência registrada com sucesso!";

        // Criar notificação para alunos faltosos
        $stmt = $conn->prepare("SELECT aluno_id FROM frequencia WHERE turma_id = ? AND data = ? AND presente = 0");
        $stmt->bind_param("is", $turma_id, $data);
        $stmt->execute();
        $faltosos = $stmt->get_result();
        while ($faltoso = $faltosos->fetch_assoc()) {
            criar_notificacao($conn, $faltoso['aluno_id'], 'aluno',
                'Falta Registrada',
                "Sua falta foi registrada em " . formatar_data_br($data), 'warning');
        }
        $stmt->close();

    } catch (Exception $e) {
        $conn->rollback();
        $msg_erro = "Erro ao registrar frequência: " . $e->getMessage();
    }
}

// Buscar turmas conforme tipo de usuário
$turmas = null;
if ($tipo_usuario === 'admin') {
    $turmas = $conn->query("SELECT t.id, t.nome, p.nome as professor
                           FROM turmas t
                           LEFT JOIN professores p ON p.id = t.professor_id
                           WHERE t.finalizada = 0
                           ORDER BY t.nome");
} else { // professor
    $stmt = $conn->prepare("SELECT id, nome FROM turmas WHERE professor_id = ? AND finalizada = 0 ORDER BY nome");
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $turmas = $stmt->get_result();
    $stmt->close();
}

// Buscar alunos da turma selecionada
$turma_selecionada = intval($_GET['turma_id'] ?? 0);
$data_selecionada = $_GET['data'] ?? date('Y-m-d');
$alunos = null;
$frequencias_existentes = [];

if ($turma_selecionada) {
    // Verificar se o professor tem acesso a esta turma
    if ($tipo_usuario === 'professor') {
        $stmt = $conn->prepare("SELECT id FROM turmas WHERE id = ? AND professor_id = ?");
        $stmt->bind_param("ii", $turma_selecionada, $id_usuario);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $turma_selecionada = 0;
        }
        $stmt->close();
    }

    if ($turma_selecionada) {
        // Buscar alunos
        $stmt = $conn->prepare("SELECT id, nome, matricula FROM alunos WHERE turma_id = ? ORDER BY nome");
        $stmt->bind_param("i", $turma_selecionada);
        $stmt->execute();
        $alunos = $stmt->get_result();

        // Buscar frequências existentes para a data
        $stmt = $conn->prepare("SELECT aluno_id, presente, observacao FROM frequencia WHERE turma_id = ? AND data = ?");
        $stmt->bind_param("is", $turma_selecionada, $data_selecionada);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $frequencias_existentes[$row['aluno_id']] = [
                'presente' => $row['presente'],
                'observacao' => $row['observacao']
            ];
        }
        $stmt->close();
    }
}

// Buscar estatísticas de frequência
$stats_frequencia = [];
if ($turma_selecionada) {
    $stmt = $conn->prepare("
        SELECT
            a.id, a.nome,
            COUNT(f.id) as total_registros,
            SUM(CASE WHEN f.presente = 1 THEN 1 ELSE 0 END) as presencas,
            SUM(CASE WHEN f.presente = 0 THEN 1 ELSE 0 END) as faltas,
            ROUND((SUM(CASE WHEN f.presente = 1 THEN 1 ELSE 0 END) / COUNT(f.id)) * 100, 1) as percentual_presenca
        FROM alunos a
        LEFT JOIN frequencia f ON a.id = f.aluno_id AND f.turma_id = ?
        WHERE a.turma_id = ?
        GROUP BY a.id, a.nome
        ORDER BY a.nome
    ");
    $stmt->bind_param("ii", $turma_selecionada, $turma_selecionada);
    $stmt->execute();
    $stats_frequencia = $stmt->get_result();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Controle de Frequência</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .frequencia-form { background: #f9fafb; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .aluno-frequencia { display: flex; align-items: center; padding: 10px; border-bottom: 1px solid #eee; gap: 15px; }
        .aluno-info { flex: 1; min-width: 200px; }
        .aluno-info strong { display: block; }
        .aluno-info small { color: #666; }
        .frequencia-controls { display: flex; align-items: center; gap: 10px; }
        .checkbox-presente { transform: scale(1.5); }
        .observacao-input { max-width: 200px; padding: 5px; font-size: 0.9em; }
        .form-row { display: flex; gap: 15px; align-items: end; }
        .form-row > div { flex: 1; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin: 20px 0; }
        .stat-card { background: #f9fafb; padding: 15px; border-radius: 6px; border-left: 4px solid #b0b8c1; }
        .percentual-presenca { font-size: 1.2em; font-weight: bold; }
        .presenca-alta { color: #28a745; }
        .presenca-media { color: #ffc107; }
        .presenca-baixa { color: #dc3545; }
        .msg-sucesso { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin: 10px 0; border: 1px solid #c3e6cb; }
        .msg-erro { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin: 10px 0; border: 1px solid #f5c6cb; }
        .btn-marcar-todos { background: #28a745; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 0.9em; margin-left: 10px; }
        .btn-desmarcar-todos { background: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 0.9em; margin-left: 5px; }
    </style>
    <script>
        function marcarTodos(presente) {
            const checkboxes = document.querySelectorAll('.checkbox-presente');
            checkboxes.forEach(checkbox => {
                checkbox.checked = presente;
            });
        }
        function confirmarEnvio() {
            return confirm('Confirma o registro da frequência para esta data?');
        }
    </script>
</head>
<body>
<div class="container">
    <h1>Controle de Frequência</h1>
    <?php if (isset($msg_sucesso)): ?>
        <div class="msg-sucesso"><?= htmlspecialchars($msg_sucesso) ?></div>
    <?php endif; ?>
    <?php if (isset($msg_erro)): ?>
        <div class="msg-erro"><?= htmlspecialchars($msg_erro) ?></div>
    <?php endif; ?>
    <!-- Seleção de Turma e Data -->
    <form method="get">
        <div class="form-row">
            <div>
                <label>Turma</label>
                <select name="turma_id" onchange="this.form.submit()" required>
                    <option value="">Selecione uma turma</option>
                    <?php if ($turmas && $turmas->num_rows > 0): ?>
                        <?php while ($turma = $turmas->fetch_assoc()): ?>
                            <option value="<?= $turma['id'] ?>" <?= $turma['id'] == $turma_selecionada ? 'selected' : '' ?>>
                                <?= htmlspecialchars($turma['nome']) ?>
                                <?php if ($tipo_usuario === 'admin' && isset($turma['professor'])): ?>
                                    - Prof. <?= htmlspecialchars($turma['professor']) ?>
                                <?php endif; ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div>
                <label>Data</label>
                <input type="date" name="data" value="<?= htmlspecialchars($data_selecionada) ?>" onchange="this.form.submit()" required>
            </div>
        </div>
    </form>
    <?php if ($alunos && $alunos->num_rows > 0): ?>
        <!-- Formulário de Frequência -->
        <div class="frequencia-form">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3>Marcar Frequência - <?= formatar_data_br($data_selecionada) ?></h3>
                <div>
                    <button type="button" class="btn-marcar-todos" onclick="marcarTodos(true)">Marcar Todos</button>
                    <button type="button" class="btn-desmarcar-todos" onclick="marcarTodos(false)">Desmarcar Todos</button>
                </div>
            </div>
            <form method="post" onsubmit="return confirmarEnvio()">
                <input type="hidden" name="acao" value="marcar_frequencia">
                <input type="hidden" name="turma_id" value="<?= $turma_selecionada ?>">
                <input type="hidden" name="data" value="<?= htmlspecialchars($data_selecionada) ?>">
                <?php while ($aluno = $alunos->fetch_assoc()): ?>
                    <div class="aluno-frequencia">
                        <div class="aluno-info">
                            <strong><?= htmlspecialchars($aluno['nome']) ?></strong>
                            <small>Matrícula: <?= htmlspecialchars($aluno['matricula']) ?></small>
                        </div>
                        <div class="frequencia-controls">
                            <label>
                                <input type="checkbox"
                                       name="frequencia[<?= $aluno['id'] ?>][presente]"
                                       class="checkbox-presente"
                                       value="1"
                                       <?= ($frequencias_existentes[$aluno['id']]['presente'] ?? 1) ? 'checked' : '' ?>>
                                Presente
                            </label>
                            <input type="text"
                                   name="frequencia[<?= $aluno['id'] ?>][observacao]"
                                   class="observacao-input"
                                   placeholder="Observação (opcional)"
                                   value="<?= htmlspecialchars($frequencias_existentes[$aluno['id']]['observacao'] ?? '') ?>">
                        </div>
                    </div>
                <?php endwhile; ?>
                <div style="margin-top: 20px;">
                    <button type="submit">Salvar Frequência</button>
                </div>
            </form>
        </div>
        <!-- Estatísticas de Frequência -->
        <?php if ($stats_frequencia && $stats_frequencia->num_rows > 0): ?>
            <h3>Estatísticas de Frequência</h3>
            <div class="stats-grid">
                <?php while ($stat = $stats_frequencia->fetch_assoc()): ?>
                    <div class="stat-card">
                        <strong><?= htmlspecialchars($stat['nome']) ?></strong>
                        <div>Presenças: <?= $stat['presencas'] ?? 0 ?></div>
                        <div>Faltas: <?= $stat['faltas'] ?? 0 ?></div>
                        <div>Total de Registros: <?= $stat['total_registros'] ?? 0 ?></div>
                        <?php if ($stat['total_registros'] > 0): ?>
                            <?php
                            $percentual = $stat['percentual_presenca'];
                            $classe_cor = $percentual >= 80 ? 'presenca-alta' : ($percentual >= 60 ? 'presenca-media' : 'presenca-baixa');
                            ?>
                            <div class="percentual-presenca <?= $classe_cor ?>">
                                <?= $percentual ?>% de presença
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    <?php elseif ($turma_selecionada): ?>
        <p>Nenhum aluno encontrado nesta turma.</p>
    <?php endif; ?>
    <div style="margin-top: 30px;">
        <a href="relatorio_frequencia.php"><button>Relatórios de Frequência</button></a>
        <a href="<?= $tipo_usuario === 'admin' ? 'dashboard_admin.php' : 'dashboard_professor.php' ?>">
            <button>Voltar ao Dashboard</button>
        </a>
    </div>
</div>
</body>
</html>