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

// Função para buscar estatísticas gerais
function obter_estatisticas_gerais($conn, $professor_id = null) {
    $where_professor = $professor_id ? "AND t.professor_id = $professor_id" : "";
    
    $stats = [];
    
    // Total de alunos
    $result = $conn->query("SELECT COUNT(*) as total FROM alunos a JOIN turmas t ON a.turma_id = t.id WHERE 1=1 $where_professor");
    $stats['total_alunos'] = $result->fetch_assoc()['total'];
    
    // Total de turmas
    $result = $conn->query("SELECT COUNT(*) as total FROM turmas WHERE 1=1 $where_professor");
    $stats['total_turmas'] = $result->fetch_assoc()['total'];
    
    // Turmas ativas
    $result = $conn->query("SELECT COUNT(*) as total FROM turmas WHERE finalizada = 0 $where_professor");
    $stats['turmas_ativas'] = $result->fetch_assoc()['total'];
    
    // Média geral de notas
    $result = $conn->query("SELECT AVG(media) as media_geral FROM notas_faltas nf JOIN alunos a ON nf.aluno_id = a.id JOIN turmas t ON a.turma_id = t.id WHERE nf.media IS NOT NULL $where_professor");
    $row = $result->fetch_assoc();
    $stats['media_geral'] = $row['media_geral'] ? round($row['media_geral'], 2) : 0;
    
    // Taxa de aprovação
    $result = $conn->query("
        SELECT 
            COUNT(*) as total_avaliados,
            SUM(CASE WHEN nf.media >= 7 AND nf.faltas < 4 THEN 1 ELSE 0 END) as aprovados
        FROM notas_faltas nf 
        JOIN alunos a ON nf.aluno_id = a.id 
        JOIN turmas t ON a.turma_id = t.id 
        WHERE nf.media IS NOT NULL AND nf.faltas IS NOT NULL $where_professor
    ");
    $row = $result->fetch_assoc();
    $stats['taxa_aprovacao'] = $row['total_avaliados'] > 0 ? round(($row['aprovados'] / $row['total_avaliados']) * 100, 1) : 0;
    
    // Frequência média
    $result = $conn->query("
        SELECT AVG(
            CASE WHEN total_registros > 0 
            THEN (presencas / total_registros) * 100 
            ELSE 0 END
        ) as frequencia_media
        FROM (
            SELECT 
                a.id,
                COUNT(f.id) as total_registros,
                SUM(CASE WHEN f.presente = 1 THEN 1 ELSE 0 END) as presencas
            FROM alunos a
            JOIN turmas t ON a.turma_id = t.id
            LEFT JOIN frequencia f ON a.id = f.aluno_id
            WHERE 1=1 $where_professor
            GROUP BY a.id
        ) as freq_stats
    ");
    $row = $result->fetch_assoc();
    $stats['frequencia_media'] = $row['frequencia_media'] ? round($row['frequencia_media'], 1) : 0;
    
    return $stats;
}

// Função para obter dados de desempenho por turma
function obter_desempenho_turmas($conn, $professor_id = null) {
    $where_professor = $professor_id ? "AND t.professor_id = $professor_id" : "";
    
    $sql = "
        SELECT 
            t.nome as turma,
            COUNT(DISTINCT a.id) as total_alunos,
            AVG(nf.media) as media_turma,
            SUM(CASE WHEN nf.media >= 7 AND nf.faltas < 4 THEN 1 ELSE 0 END) as aprovados,
            COUNT(nf.id) as avaliados,
            AVG(CASE WHEN freq.total_registros > 0 THEN (freq.presencas / freq.total_registros) * 100 ELSE 0 END) as frequencia_media
        FROM turmas t
        LEFT JOIN alunos a ON t.id = a.turma_id
        LEFT JOIN notas_faltas nf ON a.id = nf.aluno_id
        LEFT JOIN (
            SELECT 
                aluno_id,
                COUNT(*) as total_registros,
                SUM(CASE WHEN presente = 1 THEN 1 ELSE 0 END) as presencas
            FROM frequencia
            GROUP BY aluno_id
        ) freq ON a.id = freq.aluno_id
        WHERE 1=1 $where_professor
        GROUP BY t.id, t.nome
        ORDER BY t.nome
    ";
    
    return $conn->query($sql);
}

// Função para obter alunos com baixo desempenho
function obter_alunos_risco($conn, $professor_id = null) {
    $where_professor = $professor_id ? "AND t.professor_id = $professor_id" : "";
    
    $sql = "
        SELECT 
            a.nome as aluno,
            a.matricula,
            t.nome as turma,
            nf.media,
            nf.faltas,
            CASE 
                WHEN nf.faltas >= 4 THEN 'Reprovado por faltas'
                WHEN nf.media < 5 THEN 'Risco alto'
                WHEN nf.media < 7 THEN 'Recuperação'
                ELSE 'Normal'
            END as status
        FROM alunos a
        JOIN turmas t ON a.turma_id = t.id
        LEFT JOIN notas_faltas nf ON a.id = nf.aluno_id
        WHERE (nf.media < 7 OR nf.faltas >= 3) $where_professor
        ORDER BY nf.media ASC, nf.faltas DESC
    ";
    
    return $conn->query($sql);
}

// Buscar dados
$stats_gerais = obter_estatisticas_gerais($conn, $tipo_usuario === 'professor' ? $id_usuario : null);
$desempenho_turmas = obter_desempenho_turmas($conn, $tipo_usuario === 'professor' ? $id_usuario : null);
$alunos_risco = obter_alunos_risco($conn, $tipo_usuario === 'professor' ? $id_usuario : null);

// Dados para gráficos (formato JSON)
$dados_grafico_turmas = [];
if ($desempenho_turmas && $desempenho_turmas->num_rows > 0) {
    $desempenho_turmas->data_seek(0);
    while ($turma = $desempenho_turmas->fetch_assoc()) {
        $dados_grafico_turmas[] = [
            'turma' => $turma['turma'],
            'media' => floatval($turma['media_turma'] ?: 0),
            'frequencia' => floatval($turma['frequencia_media'] ?: 0),
            'taxa_aprovacao' => $turma['avaliados'] > 0 ? round(($turma['aprovados'] / $turma['avaliados']) * 100, 1) : 0
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios e Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.success {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .stat-card.warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .stat-card.info {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            display: block;
        }
        
        .stat-label {
            font-size: 0.9em;
            opacity: 0.9;
            margin-top: 5px;
        }
        
        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin: 20px 0;
        }
        
        .chart-wrapper {
            position: relative;
            height: 400px;
        }
        
        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .report-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .risk-student {
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
            border-left: 4px solid;
        }
        
        .risk-high {
            background: #fff5f5;
            border-left-color: #f56565;
        }
        
        .risk-medium {
            background: #fffbf0;
            border-left-color: #ed8936;
        }
        
        .risk-low {
            background: #f0fff4;
            border-left-color: #48bb78;
        }
        
        .export-buttons {
            margin: 20px 0;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn-export {
            background: #4299e1;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.3s;
        }
        
        .btn-export:hover {
            background: #3182ce;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Relatórios e Dashboard</h1>
    
    <!-- Estatísticas Gerais -->
    <h2>Visão Geral</h2>
    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-number"><?= $stats_gerais['total_alunos'] ?></span>
            <span class="stat-label">Total de Alunos</span>
        </div>
        <div class="stat-card success">
            <span class="stat-number"><?= $stats_gerais['total_turmas'] ?></span>
            <span class="stat-label">Total de Turmas</span>
        </div>
        <div class="stat-card info">
            <span class="stat-number"><?= $stats_gerais['media_geral'] ?></span>
            <span class="stat-label">Média Geral</span>
        </div>
        <div class="stat-card warning">
            <span class="stat-number"><?= $stats_gerais['taxa_aprovacao'] ?>%</span>
            <span class="stat-label">Taxa de Aprovação</span>
        </div>
        <div class="stat-card success">
            <span class="stat-number"><?= $stats_gerais['frequencia_media'] ?>%</span>
            <span class="stat-label">Frequência Média</span>
        </div>
        <div class="stat-card">
            <span class="stat-number"><?= $stats_gerais['turmas_ativas'] ?></span>
            <span class="stat-label">Turmas Ativas</span>
        </div>
    </div>
    
    <!-- Gráficos -->
    <?php if (!empty($dados_grafico_turmas)): ?>
    <div class="chart-container">
        <h3>Desempenho por Turma</h3>
        <div class="chart-wrapper">
            <canvas id="graficoDesempenho"></canvas>
        </div>
    </div>
    
    <div class="chart-container">
        <h3>Frequência por Turma</h3>
        <div class="chart-wrapper">
            <canvas id="graficoFrequencia"></canvas>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Relatórios Detalhados -->
    <div class="reports-grid">
        <!-- Desempenho por Turma -->
        <div class="report-card">
            <h3>Desempenho por Turma</h3>
            <?php if ($desempenho_turmas && $desempenho_turmas->num_rows > 0): ?>
                <?php 
                $desempenho_turmas->data_seek(0);
                while ($turma = $desempenho_turmas->fetch_assoc()): 
                ?>
                    <div style="margin: 10px 0; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                        <strong><?= htmlspecialchars($turma['turma']) ?></strong><br>
                        <small>
                            Alunos: <?= $turma['total_alunos'] ?> | 
                            Média: <?= number_format($turma['media_turma'] ?: 0, 2) ?> | 
                            Aprovação: <?= $turma['avaliados'] > 0 ? round(($turma['aprovados'] / $turma['avaliados']) * 100, 1) : 0 ?>%
                        </small>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>Nenhum dado disponível.</p>
            <?php endif; ?>
        </div>
        
        <!-- Alunos em Risco -->
        <div class="report-card">
            <h3>Alunos em Risco</h3>
            <?php if ($alunos_risco && $alunos_risco->num_rows > 0): ?>
                <?php while ($aluno = $alunos_risco->fetch_assoc()): ?>
                    <?php 
                    $classe_risco = 'risk-low';
                    if ($aluno['status'] === 'Risco alto' || $aluno['status'] === 'Reprovado por faltas') {
                        $classe_risco = 'risk-high';
                    } elseif ($aluno['status'] === 'Recuperação') {
                        $classe_risco = 'risk-medium';
                    }
                    ?>
                    <div class="risk-student <?= $classe_risco ?>">
                        <strong><?= htmlspecialchars($aluno['aluno']) ?></strong><br>
                        <small>
                            Turma: <?= htmlspecialchars($aluno['turma']) ?> | 
                            Média: <?= $aluno['media'] ?? '-' ?> | 
                            Faltas: <?= $aluno['faltas'] ?? '-' ?> | 
                            Status: <?= $aluno['status'] ?>
                        </small>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>Nenhum aluno em situação de risco.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Botões de Exportação -->
    <div class="export-buttons">
        <a href="relatorio_frequencia.php" class="btn-export">Relatório de Frequência</a>
        <a href="relatorio_notas.php" class="btn-export">Relatório de Notas</a>
        <a href="?export=pdf" class="btn-export">Exportar PDF</a>
        <a href="?export=excel" class="btn-export">Exportar Excel</a>
    </div>
    
    <div style="margin-top: 30px;">
        <a href="<?= $tipo_usuario === 'admin' ? 'dashboard_admin.php' : 'dashboard_professor.php' ?>">
            <button>Voltar ao Dashboard</button>
        </a>
    </div>
</div>

<script>
// Dados para os gráficos
const dadosTurmas = <?= json_encode($dados_grafico_turmas) ?>;

// Gráfico de Desempenho
if (document.getElementById('graficoDesempenho') && dadosTurmas.length > 0) {
    const ctx1 = document.getElementById('graficoDesempenho').getContext('2d');
    new Chart(ctx1, {
        type: 'bar',
        data: {
            labels: dadosTurmas.map(item => item.turma),
            datasets: [
                {
                    label: 'Média das Notas',
                    data: dadosTurmas.map(item => item.media),
                    backgroundColor: 'rgba(54, 162, 235, 0.8)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Taxa de Aprovação (%)',
                    data: dadosTurmas.map(item => item.taxa_aprovacao),
                    backgroundColor: 'rgba(75, 192, 192, 0.8)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 10,
                    title: {
                        display: true,
                        text: 'Média das Notas'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    beginAtZero: true,
                    max: 100,
                    title: {
                        display: true,
                        text: 'Taxa de Aprovação (%)'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: 'Média de Notas e Taxa de Aprovação por Turma'
                }
            }
        }
    });
}

// Gráfico de Frequência
if (document.getElementById('graficoFrequencia') && dadosTurmas.length > 0) {
    const ctx2 = document.getElementById('graficoFrequencia').getContext('2d');
    new Chart(ctx2, {
        type: 'line',
        data: {
            labels: dadosTurmas.map(item => item.turma),
            datasets: [{
                label: 'Frequência Média (%)',
                data: dadosTurmas.map(item => item.frequencia),
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    title: {
                        display: true,
                        text: 'Frequência (%)'
                    }
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: 'Frequência Média por Turma'
                }
            }
        }
    });
}
</script>
</body>
</html>