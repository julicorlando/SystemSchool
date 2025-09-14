<?php
session_start();
include "conexao.php";

// Verificar permissões
if (!verificar_permissao($_SESSION['tipo'], ['admin', 'professor'])) {
    header("Location: index.php");
    exit;
}

$tipo_usuario = $_SESSION['tipo'];
$id_usuario = $_SESSION['id'];

// Parâmetros de filtro
$turma_id = intval($_GET['turma_id'] ?? 0);
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01'); // Primeiro dia do mês
$data_fim = $_GET['data_fim'] ?? date('Y-m-t'); // Último dia do mês
$tipo_relatorio = $_GET['tipo'] ?? 'por_turma';

// Buscar turmas conforme permissão
$turmas = null;
if ($tipo_usuario === 'admin') {
    $turmas = $conn->query("SELECT t.id, t.nome, p.nome as professor 
                           FROM turmas t 
                           LEFT JOIN professores p ON p.id = t.professor_id 
                           ORDER BY t.nome");
} else { // professor
    $stmt = $conn->prepare("SELECT id, nome FROM turmas WHERE professor_id = ? ORDER BY nome");
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $turmas = $stmt->get_result();
}

// Função para gerar relatório por turma
function gerar_relatorio_turma($conn, $turma_id, $data_inicio, $data_fim) {
    $sql = "
        SELECT 
            a.id, a.nome, a.matricula,
            COUNT(f.id) as total_dias,
            SUM(CASE WHEN f.presente = 1 THEN 1 ELSE 0 END) as presencas,
            SUM(CASE WHEN f.presente = 0 THEN 1 ELSE 0 END) as faltas,
            ROUND((SUM(CASE WHEN f.presente = 1 THEN 1 ELSE 0 END) / COUNT(f.id)) * 100, 1) as percentual_presenca
        FROM alunos a
        LEFT JOIN frequencia f ON a.id = f.aluno_id 
            AND f.data BETWEEN ? AND ?
            AND f.turma_id = ?
        WHERE a.turma_id = ?
        GROUP BY a.id, a.nome, a.matricula
        ORDER BY a.nome
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssii", $data_inicio, $data_fim, $turma_id, $turma_id);
    $stmt->execute();
    return $stmt->get_result();
}

// Função para gerar relatório geral
function gerar_relatorio_geral($conn, $data_inicio, $data_fim, $professor_id = null) {
    $where_professor = $professor_id ? "AND t.professor_id = ?" : "";
    
    $sql = "
        SELECT 
            t.nome as turma,
            COUNT(DISTINCT a.id) as total_alunos,
            COUNT(f.id) as total_registros,
            SUM(CASE WHEN f.presente = 1 THEN 1 ELSE 0 END) as total_presencas,
            SUM(CASE WHEN f.presente = 0 THEN 1 ELSE 0 END) as total_faltas,
            ROUND((SUM(CASE WHEN f.presente = 1 THEN 1 ELSE 0 END) / COUNT(f.id)) * 100, 1) as percentual_presenca
        FROM turmas t
        LEFT JOIN alunos a ON t.id = a.turma_id
        LEFT JOIN frequencia f ON a.id = f.aluno_id 
            AND f.data BETWEEN ? AND ?
            AND f.turma_id = t.id
        WHERE 1=1 $where_professor
        GROUP BY t.id, t.nome
        ORDER BY t.nome
    ";
    
    $stmt = $conn->prepare($sql);
    if ($professor_id) {
        $stmt->bind_param("ssi", $data_inicio, $data_fim, $professor_id);
    } else {
        $stmt->bind_param("ss", $data_inicio, $data_fim);
    }
    $stmt->execute();
    return $stmt->get_result();
}

// Gerar dados conforme tipo de relatório
$dados_relatorio = null;
$nome_turma = '';

if ($tipo_relatorio === 'por_turma' && $turma_id) {
    // Verificar acesso à turma
    if ($tipo_usuario === 'professor') {
        $stmt = $conn->prepare("SELECT nome FROM turmas WHERE id = ? AND professor_id = ?");
        $stmt->bind_param("ii", $turma_id, $id_usuario);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $turma_id = 0;
        } else {
            $nome_turma = $result->fetch_assoc()['nome'];
        }
    } else {
        $stmt = $conn->prepare("SELECT nome FROM turmas WHERE id = ?");
        $stmt->bind_param("i", $turma_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $nome_turma = $result->fetch_assoc()['nome'];
        }
    }
    
    if ($turma_id) {
        $dados_relatorio = gerar_relatorio_turma($conn, $turma_id, $data_inicio, $data_fim);
    }
} elseif ($tipo_relatorio === 'geral') {
    $professor_filtro = $tipo_usuario === 'professor' ? $id_usuario : null;
    $dados_relatorio = gerar_relatorio_geral($conn, $data_inicio, $data_fim, $professor_filtro);
}

