<?php
/**
 * SIAE-IMSS - Restablecer Contrasena
 * Pagina donde el usuario ingresa su nueva contrasena usando el token enviado
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

if (estaLogueado()) {
    header('Location: ' . obtenerPaginaInicio());
    exit;
}

$token = $_GET['token'] ?? '';
$tokenValido = false;
$mensaje = '';

if (!empty($token)) {
    try {
        $conexion = obtenerConexion();
        $consulta = $conexion->prepare("
            SELECT id FROM password_resets 
            WHERE token = ? AND usado = 0 AND fecha_expiracion > NOW()
        ");
        $consulta->execute([$token]);
        if ($consulta->fetch()) {
            $tokenValido = true;
        } else {
            $mensaje = 'El enlace ha expirado o ya fue utilizado. Por favor solicita uno nuevo.';
        }
    } catch (Exception $e) {
        $mensaje = 'Error al validar el enlace.';
    }
} else {
    $mensaje = 'Enlace invalido o incompleto.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Contraseña - SIAE-IMSS</title>
    
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
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
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
            text-decoration: none;
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
        
        .principal {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }
        
        .contenedor-formulario {
            width: 100%;
            max-width: 420px;
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
            text-align: center;
        }
        
        .contenedor-formulario .subtitulo {
            color: var(--texto-secundario);
            font-size: 14px;
            margin-bottom: 28px;
            text-align: center;
            line-height: 1.5;
        }
        
        .grupo-campo { margin-bottom: 20px; }
        
        .etiqueta-campo {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 500;
            color: var(--texto-primario);
            margin-bottom: 8px;
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
        
        .campo-contrasena { position: relative; }
        
        .campo-contrasena input { padding-right: 44px; }
        
        .boton-mostrar {
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
        
        .boton-mostrar:hover { color: var(--texto-secundario); }
        
        .boton-accion {
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
        
        .boton-accion:hover:not(:disabled) { background: var(--primario-hover); }
        .boton-accion:disabled { opacity: 0.7; cursor: not-allowed; }
        
        .alerta {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            line-height: 1.4;
        }
        
        .alerta.error { background: #FEE2E2; color: #991B1B; }
        .alerta.exito { background: #D1FAE5; color: #065F46; }
        
        .estado-vacio {
            text-align: center;
            padding: 20px 0;
        }
        
        .estado-vacio i {
            width: 48px;
            height: 48px;
            color: #EF4444;
            margin-bottom: 16px;
        }
        
        .pie-pagina {
            text-align: center;
            padding: 20px;
            font-size: 13px;
            color: var(--texto-atenuado);
        }
        
        .spin { animation: spin 1s linear infinite; }
        @keyframes spin { 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <header class="encabezado">
        <a href="<?= URL_BASE ?>index.php" class="logo">
            <div class="logo-icono">
                <i data-lucide="shield-check" style="width: 20px; height: 20px;"></i>
            </div>
            SIAE-IMSS
        </a>
    </header>
    
    <main class="principal">
        <div class="contenedor-formulario">
            
            <?php if (!$tokenValido): ?>
                <div class="estado-vacio">
                    <i data-lucide="x-circle"></i>
                    <h2>Enlace Inválido</h2>
                    <p class="subtitulo" style="margin-top: 10px;"><?= htmlspecialchars($mensaje) ?></p>
                    <a href="<?= URL_BASE ?>views/auth/recuperar.php" class="boton-accion" style="margin-top: 20px; text-decoration: none;">
                        Solicitar nuevo enlace
                    </a>
                </div>
            <?php else: ?>
            
                <h2>Nueva Contraseña</h2>
                <p class="subtitulo">Ingresa tu nueva contraseña para acceder al sistema.</p>
                
                <div id="alertaMensaje" class="alerta" style="display: none;">
                    <i data-lucide="info" style="min-width: 18px;"></i>
                    <span id="textoAlerta"></span>
                </div>
                
                <form id="formReset" onsubmit="actualizarPassword(event)">
                    <input type="hidden" id="token" value="<?= htmlspecialchars($token) ?>">
                    
                    <div class="grupo-campo">
                        <label class="etiqueta-campo">
                            <i data-lucide="lock"></i>
                            Nueva Contraseña
                        </label>
                        <div class="campo-contrasena">
                            <input type="password" id="password" class="campo-formulario" 
                                   placeholder="Minimo 8 caracteres" required minlength="8">
                            <button type="button" class="boton-mostrar" onclick="alternar('password', 'ojo1')">
                                <i data-lucide="eye" id="ojo1"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="grupo-campo">
                        <label class="etiqueta-campo">
                            <i data-lucide="check-circle"></i>
                            Confirmar Contraseña
                        </label>
                        <div class="campo-contrasena">
                            <input type="password" id="confirmar" class="campo-formulario" 
                                   placeholder="Escribela de nuevo" required minlength="8">
                            <button type="button" class="boton-mostrar" onclick="alternar('confirmar', 'ojo2')">
                                <i data-lucide="eye" id="ojo2"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" id="btnGuardar" class="boton-accion">
                        <i data-lucide="save"></i>
                        <span>Guardar y Entrar</span>
                    </button>
                </form>
                
                <div id="btnIrLogin" style="display: none; margin-top: 20px;">
                    <a href="<?= URL_BASE ?>views/auth/login.php" class="boton-accion" style="text-decoration: none;">
                        Ir a Iniciar Sesión
                    </a>
                </div>
                
            <?php endif; ?>
            
        </div>
    </main>
    
    <footer class="pie-pagina">
        &copy; <?= date('Y') ?> SIAE-IMSS. Todos los derechos reservados.
    </footer>
    
    <script>
        lucide.createIcons();
        
        function alternar(idCampo, idIcono) {
            const campo = document.getElementById(idCampo);
            const icono = document.getElementById(idIcono);
            
            if (campo.type === 'password') {
                campo.type = 'text';
                icono.setAttribute('data-lucide', 'eye-off');
            } else {
                campo.type = 'password';
                icono.setAttribute('data-lucide', 'eye');
            }
            lucide.createIcons();
        }
        
        async function actualizarPassword(e) {
            e.preventDefault();
            
            const token = document.getElementById('token').value;
            const password = document.getElementById('password').value;
            const confirmar = document.getElementById('confirmar').value;
            const btn = document.getElementById('btnGuardar');
            const alerta = document.getElementById('alertaMensaje');
            const textoAlerta = document.getElementById('textoAlerta');
            
            if (password !== confirmar) {
                alerta.style.display = 'flex';
                alerta.className = 'alerta error';
                textoAlerta.textContent = 'Las contraseñas no coinciden';
                lucide.createIcons();
                return;
            }
            
            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader-2" class="spin"></i><span>Guardando...</span>';
            lucide.createIcons();
            
            try {
                const response = await fetch('<?= URL_BASE ?>api/recuperar_password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'actualizar_password',
                        token: token,
                        password: password,
                        confirmar_password: confirmar
                    })
                });
                
                const data = await response.json();
                
                alerta.style.display = 'flex';
                alerta.className = data.success ? 'alerta exito' : 'alerta error';
                textoAlerta.textContent = data.message;
                
                if (data.success) {
                    document.getElementById('formReset').style.display = 'none';
                    document.getElementById('btnIrLogin').style.display = 'block';
                }
                
            } catch (error) {
                alerta.style.display = 'flex';
                alerta.className = 'alerta error';
                textoAlerta.textContent = 'Error de conexión. Intenta nuevamente.';
            } finally {
                if (document.getElementById('formReset').style.display !== 'none') {
                    btn.disabled = false;
                    btn.innerHTML = '<i data-lucide="save"></i><span>Guardar y Entrar</span>';
                }
                
                const iconAlerta = alerta.querySelector('i');
                if (alerta.classList.contains('exito')) {
                    iconAlerta.setAttribute('data-lucide', 'check-circle');
                } else {
                    iconAlerta.setAttribute('data-lucide', 'alert-circle');
                }
                lucide.createIcons();
            }
        }
    </script>
</body>
</html>
