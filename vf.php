<?php
// Este script verifica todos os alunos com cadastro completo (usuário, senha, turma_id preenchidos) e marca o campo cadastro_completo = 1 no banco

include "conexao.php";

// Consulta todos alunos que JÁ estão completos mas ainda não estão marcados como cadastro_completo = 1
$sql = "SELECT id, usuario, senha, turma_id, cadastro_completo FROM alunos WHERE cadastro_completo=0";
$res = $conn->query($sql);

$atualizados = 0;

while ($aluno = $res->fetch_assoc()) {
    // Verifica se os dados principais estão preenchidos
    if (
        !empty($aluno['usuario']) &&
        !empty($aluno['senha']) &&
        !empty($aluno['turma_id'])
    ) {
        $id = $aluno['id'];
        $conn->query("UPDATE alunos SET cadastro_completo=1 WHERE id=$id");
        $atualizados++;
    }
}

echo "$atualizados alunos marcados como cadastro completo.";
?>