<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include "conexao.php";
include_once "funcoes.php";

// Captura e limpa entradas
$usuario = isset($_POST['usuario']) ? trim(limpar_entrada($_POST['usuario'])) : '';
$senha   = isset($_POST['senha']) ? $_POST['senha'] : '';

// Validação
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($usuario) || empty($senha)) {
        echo "<script>alert('Preencha todos os campos');window.location='login.php';</script>";
        exit;
    }

    // Array de tipos/tabelas e SQL
    $tipos = [
        'admin'             => "SELECT id, usuario, senha, nome FROM admins WHERE usuario = ? LIMIT 1",
        'professor'         => "SELECT id, usuario, senha, nome FROM professores WHERE usuario = ? LIMIT 1",
        // O login do aluno pode ser feito tanto por usuario quanto por matricula
        'aluno_usuario'     => "SELECT id, usuario, senha, nome FROM alunos WHERE usuario = ? LIMIT 1",
        'aluno_matricula'   => "SELECT id, matricula AS usuario, senha, nome FROM alunos WHERE matricula = ? LIMIT 1",
        // cobradores (funcionários do setor de cobrança)
        'cobradores'        => "SELECT id, usuario, senha, nome FROM cobradores WHERE usuario = ? LIMIT 1"
    ];

    $authenticated = false;
    $found_tipo = '';
    $found_row = null;

    foreach ($tipos as $tipo => $sql) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            // Log for debug but show generic message to user
            error_log("Prepare failed for login sql ($tipo): " . $conn->error);
            continue;
        }
        $stmt->bind_param("s", $usuario);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $hash = $row['senha'] ?? '';

            // Verificação de senha:
            // - Preferir password_verify para senhas hash
            // - Se não for hash (ou password_verify falhar), comparar texto simples (compatibilidade)
            $valid = false;
            if (!empty($hash) && function_exists('password_verify')) {
                // tenta verificar como hash
                if (password_verify($senha, $hash)) {
                    $valid = true;
                } else {
                    // se hash parece não ser um hash válido, fallback para comparação direta
                    // (isso cobre casos legados onde senha foi armazenada em texto)
                    if ($senha === $hash) $valid = true;
                }
            } else {
                // password_verify indisponível ou hash vazio: comparação direta
                if ($senha === $hash) $valid = true;
            }

            if ($valid) {
                $authenticated = true;
                $found_tipo = $tipo;
                $found_row = $row;
                $stmt->close();
                break;
            }
        }
        $stmt->close();
    }

    if ($authenticated && $found_row) {
        // Normalizar tipo de sessão
        if ($found_tipo === 'aluno_usuario' || $found_tipo === 'aluno_matricula') {
            $session_tipo = 'aluno';
        } elseif ($found_tipo === 'cobradores') {
            // escolhemos o rótulo 'cobradores' (plural) para compatibilidade com páginas criadas
            $session_tipo = 'cobradores';
        } else {
            $session_tipo = $found_tipo; // admin, professor
        }

        // Definir sessão
        $_SESSION['usuario'] = $found_row['usuario'];
        $_SESSION['tipo']    = $session_tipo;
        $_SESSION['id']      = intval($found_row['id']);
        $_SESSION['nome']    = (!empty($found_row['nome'])) ? $found_row['nome'] : $found_row['usuario'];

        // Mapear tipo para arquivo de dashboard (nome dos arquivos no projeto)
        $dashboard_map = [
            'admin'      => 'dashboard_admin.php',
            'professor'  => 'dashboard_professor.php',
            'aluno'      => 'dashboard_aluno.php',
            'cobradores' => 'dashboard_cobranca.php' // cobradores -> dashboard_cobranca.php
        ];

        $conn->close();

        // Redirecionar para dashboard apropriado
        $target = $dashboard_map[$session_tipo] ?? 'index.php';
        header("Location: {$target}");
        exit;
    } else {
        // Autenticação falhou
        $conn->close();
        echo "<script>alert('Usuário/matrícula ou senha inválidos');window.location='login.php';</script>";
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Login - SystemSchool</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .esqueci-senha {
            margin-top: 12px;
            text-align: left;
        }
        .esqueci-senha a {
            color: #007bff;
            font-weight: 500;
            text-decoration: underline;
            cursor: pointer;
            font-size: 1em;
        }
        .esqueci-senha a:hover {
            color: #0056b3;
        }
        /* Pequeno ajuste visual no formulário */
        .container {
            padding: 28px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 8px 28px rgba(2,6,23,0.06);
            margin-top: 6vh;
        }
        label { display:block; margin-top:10px; font-weight:600; }
        input[type="text"], input[type="password"] {
            width:100%;
            padding:10px 12px;
            border-radius:8px;
            border:1px solid #d6dae1;
            margin-top:6px;
            box-sizing:border-box;
        }
        button[type="submit"] {
            background:#2563eb;
            color:#fff;
            border:0;
            padding:10px 14px;
            border-radius:8px;
            font-weight:700;
            cursor:pointer;
        }
        .login-footer {
            margin-top:12px;
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:8px;
        }
        .role-note { font-size:0.9rem; color:#6b7280; }
    </style>
</head>
<body>
<div class="container" style="max-width:400px;margin:auto;">
    <h1 style="text-align:center;">Login</h1>
    <form method="post" action="login.php" autocomplete="off">
        <label>Usuário ou Matrícula:</label>
        <input type="text" name="usuario" required>
        <label>Senha:</label>
        <input type="password" name="senha" required>
        <div class="login-footer">
            <div class="esqueci-senha">
                <a href="recuperar_senha.php">Esqueci minha senha</a>
            </div>
            <button type="submit">Entrar</button>
        </div>
        <div style="margin-top:12px;">
            <p class="role-note">Se você for funcionário do setor de cobranças, faça login com sua conta de cobrador.</p>
        </div>
    </form>
</div>
</body>
</html>