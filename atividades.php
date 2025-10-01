<?php
session_start();
include "conexao.php";
include_once "funcoes.php";
$tipo = $_SESSION['tipo'];
$id = $_SESSION['id'];

// PROFESSOR envia atividade ou conteúdo em PDF
if($tipo == "professor" && isset($_POST['turma_id']) && isset($_POST['titulo']) && isset($_POST['tipo_envio']) && isset($_FILES['pdf'])) {
    $turma_id = intval($_POST['turma_id']);
    $titulo = limpar_entrada($_POST['titulo']);
    $descricao = limpar_entrada($_POST['descricao']);
    $tipo_envio = $_POST['tipo_envio']; // 'atividade' ou 'conteudo'
    $arquivo = $_FILES['pdf'];
    if ($arquivo['type'] == "application/pdf") {
        $nome_arquivo = uniqid().".pdf";
        move_uploaded_file($arquivo['tmp_name'], "uploads/".$nome_arquivo);
        // separa na tabela pelo campo 'tipo' (adicione campo 'tipo' na tabela atividades, varchar(20))
        $conn->query("INSERT INTO atividades (titulo, descricao, turma_id, professor_id, data_envio, arquivo, tipo) VALUES ('$titulo', '$descricao', $turma_id, $id, NOW(), '$nome_arquivo', '$tipo_envio')");
        echo "<script>alert('Arquivo enviado!');window.location='atividades.php';</script>";
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

// ALUNO: baixar atividades/conteúdos da sua turma
if($tipo == "aluno") {
    $aluno = $conn->query("SELECT turma_id FROM alunos WHERE id=$id")->fetch_assoc();
    $turma_id = $aluno['turma_id'];
    // Pega ambos, separados por tipo
    $ativs = $conn->query("SELECT a.*, p.nome as professor FROM atividades a JOIN professores p ON p.id=a.professor_id WHERE turma_id=$turma_id AND tipo='atividade' ORDER BY a.data_envio DESC");
    $conteudos = $conn->query("SELECT a.*, p.nome as professor FROM atividades a JOIN professores p ON p.id=a.professor_id WHERE turma_id=$turma_id AND tipo='conteudo' ORDER BY a.data_envio DESC");
    $turma_finalizada = turma_finalizada($conn, $turma_id);
}

// PROFESSOR: ver respostas dos alunos
if($tipo == "professor" && isset($_GET['atividade_id'])) {
    $atividade_id = intval($_GET['atividade_id']);
    // Pega turma da atividade
    $atv = $conn->query("SELECT turma_id FROM atividades WHERE id=$atividade_id")->fetch_assoc();
    $turma_finalizada = $atv ? turma_finalizada($conn, $atv['turma_id']) : false;
    $respostas = $conn->query("SELECT r.*, al.nome FROM respostas r JOIN alunos al ON al.id=r.aluno_id WHERE r.atividade_id=$atividade_id ORDER BY al.nome ASC");
    // Para controle de acesso, traz todos alunos da turma
    $alunos_turma = $conn->query("SELECT id, nome FROM alunos WHERE turma_id={$atv['turma_id']} ORDER BY nome");
}

// PROFESSOR: minhas atividades/conteúdos
if($tipo == "professor") {
    $ativs_prof = $conn->query("SELECT * FROM atividades WHERE professor_id=$id ORDER BY data_envio DESC");
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Atividades / Conteúdos</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container">
    <h1>Atividades / Conteúdos</h1>
    <?php if($tipo == "professor") { ?>
        <?php if($turmas && $turmas->num_rows > 0) { ?>
        <form method="post" enctype="multipart/form-data">
            <label>Tipo de envio</label>
            <select name="tipo_envio" required>
                <option value="atividade">Atividade</option>
                <option value="conteudo">Conteúdo</option>
            </select>
            <label>Turma</label>
            <select name="turma_id">
                <?php while($t = $turmas->fetch_assoc()) { ?>
                    <option value="<?=$t['id']?>"><?=$t['nome']?></option>
                <?php } ?>
            </select>
            <label>Título</label>
            <input type="text" name="titulo" required>
            <label>Descrição</label>
            <textarea name="descricao" required></textarea>
            <label>Arquivo PDF</label>
            <input type="file" name="pdf" accept="application/pdf" required>
            <button type="submit">Enviar</button>
        </form>
        <?php } else { ?>
            <p>Todas as suas turmas estão finalizadas ou você não possui turmas.</p>
        <?php } ?>

        <h2>Minhas Atividades</h2>
        <?php
        if($ativs_prof) {
            while($a = $ativs_prof->fetch_assoc()) {
                $tipo_label = ($a['tipo']=='conteudo'?'Conteúdo':'Atividade');
                echo "<div><b>{$a['titulo']}</b> ({$a['data_envio']}) <span style='color:gray;'>[$tipo_label]</span> ";
                if ($a['arquivo']) {
                    echo "<a href='uploads/{$a['arquivo']}' target='_blank'><button>Baixar PDF</button></a>";
                }
                echo " <a href='atividades.php?atividade_id={$a['id']}'><button>Ver Respostas / Acessos</button></a></div>";
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
                <b><?=$a['titulo']?></b> <br>
                Professor: <?=$a['professor']?> <br>
                Descrição: <?=$a['descricao']?> <br>
                Enviada em: <?=$a['data_envio']?> <br>
                <span style="color:gray;">[Atividade]</span><br>
                <?php if($a['arquivo']) { ?>
                <a href="baixar_conteudo.php?atividade_id=<?=$a['id']?>" target="_blank"><button>Baixar PDF</button></a>
                <?php } ?>
                <?php
                $ja_enviou = $conn->query("SELECT id FROM respostas WHERE atividade_id={$a['id']} AND aluno_id=$id")->num_rows > 0;
                if(!$turma_finalizada && !$ja_enviou) { ?>
                <a href="enviar_resposta.php?atividade_id=<?=$a['id']?>"><button>Enviar Resposta (PDF)</button></a>
                <?php } elseif($ja_enviou) { ?>
                <span style="color:green;">Você já enviou resposta.</span>
                <?php } ?>
            </div>
        <?php } } ?>
        <h2>Conteúdos da Sua Turma</h2>
        <?php 
        if($conteudos) {
            while($c = $conteudos->fetch_assoc()) { ?>
            <div style="border-bottom:1px solid #eee; padding:10px;">
                <b><?=$c['titulo']?></b> <br>
                Professor: <?=$c['professor']?> <br>
                Descrição: <?=$c['descricao']?> <br>
                Enviado em: <?=$c['data_envio']?> <br>
                <span style="color:gray;">[Conteúdo]</span><br>
                <?php if($c['arquivo']) { ?>
                <a href="baixar_conteudo.php?atividade_id=<?=$c['id']?>" target="_blank"><button>Baixar PDF</button></a>
                <?php } ?>
            </div>
        <?php } } ?>
    <?php } ?>

    <?php if($tipo == "professor" && isset($_GET['atividade_id'])) { ?>
        <h2>Respostas dos Alunos / Acessos</h2>
        <table>
            <tr>
                <th>Aluno</th>
                <th>Acessou</th>
                <th>Arquivo</th>
                <th>Data</th>
                <th>Ações</th>
            </tr>
            <?php
            if ($alunos_turma) {
                while ($aluno = $alunos_turma->fetch_assoc()) {
                    $acesso = $conn->query("SELECT COUNT(*) as total FROM conteudo_acesso WHERE aluno_id={$aluno['id']} AND atividade_id={$_GET['atividade_id']}")->fetch_assoc()['total'];
                    $resposta = $conn->query("SELECT * FROM respostas WHERE atividade_id={$_GET['atividade_id']} AND aluno_id={$aluno['id']}")->fetch_assoc();
                    echo "<tr>";
                    echo "<td>{$aluno['nome']}</td>";
                    echo "<td>" . ($acesso > 0 ? "<span style='color:green'>Sim</span>" : "<span style='color:red'>Não</span>") . "</td>";
                    if ($resposta && $resposta['arquivo']) {
                        echo "<td><a href='uploads/{$resposta['arquivo']}' target='_blank'>PDF</a></td>";
                        echo "<td>{$resposta['data_envio']}</td>";
                        echo "<td>";
                        if (!$turma_finalizada) {
                            echo "<a href='atividades.php?atividade_id={$_GET['atividade_id']}&liberar_resposta=1&aluno_id={$aluno['id']}'><button>Liberar novo envio</button></a>";
                        }
                        echo "</td>";
                    } else {
                        echo "<td>-</td><td>-</td><td>-</td>";
                    }
                    echo "</tr>";
                }
            }
            ?>
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