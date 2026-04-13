<?php
/**
 * SIAE-IMSS - Login
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Si ya está logueado, redirigir
if (isLoggedIn()) {
    header('Location: ' . getHomePage());
    exit;
}

$error = '';

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = post('username');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Por favor ingresa usuario y contraseña';
    } else {
        if (login($username, $password)) {
            header('Location: ' . getHomePage());
            exit;
        } else {
            $error = 'Usuario o contraseña incorrectos';
            
            // Registrar intento fallido en bitácora (sin usuario)
            try {
                $pdo = getConnection();
                $stmt = $pdo->prepare("
                    INSERT INTO bitacora (id_usuario, accion, detalle, ip_address, user_agent) 
                    VALUES (NULL, 'LOGIN_FALLIDO', ?, ?, ?)
                ");
                $stmt->execute([
                    "Intento fallido para: $username",
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
            } catch (Exception $e) {
                // Silenciar
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - SIAE-IMSS</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    
    <style>
        :root {
            --primary: #F97316;
            --primary-hover: #EA580C;
            --secondary: #2563EB;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --text-muted: #94A3B8;
            --bg-primary: #F8FAFC;
            --bg-white: #FFFFFF;
            --border-color: #E2E8F0;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .header {
            background: var(--bg-white);
            padding: 16px 40px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
            font-size: 18px;
            color: var(--text-primary);
        }
        
        .logo-icon {
            width: 36px;
            height: 36px;
            background: var(--secondary);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        
        .header-links {
            display: flex;
            gap: 24px;
        }
        
        .header-links a {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 14px;
        }
        
        .header-links a:hover {
            color: var(--text-primary);
        }
        
        .main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }
        
        .login-container {
            display: flex;
            gap: 60px;
            max-width: 900px;
            width: 100%;
        }
        
        .login-info {
            flex: 1;
            padding-top: 20px;
        }
        
        .login-info h1 {
            font-size: 32px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 16px;
            line-height: 1.3;
        }
        
        .login-info h1 span {
            color: var(--secondary);
        }
        
        .login-info p {
            color: var(--text-secondary);
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 32px;
        }
        
        .security-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--bg-white);
            padding: 12px 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        
        .security-badge i {
            color: var(--secondary);
        }
        
        .security-badge span {
            font-size: 14px;
            color: var(--text-primary);
        }
        
        .login-form-container {
            width: 380px;
            background: var(--bg-white);
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .login-form-container h2 {
            font-size: 22px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .login-form-container .subtitle {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 28px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .form-label i {
            width: 16px;
            height: 16px;
            color: var(--text-muted);
        }
        
        .form-control {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .password-input {
            position: relative;
        }
        
        .password-input input {
            padding-right: 44px;
        }
        
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 4px;
        }
        
        .password-toggle:hover {
            color: var(--text-secondary);
        }
        
        .forgot-link {
            display: block;
            text-align: right;
            font-size: 13px;
            color: var(--secondary);
            text-decoration: none;
            margin-bottom: 24px;
        }
        
        .forgot-link:hover {
            text-decoration: underline;
        }
        
        .btn-login {
            width: 100%;
            padding: 14px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background 0.2s;
        }
        
        .btn-login:hover {
            background: var(--primary-hover);
        }
        
        .footer-text {
            text-align: center;
            margin-top: 32px;
            font-size: 12px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .error-message {
            background: #FEE2E2;
            color: #991B1B;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .page-footer {
            text-align: center;
            padding: 20px;
            font-size: 13px;
            color: var(--text-muted);
        }
        
        @media (max-width: 800px) {
            .login-container {
                flex-direction: column;
                gap: 40px;
            }
            
            .login-info {
                text-align: center;
            }
            
            .login-form-container {
                width: 100%;
                max-width: 400px;
                margin: 0 auto;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">
            <div class="logo-icon">
                <i data-lucide="shield-check" style="width: 20px; height: 20px;"></i>
            </div>
            SIAE-IMSS
        </div>
        <nav class="header-links">
            <a href="#">Ayuda</a>
            <a href="#">Contacto</a>
        </nav>
    </header>
    
    <main class="main">
        <div class="login-container">
            <div class="login-info">
                <h1>Bienvenido al Sistema<br><span>SIAE-IMSS</span></h1>
                <p>Acceda a su portal administrativo para gestionar sus servicios de manera eficiente y segura.</p>
                
                <div class="security-badge">
                    <i data-lucide="shield-check"></i>
                    <span>Acceso Seguro</span>
                </div>
            </div>
            
            <div class="login-form-container">
                <h2>Iniciar Sesión</h2>
                <p class="subtitle">Ingrese sus credenciales de acceso</p>
                
                <?php if ($error): ?>
                <div class="error-message">
                    <i data-lucide="alert-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label">
                            <i data-lucide="user"></i>
                            Usuario
                        </label>
                        <input type="text" name="username" class="form-control" 
                               placeholder="Ingrese su número de usuario" required
                               value="<?= htmlspecialchars(post('username')) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i data-lucide="lock"></i>
                            Contraseña
                        </label>
                        <div class="password-input">
                            <input type="password" name="password" id="password" 
                                   class="form-control" placeholder="••••••••" required>
                            <button type="button" class="password-toggle" onclick="togglePassword()">
                                <i data-lucide="eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>
                    
                    <a href="<?= BASE_URL ?>views/auth/recuperar.php" class="forgot-link">
                        ¿Olvidaste la contraseña?
                    </a>
                    
                    <button type="submit" class="btn-login">
                        Iniciar sesión
                        <i data-lucide="arrow-right"></i>
                    </button>
                </form>
                
                <div class="footer-text">
                    Instituto Mexicano del Seguro Social
                </div>
            </div>
        </div>
    </main>
    
    <footer class="page-footer">
        &copy; <?= date('Y') ?> SIAE-IMSS. Todos los derechos reservados.
    </footer>
    
    <script>
        lucide.createIcons();
        
        function togglePassword() {
            const input = document.getElementById('password');
            const icon = document.getElementById('eyeIcon');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.setAttribute('data-lucide', 'eye-off');
            } else {
                input.type = 'password';
                icon.setAttribute('data-lucide', 'eye');
            }
            lucide.createIcons();
        }
    </script>
</body>
</html>
