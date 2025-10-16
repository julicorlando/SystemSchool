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

// PROFESSOR envia atividade em PDF
if($tipo == "professor" && isset($_POST['turma_id']) && isset($_POST['titulo']) && isset($_FILES['pdf'])) {
    $turma_id = intval($_POST['turma_id']);
    $titulo = $_POST['titulo'];
    $descricao = $_POST['descricao'];
    $tipo_atividade = $_POST['tipo_atividade'] ?? 'atividade'; // Padrão: atividade
    $arquivo = $_FILES['pdf'];
    if ($arquivo['type'] == "application/pdf") {
        $nome_arquivo = uniqid().".pdf";
        move_uploaded_file($arquivo['tmp_name'], "uploads/".$nome_arquivo);
        $conn->query("INSERT INTO atividades (titulo, descricao, tipo, turma_id, professor_id, data_envio, arquivo) VALUES ('$titulo', '$descricao', '$tipo_atividade', $turma_id, $id, NOW(), '$nome_arquivo')");
        echo "<script>alert('Atividade enviada!');window.location='atividades.php';</script>";
    } else {
        echo "<script>alert('Envie apenas PDF!');window.location='atividades.php';</script>";
    }
}

// PROFESSOR: liberar novo envio de resposta para aluno
if ($tipo == "professor" && isset($_GET['liberar_resposta']) && isset($_GET['atividade_id']) && isset($_GET['aluno_id'])) {
    $atividade_id = intval($_GET['atividade_id']);
    $aluno_id = intval($_GET['aluno_id']);
    $conn->query("DELETE FROM respostas WHERE atividade_id=$atividade_id AND aluno_id=$aluno_id");
    echo "<script>alert('Novo envio liberado para o aluno!');window.location='atividades.php?atividade_id=$atividade_id';</script>";
}

// PROFESSOR: lista turmas
if($tipo == "professor") {
    $turmas = $conn->query("SELECT id, nome FROM turmas WHERE professor_id=$id AND finalizada=0 ORDER BY nome ASC");
}

// ALUNO: baixar atividades da sua turma
if($tipo == "aluno") {
    $aluno = $conn->query("SELECT turma_id FROM alunos WHERE id=$id")->fetch_assoc();
    $turma_id = $aluno['turma_id'];
    $ativs = $conn->query("SELECT a.*, p.nome as professor FROM atividades a JOIN professores p ON p.id=a.professor_id WHERE turma_id=$turma_id ORDER BY a.data_envio DESC");
    $turma_finalizada = turma_finalizada($conn, $turma_id);
}

// PROFESSOR: ver respostas dos alunos
if($tipo == "professor" && isset($_GET['atividade_id'])) {
    $atividade_id = intval($_GET['atividade_id']);
    // Pega turma da atividade
    $atv = $conn->query("SELECT turma_id FROM atividades WHERE id=$atividade_id")->fetch_assoc();
    $turma_finalizada = $atv ? turma_finalizada($conn, $atv['turma_id']) : false;
    $respostas = $conn->query("SELECT r.*, al.nome FROM respostas r JOIN alunos al ON al.id=r.aluno_id WHERE r.atividade_id=$atividade_id ORDER BY al.nome ASC");
}

