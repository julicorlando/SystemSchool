<?php
session_start();
include "conexao.php";

// Verificar se está logado
if (!isset($_SESSION['tipo']) || !isset($_SESSION['id'])) {
    header("Location: index.php");
    exit;
}

$usuario_tipo = $_SESSION['tipo'];
$usuario_id = $_SESSION['id'];
$acao = $_GET['acao'] ?? 'listar';

// Processar upload de documento
if ($_POST && isset($_POST['acao']) && $_POST['acao'] === 'upload') {
    $aluno_id = $_POST['aluno_id'] ?? $usuario_id;
    $tipo_documento = limpar_entrada($_POST['tipo']);
    
    // Verificar permissão para upload
    $pode_upload = false;
    if ($usuario_tipo === 'admin') {
        $pode_upload = true;
    } elseif ($usuario_tipo === 'aluno' && $aluno_id == $usuario_id) {
        $pode_upload = true;
    }
    
    if ($pode_upload && isset($_FILES['documento'])) {
        $upload_result = upload_arquivo(
            $_FILES['documento'], 
            'uploads/documentos', 
            ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'], 
            10485760 // 10MB
        );
        
        if ($upload_result['success']) {
            try {
                // Desativar versões anteriores do mesmo tipo
                $stmt = $conn->prepare("UPDATE documentos SET ativo = 0 WHERE aluno_id = ? AND tipo = ?");
                $stmt->bind_param("is", $aluno_id, $tipo_documento);
                $stmt->execute();
                
                // Inserir novo documento
                $stmt = $conn->prepare("
                    INSERT INTO documentos (aluno_id, tipo, nome_arquivo, arquivo_original, tamanho, mime_type, uploaded_by, uploaded_by_tipo) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("isssisis", 
                    $aluno_id, 
                    $tipo_documento, 
                    $upload_result['arquivo'],
                    $_FILES['documento']['name'],
                    $upload_result['tamanho'],
                    $upload_result['tipo'],
                    $usuario_id,
                    $usuario_tipo
                );
                
                if ($stmt->execute()) {
                    $_SESSION['msg_sucesso'] = 'Documento enviado com sucesso!';
                } else {
                    $_SESSION['msg_erro'] = 'Erro ao registrar documento no banco de dados.';
                    deletar_arquivo($upload_result['caminho']);
                }
            } catch (Exception $e) {
                $_SESSION['msg_erro'] = 'Erro ao processar documento: ' . $e->getMessage();
                deletar_arquivo($upload_result['caminho']);
            }
        } else {
            $_SESSION['msg_erro'] = $upload_result['error'];
        }
    } else {
        $_SESSION['msg_erro'] = 'Você não tem permissão para fazer upload para este aluno.';
    }
    
    header("Location: documentos.php");
    exit;
}

// Processar download de documento
if (isset($_GET['download'])) {
    $documento_id = intval($_GET['download']);
    
    // Verificar permissão para download
    $where_permissao = "";
    if ($usuario_tipo === 'aluno') {
        $where_permissao = "AND d.aluno_id = $usuario_id";
    }
    
    $stmt = $conn->prepare("
        SELECT d.*, a.nome as aluno_nome 
        FROM documentos d 
        JOIN alunos a ON d.aluno_id = a.id 
        WHERE d.id = ? AND d.ativo = 1 $where_permissao
    ");
    $stmt->bind_param("i", $documento_id);
    $stmt->execute();
    $documento = $stmt->get_result()->fetch_assoc();
    
    if ($documento) {
        $caminho_arquivo = "uploads/documentos/" . $documento['nome_arquivo'];
        
        if (file_exists($caminho_arquivo)) {
            header('Content-Type: ' . $documento['mime_type']);
            header('Content-Disposition: attachment; filename="' . $documento['arquivo_original'] . '"');
            header('Content-Length: ' . filesize($caminho_arquivo));
            readfile($caminho_arquivo);
            exit;
        } else {
            $_SESSION['msg_erro'] = 'Arquivo não encontrado.';
        }
    } else {
        $_SESSION['msg_erro'] = 'Documento não encontrado ou sem permissão.';
    }
}

// Buscar documentos conforme permissão
$documentos = null;
$alunos = null;

if ($usuario_tipo === 'admin') {
    // Admin pode ver todos os documentos
    $aluno_filtro = $_GET['aluno_id'] ?? '';
    $where_aluno = $aluno_filtro ? "AND d.aluno_id = " . intval($aluno_filtro) : "";
    
    $documentos = $conn->query("
        SELECT d.*, a.nome as aluno_nome, a.matricula
        FROM documentos d
        JOIN alunos a ON d.aluno_id = a.id
        WHERE d.ativo = 1 $where_aluno
        ORDER BY d.created_at DESC
    ");
    
    // Buscar lista de alunos para filtro
    $alunos = $conn->query("SELECT id, nome, matricula FROM alunos ORDER BY nome");
    
} elseif ($usuario_tipo === 'aluno') {
    // Aluno só vê seus próprios documentos
    $stmt = $conn->prepare("
        SELECT d.*, a.nome as aluno_nome, a.matricula
        FROM documentos d
        JOIN alunos a ON d.aluno_id = a.id
        WHERE d.aluno_id = ? AND d.ativo = 1
        ORDER BY d.created_at DESC
    ");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $documentos = $stmt->get_result();
}

// Tipos de documentos disponíveis
$tipos_documento = [
    'matricula' => 'Documentos de Matrícula',
    'identidade' => 'RG/CNH',
    'cpf' => 'CPF',
    'comprovante_residencia' => 'Comprovante de Residência',
    'historico' => 'Histórico Escolar',
    'certificados' => 'Certificados',
    'atestados' => 'Atestados Médicos',
    'outros' => 'Outros'
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentos Digitais</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .documento-item {
            background: #f8f9fa;
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            border-left: 4px solid #007bff;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .documento-info {
            flex: 1;
        }
        
        .documento-actions {
            display: flex;
            gap: 10px;
        }
        
        .upload-area {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            margin: 20px 0;
            transition: border-color 0.3s;
        }
        
        .upload-area:hover {
            border-color: #007bff;
        }
        
        .upload-area.dragover {
            border-color: #007bff;
            background: #e3f2fd;
        }
        
        .file-icon {
            font-size: 2em;
            margin-bottom: 10px;
        }
        
        .file-pdf { color: #dc3545; }
        .file-doc { color: #007bff; }
        .file-image { color: #28a745; }
        
        .filtros-docs {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 20px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: black;
        }
    </style>
    <script>
        function abrirModal(id) {
            document.getElementById(id).style.display = 'block';
        }
        
        function fecharModal(id) {
            document.getElementById(id).style.display = 'none';
        }
        
        function definirAluno(alunoId) {
            document.getElementById('aluno_id').value = alunoId;
        }
        
        function getFileIcon(filename) {
            const ext = filename.toLowerCase().split('.').pop();
            if (['pdf'].includes(ext)) return '📄';
            if (['doc', 'docx'].includes(ext)) return '📝';
            if (['jpg', 'jpeg', 'png'].includes(ext)) return '🖼️';
            return '📎';
        }
        
        // Drag and drop
        function setupDragDrop() {
            const uploadArea = document.getElementById('uploadArea');
            const fileInput = document.getElementById('documento');
            
            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.classList.add('dragover');
            });
            
            uploadArea.addEventListener('dragleave', () => {
                uploadArea.classList.remove('dragover');
            });
            
            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('dragover');
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    document.getElementById('fileName').textContent = files[0].name;
                }
            });
            
            uploadArea.addEventListener('click', () => {
                fileInput.click();
            });
            
            fileInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    document.getElementById('fileName').textContent = e.target.files[0].name;
                }
            });
        }
        
        document.addEventListener('DOMContentLoaded', setupDragDrop);
    </script>
