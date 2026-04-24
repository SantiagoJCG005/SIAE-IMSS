<?php
/**
 *  * SI FUNCIONA NO LE MUEVAS!!!!!
 * SIAE-IMSS - Login
 * Es la pagina de inicio de sesion donde los usuarios escriben su nombre
 * de usuario y contrasena para entrar al sistema.
 */

// Carga el archivo con la configuracion general del sistema (rutas, constantes)
require_once __DIR__ . '/../../config/config.php';

// Carga el archivo que permite conectarse a la base de datos
require_once __DIR__ . '/../../config/database.php';

// Carga el archivo con funciones auxiliares que se usan en todo el sistema
require_once __DIR__ . '/../../includes/functions.php';

// Carga el archivo que maneja la autenticacion (login, logout, sesiones)
require_once __DIR__ . '/../../includes/auth.php';

// Verifica si el usuario ya tiene una sesion activa (ya esta logueado)
if (estaLogueado()) {

    // Si ya esta logueado, lo redirige a su pagina principal segun su rol
    header('Location: ' . obtenerPaginaInicio());

    // Termina la ejecucion para que no cargue el resto de la pagina
    exit;
}

// Variable donde se guardara el mensaje de error si el login falla
$mensajeError = '';

// Verifica si se envio el formulario (el usuario hizo clic en el boton)
// POST significa que se enviaron datos desde un formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Obtiene el nombre de usuario que escribio en el campo
    $nombreUsuario = obtenerPost('username');

    // Obtiene la contrasena, si no existe queda como cadena vacia
    $contrasena = $_POST['password'] ?? '';
    
    // Verifica si alguno de los campos esta vacio
    if (empty($nombreUsuario) || empty($contrasena)) {

        // Guarda mensaje de error pidiendo que llene ambos campos
        $mensajeError = 'Por favor ingresa usuario y contraseña';

    } else {

        // Intenta hacer login con los datos proporcionados
        // La funcion iniciarSesion() verifica en la base de datos si son correctos
        if (iniciarSesion($nombreUsuario, $contrasena)) {

            // Si el login fue exitoso, redirige a la pagina principal del usuario
            header('Location: ' . obtenerPaginaInicio());

            // Termina la ejecucion
            exit;

        } else {

            // Si el login fallo, guarda mensaje de error
            $mensajeError = 'Usuario o contraseña incorrectos';
            
            // Intenta registrar el intento fallido en la bitacora
            // Esto sirve para detectar intentos de hackeo
            try {

                // Se conecta a la base de datos
                $conexion = obtenerConexion();

                // Prepara la consulta SQL para insertar el registro
                $consulta = $conexion->prepare("
                    INSERT INTO bitacora (id_usuario, accion, detalle, ip_address, user_agent) 
                    VALUES (NULL, 'LOGIN_FALLIDO', ?, ?, ?)
                ");

                // Ejecuta la consulta con los datos
                $consulta->execute([
                    "Intento fallido para: $nombreUsuario",
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);

            } catch (Exception $e) {

                // Si hay error al guardar, lo ignora para no mostrar errores tecnicos
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
            --primario: #F97316;
            --primario-hover: #EA580C;
            --secundario: #2563EB;
            --texto-primario: #1E293B;
            --texto-secundario: #64748B;
            --texto-atenuado: #94A3B8;
            --fondo-primario: #F8FAFC;
            --fondo-blanco: #FFFFFF;
            --color-borde: #E2E8F0;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--fondo-primario);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .encabezado {
            background: var(--fondo-blanco);
            padding: 16px 40px;
            border-bottom: 1px solid var(--color-borde);
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
            color: var(--texto-primario);
        }
        
        .logo-icono {
            width: 36px;
            height: 36px;
            background: var(--secundario);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        
        .enlaces-encabezado {
            display: flex;
            gap: 24px;
        }
        
        .enlaces-encabezado a {
            color: var(--texto-secundario);
            text-decoration: none;
            font-size: 14px;
        }
        
        .enlaces-encabezado a:hover {
            color: var(--texto-primario);
        }
        
        .principal {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }
        
        .contenedor-login {
            display: flex;
            gap: 60px;
            max-width: 900px;
            width: 100%;
        }
        
        .info-login {
            flex: 1;
            padding-top: 20px;
        }
        
        .info-login h1 {
            font-size: 32px;
            font-weight: 600;
            color: var(--texto-primario);
            margin-bottom: 16px;
            line-height: 1.3;
        }
        
        .info-login h1 span {
            color: var(--secundario);
        }
        
        .info-login p {
            color: var(--texto-secundario);
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 32px;
        }
        
        .insignia-seguridad {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--fondo-blanco);
            padding: 12px 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        
        .insignia-seguridad i {
            color: var(--secundario);
        }
        
        .insignia-seguridad span {
            font-size: 14px;
            color: var(--texto-primario);
        }
        
        .contenedor-formulario {
            width: 380px;
            background: var(--fondo-blanco);
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .contenedor-formulario h2 {
            font-size: 22px;
            font-weight: 600;
            color: var(--texto-primario);
            margin-bottom: 8px;
        }
        
        .contenedor-formulario .subtitulo {
            color: var(--texto-secundario);
            font-size: 14px;
            margin-bottom: 28px;
        }
        
        .grupo-campo {
            margin-bottom: 20px;
        }
        
        .etiqueta-campo {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 500;
            color: var(--texto-primario);
            margin-bottom: 8px;
        }
        
        .etiqueta-campo i {
            width: 16px;
            height: 16px;
            color: var(--texto-atenuado);
        }
        
        .campo-formulario {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--color-borde);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .campo-formulario:focus {
            outline: none;
            border-color: var(--secundario);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .campo-contrasena {
            position: relative;
        }
        
        .campo-contrasena input {
            padding-right: 44px;
        }
        
        .boton-mostrar-contrasena {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--texto-atenuado);
            cursor: pointer;
            padding: 4px;
        }
        
        .boton-mostrar-contrasena:hover {
            color: var(--texto-secundario);
        }
        
        .enlace-olvido {
            display: block;
            text-align: right;
            font-size: 13px;
            color: var(--secundario);
            text-decoration: none;
            margin-bottom: 24px;
        }
        
        .enlace-olvido:hover {
            text-decoration: underline;
        }
        
        .boton-login {
            width: 100%;
            padding: 14px;
            background: var(--primario);
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
        
        .boton-login:hover {
            background: var(--primario-hover);
        }
        
        .texto-pie-formulario {
            text-align: center;
            margin-top: 32px;
            font-size: 12px;
            color: var(--texto-atenuado);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .mensaje-error {
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
        
        .pie-pagina {
            text-align: center;
            padding: 20px;
            font-size: 13px;
            color: var(--texto-atenuado);
        }
        
        @media (max-width: 800px) {
            .contenedor-login {
                flex-direction: column;
                gap: 40px;
            }
            
            .info-login {
                text-align: center;
            }
            
            .contenedor-formulario {
                width: 100%;
                max-width: 400px;
                margin: 0 auto;
            }
        }
    </style>
</head>
<body>
    <header class="encabezado">
        <div class="logo">
            <div class="logo-icono">
                <i data-lucide="shield-check" style="width: 20px; height: 20px;"></i>
            </div>
            SIAE-IMSS
        </div>
        <nav class="enlaces-encabezado">
            <a href="#">Ayuda</a>
            <a href="#">Contacto</a>
        </nav>
    </header>
    
    <main class="principal">
        <div class="contenedor-login">
            <div class="info-login">
                <h1>Bienvenido al Sistema<br><span>SIAE-IMSS</span></h1>
                <p>Acceda a su portal administrativo para gestionar sus servicios de manera eficiente y segura.</p>
                
                <div class="insignia-seguridad">
                    <i data-lucide="shield-check"></i>
                    <span>Acceso Seguro</span>
                </div>
            </div>
            
            <div class="contenedor-formulario">
                <h2>Iniciar Sesión</h2>
                <p class="subtitulo">Ingrese sus credenciales de acceso</p>
                
                <?php if ($mensajeError): ?>
                <div class="mensaje-error">
                    <i data-lucide="alert-circle"></i>
                    <?= htmlspecialchars($mensajeError) ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="grupo-campo">
                        <label class="etiqueta-campo">
                            <i data-lucide="user"></i>
                            Usuario
                        </label>
                        <input type="text" name="username" class="campo-formulario" 
                               placeholder="Ingrese su número de usuario" required
                               value="<?= htmlspecialchars(obtenerPost('username')) ?>">
                    </div>
                    
                    <div class="grupo-campo">
                        <label class="etiqueta-campo">
                            <i data-lucide="lock"></i>
                            Contraseña
                        </label>
                        <div class="campo-contrasena">
                            <input type="password" name="password" id="campoContrasena" 
                                   class="campo-formulario" placeholder="••••••••" required>
                            <button type="button" class="boton-mostrar-contrasena" onclick="alternarContrasena()">
                                <i data-lucide="eye" id="iconoOjo"></i>
                            </button>
                        </div>
                    </div>
                    
                    <a href="<?= URL_BASE ?>views/auth/recuperar.php" class="enlace-olvido">
                        ¿Olvidaste la contraseña?
                    </a>
                    
                    <button type="submit" class="boton-login">
                        Iniciar sesión
                        <i data-lucide="arrow-right"></i>
                    </button>
                </form>
                
                <div class="texto-pie-formulario">
                    INSTITUTO TECNOLOGICO DE CHETUMAL
                </div>
            </div>
        </div>
    </main>
    
    <footer class="pie-pagina">
        &copy; <?= date('Y') ?> SIAE-IMSS. Todos los derechos reservados.
    </footer>
    
    <script>
        // Inicializa los iconos de Lucide
        lucide.createIcons();
        
        // Funcion para mostrar u ocultar la contrasena
        function alternarContrasena() {
            const campoContrasena = document.getElementById('campoContrasena');
            const iconoOjo = document.getElementById('iconoOjo');
            
            if (campoContrasena.type === 'password') {
                campoContrasena.type = 'text';
                iconoOjo.setAttribute('data-lucide', 'eye-off');
            } else {
                campoContrasena.type = 'password';
                iconoOjo.setAttribute('data-lucide', 'eye');
            }
            lucide.createIcons();
        }
    </script>
</body>
</html>