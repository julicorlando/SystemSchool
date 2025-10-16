<?php
/**
 * Migration script to add 'tipo' field to atividades table
 * Run this once to update the database schema
 */
include "conexao.php";

// Add tipo column to atividades table if it doesn't exist
$check = $conn->query("SHOW COLUMNS FROM atividades LIKE 'tipo'");
if($check->num_rows == 0) {
    $conn->query("ALTER TABLE atividades ADD COLUMN tipo ENUM('conteudo', 'atividade') NOT NULL DEFAULT 'atividade' AFTER descricao");
    echo "Campo 'tipo' adicionado à tabela 'atividades' com sucesso!<br>";
} else {
    echo "Campo 'tipo' já existe na tabela 'atividades'.<br>";
}

echo "<br><a href='dashboard_admin.php'>Voltar ao Dashboard</a>";
?>
