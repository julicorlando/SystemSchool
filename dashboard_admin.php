<?php
session_start();
include "conexao.php";
if($_SESSION['tipo'] !== "admin") header("Location: index.php");

// Finalizar turma
if(isset($_GET['finalizar'])) {
    $id = intval($_GET['finalizar']);
    $conn->query("UPDATE turmas SET finalizada=1 WHERE id=$id");
    header("Location: dashboard_admin.php");
}
// Reabrir turma
if(isset($_GET['reabrir'])) {
    $id = intval($_GET['reabrir']);
    $conn->query("UPDATE turmas SET finalizada=0 WHERE id=$id");
    header("Location: dashboard_admin.php");
}

// Listar turmas
$turmas = $conn->query("SELECT t.id, t.nome, t.turno, p.nome as professor, t.finalizada
                        FROM turmas t LEFT JOIN professores p ON p.id=t.professor_id
                        ORDER BY t.nome ASC");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Painel do Administrador</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container">
    <h1>Painel do Administrador</h1>
    <a href="cadastro_turma.php"><button>Cadastrar Turma</button></a>
    <a href="cadastro_professor.php"><button>Cadastrar Professor</button></a>
    <a href="cadastro_aluno.php"><button>Cadastrar Aluno</button></a>
    <a href="notas_faltas.php"><button>Notas e Faltas</button></a>
    <a href="listar_materias.php"><button>Gerenciar Matérias</button></a>

    <h2>Turmas</h2>
    <table>
        <tr><th>Nome</th><th>Turno</th><th>Professor</th><th>Status</th><th>Ação</th></tr>
        <?php while($t = $turmas->fetch_assoc()) { ?>
            <tr>
                <td><?=$t['nome']?></td>
                <td><?=$t['turno']?></td>
                <td><?=$t['professor']?></td>
                <td><?=($t['finalizada'] ? 'Finalizada' : 'Aberta')?></td>
                <td>
                    <?php if(!$t['finalizada']) { ?>
                        <a href="?finalizar=<?=$t['id']?>"><button>Finalizar</button></a>
                    <?php } else { ?>
                        <a href="?reabrir=<?=$t['id']?>"><button>Reabrir</button></a>
                    <?php } ?>
                </td>
            </tr>
        <?php } ?>
    </table>
    <a href="logout.php"><button>Sair</button></a>
</div>
</body>
</html>