</head>
<body>
<div class="container">
    <h1>Documentos Digitais</h1>
    
    <?php if (isset($_SESSION['msg_sucesso'])): ?>
        <div class="alert alert-success"><?= $_SESSION['msg_sucesso'] ?></div>
        <?php unset($_SESSION['msg_sucesso']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['msg_erro'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['msg_erro'] ?></div>
        <?php unset($_SESSION['msg_erro']); ?>
    <?php endif; ?>
    
    <!-- Filtros (apenas para admin) -->
    <?php if ($usuario_tipo === 'admin' && $alunos): ?>
        <div class="filtros-docs">
            <form method="get" class="form-row">
                <div>
                    <label>Filtrar por Aluno</label>
                    <select name="aluno_id" onchange="this.form.submit()">
                        <option value="">Todos os alunos</option>
                        <?php while ($aluno = $alunos->fetch_assoc()): ?>
                            <option value="<?= $aluno['id'] ?>" <?= ($_GET['aluno_id'] ?? '') == $aluno['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($aluno['nome']) ?> (<?= htmlspecialchars($aluno['matricula']) ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </form>
        </div>
    <?php endif; ?>
    
    <!-- Botão Upload -->
    <div style="margin: 20px 0;">
        <button onclick="abrirModal('modalUpload')" class="btn-primary">Enviar Documento</button>
    </div>
    
    <!-- Lista de Documentos -->
    <h2><?= $usuario_tipo === 'admin' ? 'Documentos dos Alunos' : 'Meus Documentos' ?></h2>
    
    <?php if ($documentos && $documentos->num_rows > 0): ?>
        <?php while ($doc = $documentos->fetch_assoc()): ?>
            <div class="documento-item">
                <div class="documento-info">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span class="file-icon"><?= getFileIcon($doc['arquivo_original']) ?></span>
                        <div>
                            <h4 style="margin: 0;"><?= htmlspecialchars($tipos_documento[$doc['tipo']] ?? $doc['tipo']) ?></h4>
                            <p style="margin: 5px 0; color: #666;">
                                <strong>Arquivo:</strong> <?= htmlspecialchars($doc['arquivo_original']) ?><br>
                                <?php if ($usuario_tipo === 'admin'): ?>
                                    <strong>Aluno:</strong> <?= htmlspecialchars($doc['aluno_nome']) ?> (<?= htmlspecialchars($doc['matricula']) ?>)<br>
                                <?php endif; ?>
                                <strong>Enviado em:</strong> <?= formatar_data_hora_br($doc['created_at']) ?><br>
                                <strong>Tamanho:</strong> <?= number_format($doc['tamanho'] / 1024, 2) ?> KB
                            </p>
                        </div>
                    </div>
                </div>
                <div class="documento-actions">
                    <a href="?download=<?= $doc['id'] ?>" class="btn btn-info">Download</a>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p>Nenhum documento encontrado.</p>
    <?php endif; ?>
    
    <!-- Modal Upload -->
    <div id="modalUpload" class="modal">
        <div class="modal-content">
            <span class="close" onclick="fecharModal('modalUpload')">&times;</span>
            <h3>Enviar Documento</h3>
            
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="acao" value="upload">
                
                <?php if ($usuario_tipo === 'admin'): ?>
                    <div>
                        <label>Aluno</label>
                        <select name="aluno_id" required>
                            <option value="">Selecione o aluno</option>
                            <?php 
                            if ($alunos) {
                                $alunos->data_seek(0);
                                while ($aluno = $alunos->fetch_assoc()): 
                            ?>
                                <option value="<?= $aluno['id'] ?>">
                                    <?= htmlspecialchars($aluno['nome']) ?> (<?= htmlspecialchars($aluno['matricula']) ?>)
                                </option>
                            <?php endwhile; } ?>
                        </select>
                    </div>
                <?php else: ?>
                    <input type="hidden" name="aluno_id" value="<?= $usuario_id ?>">
                <?php endif; ?>
                
                <div>
                    <label>Tipo de Documento</label>
                    <select name="tipo" required>
                        <option value="">Selecione o tipo</option>
                        <?php foreach ($tipos_documento as $key => $label): ?>
                            <option value="<?= $key ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label>Arquivo</label>
                    <div id="uploadArea" class="upload-area">
                        <div class="file-icon">📎</div>
                        <p>Clique aqui ou arraste um arquivo</p>
                        <p><small>Formatos aceitos: PDF, DOC, DOCX, JPG, PNG (máx. 10MB)</small></p>
                        <p id="fileName" style="font-weight: bold; color: #007bff;"></p>
                    </div>
                    <input type="file" id="documento" name="documento" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required style="display: none;">
                </div>
                
                <button type="submit">Enviar Documento</button>
            </form>
        </div>
    </div>
    
    <div style="margin-top: 30px;">
        <a href="<?= 
            $usuario_tipo === 'admin' ? 'dashboard_admin.php' : 
            ($usuario_tipo === 'professor' ? 'dashboard_professor.php' : 'dashboard_aluno.php') 
        ?>"><button>Voltar ao Dashboard</button></a>
    </div>
</div>

<script>
// Adicionar a função getFileIcon no JavaScript
function getFileIcon(filename) {
    const ext = filename.toLowerCase().split('.').pop();
    if (['pdf'].includes(ext)) return '📄';
    if (['doc', 'docx'].includes(ext)) return '📝';
    if (['jpg', 'jpeg', 'png'].includes(ext)) return '🖼️';
    return '📎';
}
</script>
</body>
</html>