// Função para exportar CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv' && $dados_relatorio) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="relatorio_frequencia_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    if ($tipo_relatorio === 'por_turma') {
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM para UTF-8
        fputcsv($output, ['Nome', 'Matrícula', 'Total Dias', 'Presenças', 'Faltas', 'Percentual Presença']);
        
        $dados_relatorio->data_seek(0);
        while ($row = $dados_relatorio->fetch_assoc()) {
            fputcsv($output, [
                $row['nome'],
                $row['matricula'],
                $row['total_dias'] ?: 0,
                $row['presencas'] ?: 0,
                $row['faltas'] ?: 0,
                ($row['percentual_presenca'] ?: 0) . '%'
            ]);
        }
    } else {
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($output, ['Turma', 'Total Alunos', 'Total Registros', 'Presenças', 'Faltas', 'Percentual Presença']);
        
        $dados_relatorio->data_seek(0);
        while ($row = $dados_relatorio->fetch_assoc()) {
            fputcsv($output, [
                $row['turma'],
                $row['total_alunos'],
                $row['total_registros'] ?: 0,
                $row['total_presencas'] ?: 0,
                $row['total_faltas'] ?: 0,
                ($row['percentual_presenca'] ?: 0) . '%'
            ]);
        }
    }
    
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios de Frequência</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .form-row {
            display: flex;
            gap: 15px;
            align-items: end;
            margin: 15px 0;
        }
        .form-row > div {
            flex: 1;
        }
        .relatorio-section {
            background: #f9fafb;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .table-responsive {
            overflow-x: auto;
            margin: 15px 0;
        }
        .percentual {
            font-weight: bold;
        }
        .percentual.alta { color: #28a745; }
        .percentual.media { color: #ffc107; }
        .percentual.baixa { color: #dc3545; }
        .export-buttons {
            margin: 15px 0;
            display: flex;
            gap: 10px;
        }
        .btn-export {
            background: #17a2b8;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.9em;
        }
        .btn-export:hover {
            background: #138496;
        }
        .filtros {
            background: #ffffff;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
            margin: 20px 0;
        }
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
    </style>
</head>
<body>
<div class="container">
    <h1>Relatórios de Frequência</h1>
    
    <!-- Abas -->
    <div class="tab-buttons">
        <a href="?tipo=por_turma&turma_id=<?= $turma_id ?>&data_inicio=<?= $data_inicio ?>&data_fim=<?= $data_fim ?>" 
           class="tab-button <?= $tipo_relatorio === 'por_turma' ? 'active' : '' ?>">Por Turma</a>
        <a href="?tipo=geral&data_inicio=<?= $data_inicio ?>&data_fim=<?= $data_fim ?>" 
           class="tab-button <?= $tipo_relatorio === 'geral' ? 'active' : '' ?>">Geral</a>
    </div>
    
    <!-- Filtros -->
    <div class="filtros">
        <h3>Filtros</h3>
        <form method="get">
            <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo_relatorio) ?>">
            
            <div class="form-row">
                <?php if ($tipo_relatorio === 'por_turma'): ?>
                    <div>
                        <label>Turma</label>
                        <select name="turma_id" required>
                            <option value="">Selecione uma turma</option>
                            <?php if ($turmas && $turmas->num_rows > 0): ?>
                                <?php 
                                $turmas->data_seek(0);
                                while ($turma = $turmas->fetch_assoc()): 
                                ?>
                                    <option value="<?= $turma['id'] ?>" <?= $turma['id'] == $turma_id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($turma['nome']) ?>
                                        <?php if ($tipo_usuario === 'admin' && isset($turma['professor'])): ?>
                                            - Prof. <?= htmlspecialchars($turma['professor']) ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                <?php endif; ?>
                
                <div>
                    <label>Data Início</label>
                    <input type="date" name="data_inicio" value="<?= htmlspecialchars($data_inicio) ?>" required>
                </div>
                
                <div>
                    <label>Data Fim</label>
                    <input type="date" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>" required>
                </div>
                
                <div>
                    <button type="submit">Gerar Relatório</button>
                </div>
            </div>
        </form>
    </div>
    
    <?php if ($dados_relatorio && $dados_relatorio->num_rows > 0): ?>
        <div class="relatorio-section">
            <?php if ($tipo_relatorio === 'por_turma'): ?>
                <h3>Relatório de Frequência - <?= htmlspecialchars($nome_turma) ?></h3>
                <p><strong>Período:</strong> <?= formatar_data_br($data_inicio) ?> a <?= formatar_data_br($data_fim) ?></p>
                
                <div class="export-buttons">
                    <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn-export">
                        Exportar CSV
                    </a>
                </div>
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Matrícula</th>
                                <th>Total Dias</th>
                                <th>Presenças</th>
                                <th>Faltas</th>
                                <th>% Presença</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($aluno = $dados_relatorio->fetch_assoc()): ?>
                                <?php 
                                $percentual = $aluno['percentual_presenca'] ?: 0;
                                $classe_percentual = $percentual >= 80 ? 'alta' : ($percentual >= 60 ? 'media' : 'baixa');
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($aluno['nome']) ?></td>
                                    <td><?= htmlspecialchars($aluno['matricula']) ?></td>
                                    <td><?= $aluno['total_dias'] ?: 0 ?></td>
                                    <td><?= $aluno['presencas'] ?: 0 ?></td>
                                    <td><?= $aluno['faltas'] ?: 0 ?></td>
                                    <td class="percentual <?= $classe_percentual ?>"><?= $percentual ?>%</td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
            <?php elseif ($tipo_relatorio === 'geral'): ?>
                <h3>Relatório Geral de Frequência</h3>
                <p><strong>Período:</strong> <?= formatar_data_br($data_inicio) ?> a <?= formatar_data_br($data_fim) ?></p>
                
                <div class="export-buttons">
                    <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn-export">
                        Exportar CSV
                    </a>
                </div>
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Turma</th>
                                <th>Total Alunos</th>
                                <th>Total Registros</th>
                                <th>Presenças</th>
                                <th>Faltas</th>
                                <th>% Presença</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($turma = $dados_relatorio->fetch_assoc()): ?>
                                <?php 
                                $percentual = $turma['percentual_presenca'] ?: 0;
                                $classe_percentual = $percentual >= 80 ? 'alta' : ($percentual >= 60 ? 'media' : 'baixa');
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($turma['turma']) ?></td>
                                    <td><?= $turma['total_alunos'] ?></td>
                                    <td><?= $turma['total_registros'] ?: 0 ?></td>
                                    <td><?= $turma['total_presencas'] ?: 0 ?></td>
                                    <td><?= $turma['total_faltas'] ?: 0 ?></td>
                                    <td class="percentual <?= $classe_percentual ?>"><?= $percentual ?>%</td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
    <?php elseif (isset($_GET['turma_id']) || isset($_GET['tipo'])): ?>
        <div class="relatorio-section">
            <p>Nenhum dado encontrado para os filtros selecionados.</p>
        </div>
    <?php endif; ?>
    
    <div style="margin-top: 30px;">
        <a href="frequencia.php"><button>Voltar ao Controle de Frequência</button></a>
        <a href="<?= $tipo_usuario === 'admin' ? 'dashboard_admin.php' : 'dashboard_professor.php' ?>">
            <button>Dashboard</button>
        </a>
    </div>
</div>
</body>
</html>