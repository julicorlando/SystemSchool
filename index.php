<?php
session_start();

// Se usuário já estiver logado, redireciona para o dashboard correspondente ao perfil.
// Adicionado suporte ao perfil "cobradores" que irá para dashboard_cobranca.php
if (isset($_SESSION['tipo'])) {
    switch ($_SESSION['tipo']) {
        case "admin":
            header("Location: dashboard_admin.php");
            exit;
        case "professor":
            header("Location: dashboard_professor.php");
            exit;
        case "aluno":
            header("Location: dashboard_aluno.php");
            exit;
        case "cobradores":
            // perfil usado no sistema para funcionários do setor de cobrança
            header("Location: dashboard_cobranca.php");
            exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Sistema Escolar</title>
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
    <h1 style="text-align:center;">Sistema Escolar</h1>
    <form method="post" action="login.php" autocomplete="off">
        <label for="usuario">Usuário</label>
        <input type="text" name="usuario" id="usuario" required>

        <label for="senha">Senha</label>
        <input type="password" name="senha" id="senha" required>

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