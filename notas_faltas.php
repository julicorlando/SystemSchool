<?php
session_start();
include "conexao.php";
$tipo = $_SESSION['tipo'];
$id = $_SESSION['id'];

// Quantidade de notas solicitadas (ajuste aqui para sua regra, ex: 2)
$notas_solicitadas = 2;

// ADMIN ou PROFESSOR: atribui notas/faltas
if(($tipo == "admin" || $tipo == "professor") && isset($_POST['aluno_id'])) {
    $aluno_id = $_POST['aluno_id'];
    // Notas podem ser deixadas em branco
    $nota1 = $_POST['nota1'] !== "" ? floatval($_POST['nota1']) : null;
    $nota2 = $_POST['nota2'] !== "" ? floatval($_POST['nota2']) : null;

    // Faltas automáticas conforme a frequência
    $faltas = $conn->query("SELECT COUNT(*) AS faltas FROM frequencia WHERE aluno_id=$aluno_id AND presente=0")->fetch_assoc()['faltas'];
    
    // Média sempre pela quantidade de notas solicitadas
    $notas = [];
    $pendente = false;
    if ($nota1 !== null) $notas[] = $nota1; else $pendente = true;
    if ($nota2 !== null) $notas[] = $nota2; else $pendente = true;

    if (count($notas) > 0) {
        $media = array_sum($notas) / $notas_solicitadas;
    } else {
        $media = null;
    }
    $conn->query("REPLACE INTO notas_faltas (aluno_id, nota1, nota2, media, faltas) VALUES ($aluno_id, ".($nota1===null?'NULL':$nota1).", ".($nota2===null?'NULL':$nota2).", ".($media===null?'NULL':$media).", $faltas)");
    echo "<script>alert('Notas/Faltas atualizadas!');window.location='notas_faltas.php" . (isset($_GET['turma_id']) ? '?turma_id='.$_GET['turma_id'] : '') . "';</script>";
    exit;
}

