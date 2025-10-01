<?php
// Corrigido para ambiente de produção hospedado (exemplo: Hostinger, cPanel, etc)
// Troque as credenciais abaixo pelas do SEU servidor:
$host = 'localhost';
$user = 'josdevco_info';          // Seu usuário do banco
$pass = 'Bento121021@';           // Sua senha do banco
$db_name = 'josdevco_info';       // Seu banco de dados

$conn = new mysqli($host, $user, $pass, $db_name);
if ($conn->connect_error) {
    die("Erro na conexão: " . $conn->connect_error);
}

// Lista de alunos (nome e matrícula extraídos da imagem)
$alunos = [
    ["Andre Fernando Silva de Arruda", "ELT230044"],
    ["Clebeson Henrique do Nascimento Oliveira", "ELT230083"],
    ["Danilo Ricardo Figueiredo dos Santos", "EDF140185"],
    ["Everton Cipriano Da silva", "RAD230143"],
    ["Fabio Barros de França Filho", "ELT230085"],
    ["Fausto da Silva Santana dos Santos", "ELT230075"],
    ["Flávio Emerson Lima da Silva", "enf250010"],
    ["Francisco Antãnio da Silva", "ELT230096"],
    ["Gabriela Lopes Gomes De Souza Da Silva", "ENF250084"],
    ["Gerlania Alves da Silva", "RAD230131"],
    ["Gilson Lopes de sena", "230046"],
    ["Gilson Santos Alves Filho", "230131"],
    ["Gleybson Jose de Oliveira souza", "ELT230909"],
    ["Itamar Rodrigues silva", "ELT230078"],
    ["Jarbas Barbosa da Rocha", "ELT230081"],
    ["João Victor da Silva Oliveira", "ELT230047L"],
    ["Kauã José da Silva Alves", "ELT230041"],
    ["Leandro Gustavo Silva de Silva", "ENF210107"],
    ["Lucas Domingos Moreno da Silva", "ELT230108"],
    ["Luciano José da Silva", "ELT230046"],
    ["Maria Beatriz dos Santos Lima", "ADM220087"],
    ["Maria de Lourdes dias de Almeida", "Enfer220167"],
    ["Maria José Silva dos Santos", "ENF180481"],
    ["Miguel Rodrigues Nunes", "ELT230087"],
    ["Natália Maria dos Santos", "Enf250116"],
    ["Paulo Correia de Oliveira", "230042"],
    ["Ryan Vitor da Silva Nascimento", "Enf02057"],
    ["Shirlene da conceição Medeiros lemos", "ENF210235"],
    ["Suení Kelly Do Nascimento Casciano", "ENF240377"]
];

// Função para gerar usuário a partir do nome
function gerar_usuario($nome) {
    $nome = strtolower(trim($nome));
    $nome = iconv('UTF-8', 'ASCII//TRANSLIT', $nome); // remove acentos
    $nome = preg_replace('/[^a-z0-9 ]/', '', $nome);
    $partes = explode(' ', $nome);
    $usuario = $partes[0];
    if (count($partes) > 1) {
        $usuario .= '.' . end($partes);
    }
    return $usuario;
}

$total = 0;
foreach ($alunos as $aluno) {
    $nome = $conn->real_escape_string($aluno[0]);
    $matricula = $conn->real_escape_string($aluno[1]);
    $usuario = gerar_usuario($aluno[0]);
    $usuario = $conn->real_escape_string($usuario);
    $email = "aluno@sememail.com";
    $senha = $matricula; // senha = matrícula pura
    $turma_id = 1; // Use 1 para turma padrão, se existir
    $telefone = "888888888";

    // Verifica se já existe esse nome OU matrícula
    $sql_verifica = "SELECT id FROM alunos WHERE nome = '$nome' OR matricula = '$matricula'";
    $result_verifica = $conn->query($sql_verifica);
    if ($result_verifica->num_rows > 0) {
        echo "Aluno já cadastrado: $nome | Matrícula: $matricula<br>";
        continue;
    }

    // Garante usuário único
    $base_usuario = $usuario;
    $count = 1;
    while ($conn->query("SELECT id FROM alunos WHERE usuario = '$usuario'")->num_rows > 0) {
        $usuario = $base_usuario . $count;
        $usuario = $conn->real_escape_string($usuario);
        $count++;
    }

    // Cadastra aluno
    $sql = "INSERT INTO alunos (nome, usuario, senha, email, turma_id, telefone, matricula)
            VALUES ('$nome', '$usuario', '$senha', '$email', $turma_id, '$telefone', '$matricula')";
    if ($conn->query($sql)) {
        echo "Aluno cadastrado: $nome | Usuário: $usuario | Senha: $senha<br>";
        $total++;
    } else {
        echo "Erro ao cadastrar $nome: " . $conn->error . "<br>";
    }
}
echo "<hr>Total de alunos cadastrados: $total";
$conn->close();
?>