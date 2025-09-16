<?php
session_start();
include "conexao.php";
if($_SESSION['tipo'] !== "professor") header("Location: index.php");
$professor_id = $_SESSION['id'];

// Cadastro de prova
if(isset($_POST['titulo']) && isset($_POST['materia_id']) && isset($_POST['num_questoes'])) {
    $titulo = $_POST['titulo'];
    $descricao = $_POST['descricao'] ?? '';
    $materia_id = intval($_POST['materia_id']);
    $num_questoes = intval($_POST['num_questoes']);
    
    // Verificar se há questões suficientes na matéria
    $count_questoes = $conn->query("SELECT COUNT(*) as total FROM questoes WHERE materia_id=$materia_id AND professor_id=$professor_id")->fetch_assoc();
    
    if($count_questoes['total'] < $num_questoes) {
        echo "<script>alert('Você não tem questões suficientes nesta matéria! Disponível: {$count_questoes['total']}, Solicitado: $num_questoes');</script>";
    } else {
        // Criar a prova
        $stmt = $conn->prepare("INSERT INTO provas (titulo, descricao, materia_id, professor_id, total_questoes) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiii", $titulo, $descricao, $materia_id, $professor_id, $num_questoes);
        
        if($stmt->execute()) {
            $prova_id = $conn->insert_id;
            
            // Sortear questões aleatoriamente
            $questoes_sorteadas = $conn->query("SELECT id FROM questoes WHERE materia_id=$materia_id AND professor_id=$professor_id ORDER BY RAND() LIMIT $num_questoes");
            
            $ordem = 1;
            while($q = $questoes_sorteadas->fetch_assoc()) {
                $conn->query("INSERT INTO prova_questoes (prova_id, questao_id, ordem) VALUES ($prova_id, {$q['id']}, $ordem)");
                $ordem++;
            }
            
            echo "<script>alert('Prova criada com sucesso! $num_questoes questões foram sorteadas automaticamente.');window.location='listar_provas.php';</script>";
        } else {
            echo "<script>alert('Erro ao criar prova!');</script>";
        }
    }
}

// Listar matérias que têm questões do professor
$materias = $conn->query("SELECT DISTINCT m.id, m.nome, COUNT(q.id) as total_questoes 
                         FROM materias m 
                         JOIN questoes q ON q.materia_id = m.id 
                         WHERE q.professor_id = $professor_id 
                         GROUP BY m.id, m.nome 
                         ORDER BY m.nome ASC");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Criar Prova - Banco de Questões</title>
    <link rel="stylesheet" href="css/style.css">
    <script>
        function atualizarQuestoesDisponiveis() {
            var select = document.getElementById('materia_id');
            var info = document.getElementById('questoes_info');
            var maxInput = document.getElementById('num_questoes');
            
            if(select.value) {
                var opcao = select.options[select.selectedIndex];
                var total = opcao.dataset.total;
                info.innerHTML = 'Questões disponíveis: ' + total;
                maxInput.max = total;
                maxInput.value = Math.min(maxInput.value, total);
            } else {
                info.innerHTML = '';
                maxInput.max = '';
            }
        }
    </script>
</head>
<body>
<div class="container">
    <h1>Criar Nova Prova</h1>
    
    <?php if($materias && $materias->num_rows > 0) { ?>
    
    <form method="post">
        <label>Título da Prova</label>
        <input type="text" name="titulo" required placeholder="Digite o título da prova">
        
        <label>Descrição (opcional)</label>
        <textarea name="descricao" rows="3" placeholder="Descrição da prova..."></textarea>
        
        <label>Matéria</label>
        <select name="materia_id" id="materia_id" required onchange="atualizarQuestoesDisponiveis()">
            <option value="">Selecione uma matéria</option>
            <?php while($m = $materias->fetch_assoc()) { ?>
                <option value="<?=$m['id']?>" data-total="<?=$m['total_questoes']?>">
                    <?=$m['nome']?> (<?=$m['total_questoes']?> questões)
                </option>
            <?php } ?>
        </select>
        <div id="questoes_info" style="color: #666; font-size: 0.9em; margin-top: 5px;"></div>
        
        <label>Número de Questões para Sortear</label>
        <input type="number" name="num_questoes" id="num_questoes" min="1" required placeholder="Ex: 10">
        <div style="color: #666; font-size: 0.9em; margin-top: 5px;">
            As questões serão sorteadas automaticamente da matéria selecionada
        </div>
        
        <button type="submit">Criar Prova</button>
    </form>
    
    <?php } else { ?>
        <p>Você ainda não tem questões cadastradas. <a href="cadastro_questao.php">Cadastre algumas questões</a> antes de criar uma prova.</p>
    <?php } ?>
    
    <div style="margin-top: 20px;">
        <a href="listar_provas.php"><button>Ver Minhas Provas</button></a>
        <a href="dashboard_professor.php"><button>Voltar ao Dashboard</button></a>
    </div>
</div>
</body>
</html>