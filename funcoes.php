<?php
// Função para criptografar senha
function criptografar_senha($senha) {
    return password_hash($senha, PASSWORD_DEFAULT);
}

// Função para verificar senha
function verificar_senha($senha, $hash) {
    return password_verify($senha, $hash);
}

// Função para limpar entradas (evitar XSS, SQL Injection, etc.)
function limpar_entrada($dados) {
    if (is_array($dados)) {
        return array_map('limpar_entrada', $dados);
    }
    return htmlspecialchars(strip_tags(trim($dados)), ENT_QUOTES, 'UTF-8');
}

// Função para validar email
function validar_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Função para validar CPF
function validar_cpf($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) != 11) return false;
    if (preg_match('/(\d)\1{10}/', $cpf)) return false;
    for ($i = 9; $i < 11; $i++) {
        $d = 0;
        for ($c = 0; $c < $i; $c++) {
            $d += $cpf[$c] * (($i + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$i] != $d) return false;
    }
    return true;
}

// Função para formatar CPF
function formatar_cpf($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);
}

// Função para verificar se turma está finalizada
function turma_finalizada($conn, $turma_id) {
    $stmt = $conn->prepare("SELECT finalizada FROM turmas WHERE id = ?");
    $stmt->bind_param("i", $turma_id);
    $stmt->execute();
    $resultado = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return !empty($resultado) && $resultado['finalizada'] == 1;
}

// Função para gerar senha aleatória
function gerar_senha($tamanho = 8) {
    $caracteres = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $senha = '';
    $max = strlen($caracteres) - 1;
    for ($i = 0; $i < $tamanho; $i++) {
        $senha .= $caracteres[random_int(0, $max)];
    }
    return $senha;
}

// Função para verificar permissões
function verificar_permissao($tipo_usuario, $permissoes_requeridas) {
    if (!is_array($permissoes_requeridas)) {
        $permissoes_requeridas = [$permissoes_requeridas];
    }
    return in_array($tipo_usuario, $permissoes_requeridas);
}

// Função para formatar data brasileira
function formatar_data_br($data) {
    if (empty($data) || $data === '0000-00-00') return '';
    return date('d/m/Y', strtotime($data));
}

// Função para formatar data e hora brasileira
function formatar_data_hora_br($data) {
    if (empty($data) || $data === '0000-00-00 00:00:00') return '';
    return date('d/m/Y H:i', strtotime($data));
}

// Função para converter data brasileira para MySQL
function data_br_para_mysql($data) {
    if (empty($data)) return null;
    $partes = explode('/', $data);
    if (count($partes) == 3) {
        return $partes[2] . '-' . $partes[1] . '-' . $partes[0];
    }
    return null;
}

// Função para calcular idade
function calcular_idade($data_nascimento) {
    if (empty($data_nascimento)) return null;
    $hoje = new DateTime();
    $nascimento = new DateTime($data_nascimento);
    return $hoje->diff($nascimento)->y;
}

// Função para criar notificação
function criar_notificacao($conn, $usuario_id, $usuario_tipo, $titulo, $mensagem, $tipo = 'info') {
    $stmt = $conn->prepare("INSERT INTO notificacoes (usuario_id, usuario_tipo, titulo, mensagem, tipo) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $usuario_id, $usuario_tipo, $titulo, $mensagem, $tipo);
    $stmt->execute();
    $sucesso = $stmt->affected_rows > 0;
    $stmt->close();
    return $sucesso;
}

// Função para contar notificações não lidas
function contar_notificacoes_nao_lidas($conn, $usuario_id, $usuario_tipo) {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM notificacoes WHERE usuario_id = ? AND usuario_tipo = ? AND lida = 0");
    $stmt->bind_param("is", $usuario_id, $usuario_tipo);
    $stmt->execute();
    $resultado = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $resultado['total'] ?? 0;
}

// Função para upload seguro de arquivo
function upload_arquivo($arquivo, $pasta_destino, $tipos_permitidos = ['pdf'], $tamanho_max = 5242880) {
    if (!isset($arquivo['tmp_name']) || empty($arquivo['tmp_name'])) {
        return ['success' => false, 'error' => 'Nenhum arquivo enviado'];
    }
    if ($arquivo['size'] > $tamanho_max) {
        return ['success' => false, 'error' => 'Arquivo muito grande. Máximo ' . ($tamanho_max / 1024 / 1024) . 'MB'];
    }
    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    if (!in_array($extensao, $tipos_permitidos)) {
        return ['success' => false, 'error' => 'Tipo de arquivo não permitido'];
    }
    $nome_arquivo = uniqid('', true) . '.' . $extensao;
    $caminho_completo = rtrim($pasta_destino, '/\\') . '/' . $nome_arquivo;
    if (!is_dir($pasta_destino)) {
        mkdir($pasta_destino, 0755, true);
    }
    if (move_uploaded_file($arquivo['tmp_name'], $caminho_completo)) {
        return [
            'success' => true, 
            'arquivo' => $nome_arquivo,
            'caminho' => $caminho_completo,
            'tamanho' => $arquivo['size'],
            'tipo' => $arquivo['type']
        ];
    } else {
        return ['success' => false, 'error' => 'Erro ao salvar arquivo'];
    }
}

// Função para deletar arquivo
function deletar_arquivo($caminho) {
    if (file_exists($caminho)) {
        return unlink($caminho);
    }
    return false;
}
?>