// ADMIN: lista turmas e alunos da turma selecionada
if($tipo == "admin") {
    $turmas = $conn->query("SELECT id, nome FROM turmas ORDER BY nome ASC");
    $turma_id = isset($_GET['turma_id']) ? intval($_GET['turma_id']) : null;
    if($turma_id) {
        $alunos = $conn->query("SELECT a.id, a.nome, t.nome as turma, t.id as turma_id 
            FROM alunos a JOIN turmas t ON t.id=a.turma_id 
            WHERE t.id=$turma_id ORDER BY a.nome ASC");
    }
}

// PROFESSOR: lista alunos da(s) sua(s) turma(s)
if($tipo == "professor") {
    $alunos = $conn->query("SELECT a.id, a.nome, t.nome as turma, t.id as turma_id 
        FROM alunos a JOIN turmas t ON t.id=a.turma_id 
        WHERE t.professor_id=$id ORDER BY a.nome ASC");
}

// ALUNO: ver suas notas e faltas
if($tipo == "aluno") {
    $dados = $conn->query("SELECT nf.nota1, nf.nota2, nf.media, nf.faltas, t.nome as turma, t.id as turma_id 
        FROM notas_faltas nf 
        JOIN alunos a ON a.id=nf.aluno_id 
        JOIN turmas t ON t.id=a.turma_id 
        WHERE nf.aluno_id=$id")->fetch_assoc();

    // Se não encontrou, pega só os dados da turma
    if (!$dados) {
        $dados = $conn->query("SELECT t.nome as turma, t.id as turma_id 
            FROM alunos a 
            JOIN turmas t ON t.id=a.turma_id 
            WHERE a.id=$id")->fetch_assoc();
    }

    // Regras de aprovação
    $status = "-";
    $pendente = false;
    if(isset($dados['media']) && isset($dados['faltas'])) {
        $media = $dados['media'];
        $faltas = $dados['faltas'];
        if (!isset($dados['nota1']) || $dados['nota1'] === null || $dados['nota1'] === "") $pendente = true;
        if (!isset($dados['nota2']) || $dados['nota2'] === null || $dados['nota2'] === "") $pendente = true;

        if ($pendente) {
            $status = "Pendente nota(s)";
        } elseif ($faltas >= 4) {
            $status = "Reprovado por faltas";
        } elseif ($media !== null && $media >= 7) {
            $status = "Aprovado";
        } elseif ($media !== null && $media >= 5) {
            $status = "Recuperação";
        } else {
            $status = $media !== null ? "Reprovado" : "-";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Notas e Faltas</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container">
    <h1>Notas e Faltas</h1>
    <?php if($tipo == "admin") { ?>
        <form method="get">
            <label>Selecione a Turma</label>
            <select name="turma_id" onchange="this.form.submit()">
                <option value="">Selecione...</option>
                <?php 
                if ($turmas) {
                    while($t = $turmas->fetch_assoc()) { ?>
                        <option value="<?=$t['id']?>" <?=($turma_id==$t['id']?'selected':'')?>><?=htmlspecialchars($t['nome'])?></option>
                <?php } } ?>
            </select>
        </form>
        <?php if(isset($alunos)) { ?>
        <table>
            <tr><th>Aluno</th><th>Turma</th><th>Nota 1</th><th>Nota 2</th><th>Média</th><th>Faltas</th><th>Status</th><th>Ações</th></tr>
            <?php while($a = $alunos->fetch_assoc()) {
                $nf = $conn->query("SELECT nota1, nota2, media, faltas FROM notas_faltas WHERE aluno_id=".$a['id'])->fetch_assoc() ?? [];
                $faltas = $conn->query("SELECT COUNT(*) AS faltas FROM frequencia WHERE aluno_id=".$a['id']." AND presente=0")->fetch_assoc()['faltas'];
                $pendente = false;
                if (!isset($nf['nota1']) || $nf['nota1'] === null || $nf['nota1'] === "") $pendente = true;
                if (!isset($nf['nota2']) || $nf['nota2'] === null || $nf['nota2'] === "") $pendente = true;
                $media = (isset($nf['media']) ? $nf['media'] : null);
                // Status
                if ($pendente) {
                    $status = "Pendente nota(s)";
                } elseif ($media !== null && $faltas !== null) {
                    if ($faltas >= 4) {
                        $status = "Reprovado por faltas";
                    } elseif ($media >= 7) {
                        $status = "Aprovado";
                    } elseif ($media >= 5) {
                        $status = "Recuperação";
                    } else {
                        $status = "Reprovado";
                    }
                } else {
                    $status = "-";
                }
                $bloquear = function_exists('turma_finalizada') ? turma_finalizada($conn, $a['turma_id']) : false;
            ?>
            <form method="post">
            <tr>
                <td><?= htmlspecialchars($a['nome']) ?></td>
                <td><?= htmlspecialchars($a['turma']) ?></td>
                <td><input type="number" step="0.01" name="nota1" value="<?= $nf['nota1'] ?? '' ?>" <?=($bloquear?'readonly':'')?>></td>
                <td><input type="number" step="0.01" name="nota2" value="<?= $nf['nota2'] ?? '' ?>" <?=($bloquear?'readonly':'')?>></td>
                <td><?= ($media !== null ? number_format($media,2) : '-') ?></td>
                <td><?= $faltas ?></td>
                <td><?= $status ?></td>
                <td>
                    <input type="hidden" name="aluno_id" value="<?= $a['id'] ?>">
                    <?php if (!$bloquear) { ?>
                    <button type="submit">Salvar</button>
                    <?php } ?>
                </td>
            </tr>
            </form>
            <?php } ?>
        </table>
        <?php } ?>
    <?php } ?>

    <?php if($tipo == "professor") { ?>
        <table>
            <tr><th>Aluno</th><th>Turma</th><th>Nota 1</th><th>Nota 2</th><th>Média</th><th>Faltas</th><th>Status</th><th>Ações</th></tr>
            <?php while($a = $alunos->fetch_assoc()) {
                $nf = $conn->query("SELECT nota1, nota2, media, faltas FROM notas_faltas WHERE aluno_id=".$a['id'])->fetch_assoc() ?? [];
                $faltas = $conn->query("SELECT COUNT(*) AS faltas FROM frequencia WHERE aluno_id=".$a['id']." AND presente=0")->fetch_assoc()['faltas'];
                $pendente = false;
                if (!isset($nf['nota1']) || $nf['nota1'] === null || $nf['nota1'] === "") $pendente = true;
                if (!isset($nf['nota2']) || $nf['nota2'] === null || $nf['nota2'] === "") $pendente = true;
                $media = (isset($nf['media']) ? $nf['media'] : null);
                if ($pendente) {
                    $status = "Pendente nota(s)";
                } elseif ($media !== null && $faltas !== null) {
                    if ($faltas >= 4) {
                        $status = "Reprovado por faltas";
                    } elseif ($media >= 7) {
                        $status = "Aprovado";
                    } elseif ($media >= 5) {
                        $status = "Recuperação";
                    } else {
                        $status = "Reprovado";
                    }
                } else {
                    $status = "-";
                }
                $bloquear = function_exists('turma_finalizada') ? turma_finalizada($conn, $a['turma_id']) : false;
            ?>
            <form method="post">
            <tr>
                <td><?= htmlspecialchars($a['nome']) ?></td>
                <td><?= htmlspecialchars($a['turma']) ?></td>
                <td><input type="number" step="0.01" name="nota1" value="<?= $nf['nota1'] ?? '' ?>" <?=($bloquear?'readonly':'')?>></td>
                <td><input type="number" step="0.01" name="nota2" value="<?= $nf['nota2'] ?? '' ?>" <?=($bloquear?'readonly':'')?>></td>
                <td><?= ($media !== null ? number_format($media,2) : '-') ?></td>
                <td><?= $faltas ?></td>
                <td><?= $status ?></td>
                <td>
                    <input type="hidden" name="aluno_id" value="<?= $a['id'] ?>">
                    <?php if (!$bloquear) { ?>
                    <button type="submit">Salvar</button>
                    <?php } ?>
                </td>
            </tr>
            </form>
            <?php } ?>
        </table>
    <?php } ?>

    <?php if($tipo == "aluno") { ?>
        <h2>Sua Nota e Faltas</h2>
        <p>Turma: <?= $dados['turma'] ?? '-' ?></p>
        <p>Nota 1: <?= $dados['nota1'] ?? '-' ?></p>
        <p>Nota 2: <?= $dados['nota2'] ?? '-' ?></p>
        <p>Média: <?= (isset($dados['media']) && $dados['media'] !== null ? number_format($dados['media'],2) : '-') ?></p>
        <p>Faltas: <?= $dados['faltas'] ?? '-' ?></p>
        <p>Status: <b><?= $status ?></b></p>
    <?php } ?>
    <a href="<?php
        if($tipo=='admin') echo 'dashboard_admin.php';
        else if($tipo=='professor') echo 'dashboard_professor.php';
        else echo 'dashboard_aluno.php';
    ?>"><button>Voltar</button></a>
</div>
</body>
</html>