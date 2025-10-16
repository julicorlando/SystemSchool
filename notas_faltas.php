<?php
session_start();
include "conexao.php";
$tipo = $_SESSION['tipo'];
$id = $_SESSION['id'];

// Função para verificar se turma está finalizada
function turma_finalizada($conn, $turma_id) {
    $res = $conn->query("SELECT finalizada FROM turmas WHERE id=$turma_id")->fetch_assoc();
    return !empty($res) && $res['finalizada'] == 1;
}

// ADMIN ou PROFESSOR: atribui notas/faltas
if(($tipo == "admin" || $tipo == "professor") && isset($_POST['aluno_id'])) {
    $aluno_id = $_POST['aluno_id'];
    $nota1 = floatval($_POST['nota1']);
    $nota2 = floatval($_POST['nota2']);
    $faltas = intval($_POST['faltas']);
    $media = ($nota1 + $nota2) / 2;
    $conn->query("REPLACE INTO notas_faltas (aluno_id, nota1, nota2, media, faltas) VALUES ($aluno_id, $nota1, $nota2, $media, $faltas)");
    echo "<script>alert('Notas/Faltas atualizadas!');window.location='notas_faltas.php" . (isset($_GET['turma_id']) ? '?turma_id='.$_GET['turma_id'] : '') . "';</script>";
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
    // O professor pode ter mais de uma turma
    $alunos = $conn->query("SELECT a.id, a.nome, t.nome as turma, t.id as turma_id 
        FROM alunos a JOIN turmas t ON t.id=a.turma_id 
        WHERE t.professor_id=$id ORDER BY a.nome ASC");
}

// ALUNO: ver suas notas e faltas
if($tipo == "aluno") {
    $dados = $conn->query("SELECT nf.nota1, nf.nota2, nf.media, nf.faltas, t.nome as turma, t.id as turma_id 
        FROM notas_faltas nf JOIN alunos a ON a.id=nf.aluno_id JOIN turmas t ON t.id=a.turma_id 
        WHERE nf.aluno_id=$id")->fetch_assoc();
    // Regras de aprovação
    $status = "-";
    if($dados) {
        $media = $dados['media'];
        $faltas = $dados['faltas'];
        if ($faltas >= 4) {
            $status = "Reprovado por faltas";
        } elseif ($media >= 7) {
            $status = "Aprovado";
        } else {
            $status = "Recuperação";
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
                        <option value="<?=$t['id']?>" <?=($turma_id==$t['id']?'selected':'')?>><?=$t['nome']?></option>
                <?php } } ?>
            </select>
        </form>
        <?php if(isset($alunos)) { ?>
        <table>
            <tr><th>Aluno</th><th>Turma</th><th>Nota 1</th><th>Nota 2</th><th>Média</th><th>Faltas</th><th>Status</th><th>Pendente</th><th>Ações</th></tr>
            <?php while($a = $alunos->fetch_assoc()) {
                $nf = $conn->query("SELECT nota1, nota2, media, faltas FROM notas_faltas WHERE aluno_id=".$a['id'])->fetch_assoc();
                $media = $nf['media'] ?? null;
                $faltas = $nf['faltas'] ?? null;
                // Status
                if ($media !== null && $faltas !== null) {
                    if ($faltas >= 4) {
                        $status = "Reprovado por faltas";
                    } elseif ($media >= 7) {
                        $status = "Aprovado";
                    } else {
                        $status = "Recuperação";
                    }
                } else {
                    $status = "-";
                }
                $bloquear = turma_finalizada($conn, $a['turma_id']);
                
                // Contar atividades pendentes: tipo='atividade' E sem resposta do aluno
                $pendentes_query = "SELECT COUNT(*) as total FROM atividades atv 
                                    WHERE atv.turma_id={$a['turma_id']} 
                                    AND (atv.tipo='atividade' OR atv.tipo IS NULL)
                                    AND NOT EXISTS (
                                        SELECT 1 FROM respostas r 
                                        WHERE r.atividade_id=atv.id AND r.aluno_id={$a['id']}
                                    )";
                $pendentes = $conn->query($pendentes_query)->fetch_assoc()['total'];
            ?>
            <form method="post">
            <tr>
                <td><?=$a['nome']?></td>
                <td><?=$a['turma']?></td>
                <td><input type="number" step="0.01" name="nota1" value="<?=$nf['nota1']??''?>" required <?=($bloquear?'readonly':'')?>></td>
                <td><input type="number" step="0.01" name="nota2" value="<?=$nf['nota2']??''?>" required <?=($bloquear?'readonly':'')?>></td>
                <td><?=($nf['media']??'-')?></td>
                <td><input type="number" name="faltas" value="<?=$nf['faltas']??''?>" required <?=($bloquear?'readonly':'')?>></td>
                <td><?=$status?></td>
                <td><?=$pendentes?> atividade<?=($pendentes!=1?'s':'')?></td>
                <td>
                    <input type="hidden" name="aluno_id" value="<?=$a['id']?>">
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
            <tr><th>Aluno</th><th>Turma</th><th>Nota 1</th><th>Nota 2</th><th>Média</th><th>Faltas</th><th>Status</th><th>Pendente</th><th>Ações</th></tr>
            <?php while($a = $alunos->fetch_assoc()) {
                $nf = $conn->query("SELECT nota1, nota2, media, faltas FROM notas_faltas WHERE aluno_id=".$a['id'])->fetch_assoc();
                $media = $nf['media'] ?? null;
                $faltas = $nf['faltas'] ?? null;
                if ($media !== null && $faltas !== null) {
                    if ($faltas >= 4) {
                        $status = "Reprovado por faltas";
                    } elseif ($media >= 7) {
                        $status = "Aprovado";
                    } else {
                        $status = "Recuperação";
                    }
                } else {
                    $status = "-";
                }
                $bloquear = turma_finalizada($conn, $a['turma_id']);
                
                // Contar atividades pendentes: tipo='atividade' E sem resposta do aluno
                $pendentes_query = "SELECT COUNT(*) as total FROM atividades atv 
                                    WHERE atv.turma_id={$a['turma_id']} 
                                    AND (atv.tipo='atividade' OR atv.tipo IS NULL)
                                    AND NOT EXISTS (
                                        SELECT 1 FROM respostas r 
                                        WHERE r.atividade_id=atv.id AND r.aluno_id={$a['id']}
                                    )";
                $pendentes = $conn->query($pendentes_query)->fetch_assoc()['total'];
            ?>
            <form method="post">
            <tr>
                <td><?=$a['nome']?></td>
                <td><?=$a['turma']?></td>
                <td><input type="number" step="0.01" name="nota1" value="<?=$nf['nota1']??''?>" required <?=($bloquear?'readonly':'')?>></td>
                <td><input type="number" step="0.01" name="nota2" value="<?=$nf['nota2']??''?>" required <?=($bloquear?'readonly':'')?>></td>
                <td><?=($nf['media']??'-')?></td>
                <td><input type="number" name="faltas" value="<?=$nf['faltas']??''?>" required <?=($bloquear?'readonly':'')?>></td>
                <td><?=$status?></td>
                <td><?=$pendentes?> atividade<?=($pendentes!=1?'s':'')?></td>
                <td>
                    <input type="hidden" name="aluno_id" value="<?=$a['id']?>">
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
        <p>Turma: <?=$dados['turma']?></p>
        <p>Nota 1: <?=$dados['nota1']??' - '?></p>
        <p>Nota 2: <?=$dados['nota2']??' - '?></p>
        <p>Média: <?=$dados['media']??' - '?></p>
        <p>Faltas: <?=$dados['faltas']??' - '?></p>
        <p>Status: <b><?=$status?></b></p>
    <?php } ?>
    <a href="<?php
        if($tipo=='admin') echo 'dashboard_admin.php';
        else if($tipo=='professor') echo 'dashboard_professor.php';
        else echo 'dashboard_aluno.php';
    ?>"><button>Voltar</button></a>
</div>
</body>
</html>