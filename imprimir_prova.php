<?php
session_start();
include "conexao.php";
if($_SESSION['tipo'] !== "professor") header("Location: index.php");
$professor_id = $_SESSION['id'];

$prova_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$gabarito = isset($_GET['gabarito']) ? true : false;

// Verificar se a prova pertence ao professor
$prova = $conn->query("SELECT p.*, m.nome as materia_nome 
                      FROM provas p 
                      JOIN materias m ON m.id = p.materia_id 
                      WHERE p.id=$prova_id AND p.professor_id=$professor_id")->fetch_assoc();

if(!$prova) {
    echo "<script>alert('Prova não encontrada ou sem permissão!');window.location='listar_provas.php';</script>";
    exit;
}

// Buscar questões da prova
$questoes = $conn->query("SELECT q.*, pq.ordem 
                         FROM questoes q 
                         JOIN prova_questoes pq ON pq.questao_id = q.id 
                         WHERE pq.prova_id = $prova_id 
                         ORDER BY pq.ordem ASC");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title><?=htmlspecialchars($prova['titulo'])?> - <?=($gabarito ? 'Gabarito' : 'Prova')?></title>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; }
        }
        
        body {
            margin: 20px;
            font-family: 'Times New Roman', serif;
            font-size: 12pt;
            line-height: 1.4;
        }
        
        .cabecalho {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        
        .cabecalho h1 {
            margin: 0;
            font-size: 18pt;
            font-weight: bold;
        }
        
        .cabecalho p {
            margin: 5px 0;
            font-size: 11pt;
        }
        
        .info-aluno {
            margin-bottom: 25px;
            border: 1px solid #000;
            padding: 15px;
        }
        
        .info-aluno table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .info-aluno td {
            padding: 5px;
            border-bottom: 1px solid #ccc;
        }
        
        .questao {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        
        .questao-numero {
            font-weight: bold;
            font-size: 13pt;
            margin-bottom: 8px;
        }
        
        .questao-enunciado {
            margin-bottom: 12px;
            text-align: justify;
        }
        
        .alternativa {
            margin: 6px 0;
            padding-left: 20px;
        }
        
        .alternativa.correta {
            font-weight: bold;
            background-color: #e8e8e8;
        }
        
        .gabarito-final {
            margin-top: 30px;
            border: 2px solid #000;
            padding: 15px;
            text-align: center;
        }
        
        .no-print {
            margin: 20px 0;
            text-align: center;
        }
        
        .no-print button {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            margin: 5px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .no-print button:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">🖨️ Imprimir</button>
        <button onclick="window.location='?id=<?=$prova_id?>&gabarito=<?=($gabarito ? '0' : '1')?>'">
            <?=($gabarito ? '📄 Ver sem Gabarito' : '✅ Ver com Gabarito')?>
        </button>
        <button onclick="window.close()">❌ Fechar</button>
    </div>

    <div class="cabecalho">
        <h1><?=htmlspecialchars($prova['titulo'])?></h1>
        <p><strong>Matéria:</strong> <?=$prova['materia_nome']?></p>
        <?php if($prova['descricao']) { ?>
            <p><?=htmlspecialchars($prova['descricao'])?></p>
        <?php } ?>
        <p><strong>Total de questões:</strong> <?=$prova['total_questoes']?> | <strong>Data:</strong> <?=date('d/m/Y')?></p>
        <?php if($gabarito) { ?>
            <p style="color: #d32f2f; font-weight: bold;">*** GABARITO ***</p>
        <?php } ?>
    </div>
    
    <?php if(!$gabarito) { ?>
    <div class="info-aluno">
        <table>
            <tr>
                <td style="width: 60%;"><strong>Nome do Aluno:</strong> _________________________________</td>
                <td><strong>Turma:</strong> __________</td>
            </tr>
            <tr>
                <td><strong>Data:</strong> ___/___/_____</td>
                <td><strong>Nota:</strong> __________</td>
            </tr>
        </table>
    </div>
    <?php } ?>
    
    <?php if($questoes && $questoes->num_rows > 0) { ?>
        
        <?php $contador = 1; $gabarito_array = []; while($q = $questoes->fetch_assoc()) { 
            $gabarito_array[] = $q['resposta_correta'];
        ?>
            <div class="questao">
                <div class="questao-numero"><?=$contador?>)</div>
                <div class="questao-enunciado"><?=nl2br(htmlspecialchars($q['enunciado']))?></div>
                
                <div class="alternativa <?=($gabarito && $q['resposta_correta'] == 'A' ? 'correta' : '')?>">
                    a) <?=htmlspecialchars($q['alternativa_a'])?>
                </div>
                <div class="alternativa <?=($gabarito && $q['resposta_correta'] == 'B' ? 'correta' : '')?>">
                    b) <?=htmlspecialchars($q['alternativa_b'])?>
                </div>
                <div class="alternativa <?=($gabarito && $q['resposta_correta'] == 'C' ? 'correta' : '')?>">
                    c) <?=htmlspecialchars($q['alternativa_c'])?>
                </div>
                <div class="alternativa <?=($gabarito && $q['resposta_correta'] == 'D' ? 'correta' : '')?>">
                    d) <?=htmlspecialchars($q['alternativa_d'])?>
                </div>
            </div>
            <?php $contador++; ?>
        <?php } ?>
        
        <?php if($gabarito) { ?>
        <div class="gabarito-final">
            <h3>GABARITO</h3>
            <p style="font-size: 14pt; letter-spacing: 2px;">
                <?php for($i = 0; $i < count($gabarito_array); $i++) { 
                    echo ($i + 1) . ":" . $gabarito_array[$i] . " &nbsp;&nbsp; ";
                    if(($i + 1) % 10 == 0) echo "<br>";
                } ?>
            </p>
        </div>
        <?php } ?>
        
    <?php } else { ?>
        <p>Esta prova não tem questões associadas.</p>
    <?php } ?>
</body>
</html>