// PROFESSOR: minhas atividades
if($tipo == "professor") {
    $ativs_prof = $conn->query("SELECT * FROM atividades WHERE professor_id=$id ORDER BY data_envio DESC");
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Atividades</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container">
    <h1>Atividades</h1>
    <?php if($tipo == "professor") { ?>
        <?php if($turmas && $turmas->num_rows > 0) { ?>
        <form method="post" enctype="multipart/form-data">
            <label>Turma</label>
            <select name="turma_id">
                <?php while($t = $turmas->fetch_assoc()) { ?>
                    <option value="<?=$t['id']?>"><?=$t['nome']?></option>
                <?php } ?>
            </select>
            <label>Tipo</label>
            <select name="tipo_atividade" required>
                <option value="atividade">Atividade (requer resposta do aluno)</option>
                <option value="conteudo">Conteúdo (apenas para leitura)</option>
            </select>
            <label>Título</label>
            <input type="text" name="titulo" required>
            <label>Descrição</label>
            <textarea name="descricao" required></textarea>
            <label>Arquivo PDF da Atividade</label>
            <input type="file" name="pdf" accept="application/pdf" required>
            <button type="submit">Enviar Atividade</button>
        </form>
        <?php } else { ?>
            <p>Todas as suas turmas estão finalizadas ou você não possui turmas.</p>
        <?php } ?>

        <h2>Minhas Atividades</h2>
        <?php
        if($ativs_prof) {
            while($a = $ativs_prof->fetch_assoc()) {
                $tipo_ativ = $a['tipo'] ?? 'atividade';
                $tipo_label = $tipo_ativ == 'conteudo' ? 'CONTEÚDO' : 'ATIVIDADE';
                $tipo_cor = $tipo_ativ == 'conteudo' ? '#3498db' : '#9b59b6';
                echo "<div><b>{$a['titulo']}</b> <span style='background:$tipo_cor;color:white;padding:2px 8px;border-radius:3px;font-size:0.8em;'>$tipo_label</span> ({$a['data_envio']}) ";
                if ($a['arquivo']) {
                    echo "<a href='uploads/{$a['arquivo']}' target='_blank'><button>Baixar PDF</button></a>";
                }
                echo " <a href='atividades.php?atividade_id={$a['id']}'><button>Ver Respostas</button></a></div>";
            }
        }
        ?>
    <?php } ?>

    <?php if($tipo == "aluno") { ?>
        <h2>Atividades da Sua Turma</h2>
        <?php 
        if($ativs) {
            while($a = $ativs->fetch_assoc()) { ?>
            <div style="border-bottom:1px solid #eee; padding:10px;">
                <b><?=$a['titulo']?></b> 
                <?php 
                $tipo_ativ = $a['tipo'] ?? 'atividade';
                if($tipo_ativ == 'conteudo') {
                    echo '<span style="background:#3498db;color:white;padding:2px 8px;border-radius:3px;font-size:0.8em;margin-left:5px;">CONTEÚDO</span>';
                } else {
                    // Verifica se é atividade e se o aluno já enviou
                    $ja_enviou = $conn->query("SELECT id FROM respostas WHERE atividade_id={$a['id']} AND aluno_id=$id")->num_rows > 0;
                    if($ja_enviou) {
                        echo '<span style="background:#27ae60;color:white;padding:2px 8px;border-radius:3px;font-size:0.8em;margin-left:5px;">RESPONDIDA</span>';
                    } else {
                        echo '<span style="background:#e74c3c;color:white;padding:2px 8px;border-radius:3px;font-size:0.8em;margin-left:5px;">PENDENTE</span>';
                    }
                }
                ?>
                <br>
                Professor: <?=$a['professor']?> <br>
                Descrição: <?=$a['descricao']?> <br>
                Enviada em: <?=$a['data_envio']?> <br>
                <?php if($a['arquivo']) { ?>
                <a href="uploads/<?=$a['arquivo']?>" target="_blank"><button>Baixar PDF</button></a>
                <?php } ?>
                <?php
                // Permitir envio de resposta apenas se for atividade (não conteúdo), turma não estiver finalizada e não houver resposta ainda
                if($tipo_ativ == 'atividade') {
                    if(!$turma_finalizada && !$ja_enviou) { ?>
                    <a href="enviar_resposta.php?atividade_id=<?=$a['id']?>"><button>Enviar Resposta (PDF)</button></a>
                    <?php } elseif($ja_enviou) { ?>
                    <span style="color:green;">Você já enviou resposta.</span>
                    <?php }
                } ?>
            </div>
        <?php } } ?>
    <?php } ?>

    <?php if($tipo == "professor" && isset($_GET['atividade_id'])) { ?>
        <h2>Respostas dos Alunos</h2>
        <table>
            <tr><th>Aluno</th><th>Arquivo</th><th>Data</th><th>Ações</th></tr>
            <?php if($respostas) { while($r = $respostas->fetch_assoc()) { ?>
                <tr>
                    <td><?=$r['nome']?></td>
                    <td><a href="uploads/<?=$r['arquivo']?>" target="_blank">PDF</a></td>
                    <td><?=$r['data_envio']?></td>
                    <td>
                        <?php if(!$turma_finalizada) { ?>
                        <a href="atividades.php?atividade_id=<?=$_GET['atividade_id']?>&liberar_resposta=1&aluno_id=<?=$r['aluno_id']?>"><button>Liberar novo envio</button></a>
                        <?php } ?>
                    </td>
                </tr>
            <?php } } ?>
        </table>
        <?php if($turma_finalizada) { ?>
            <p style="color:red"><b>Turma finalizada. Não é possível editar ou responder atividades.</b></p>
        <?php } ?>
        <a href="atividades.php"><button>Voltar</button></a>
    <?php } ?>
    <a href="<?=($tipo=="professor"?"dashboard_professor.php":"dashboard_aluno.php")?>"><button>Voltar</button></a>
</div>
</body>
</html>