<?php
/**
 * SIAE-IMSS - Solicitar Recuperacion de Contrasena
 * Pagina donde el usuario ingresa su correo para recibir un enlace
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

if (estaLogueado()) {
    header('Location: ' . obtenerPaginaInicio());
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - SIAE-IMSS</title>
    
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
        
        .boton-accion:hover:not(:disabled) {
            background: var(--primario-hover);
        }
        
        .boton-accion:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .enlace-volver {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            text-align: center;
            font-size: 14px;
            color: var(--texto-secundario);
            text-decoration: none;
            margin-top: 24px;
            transition: color 0.2s;
        }
        
        .enlace-volver:hover {
            color: var(--texto-primario);
        }
        
        .alerta {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 20px;
            display: none;
            align-items: flex-start;
            gap: 10px;
            line-height: 1.4;
        }
        
        .alerta.error {
            background: #FEE2E2;
            color: #991B1B;
            display: flex;
        }
        
        .alerta.exito {
            background: #D1FAE5;
            color: #065F46;
            display: flex;
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
            <h2>Recuperar Contraseña</h2>
            <p class="subtitulo">Ingresa el correo electrónico asociado a tu cuenta y te enviaremos un enlace para restablecerla.</p>
            
            <div id="alertaMensaje" class="alerta">
                <i data-lucide="info" style="min-width: 18px;"></i>
                <span id="textoAlerta"></span>
            </div>
            
            <form id="formRecuperar" onsubmit="enviarSolicitud(event)">
                <div class="grupo-campo">
                    <label class="etiqueta-campo">
                        <i data-lucide="mail"></i>
                        Correo Electrónico
                    </label>
                    <input type="email" id="email" class="campo-formulario" 
                           placeholder="ejemplo@correo.com" required>
                </div>
                
                <button type="submit" id="btnEnviar" class="boton-accion">
                    <i data-lucide="send"></i>
                    <span>Enviar enlace</span>
                </button>
                
                <a href="<?= URL_BASE ?>views/auth/login.php" class="enlace-volver">
                    <i data-lucide="arrow-left" style="width: 16px; height: 16px;"></i>
                    Volver al inicio de sesión
                </a>
            </form>
        </div>
    </main>
    
    <footer class="pie-pagina">
        &copy; <?= date('Y') ?> SIAE-IMSS. Todos los derechos reservados.
    </footer>
    
    <script>
        lucide.createIcons();
        
        async function enviarSolicitud(e) {
            e.preventDefault();
            
            const email = document.getElementById('email').value;
            const btn = document.getElementById('btnEnviar');
            const alerta = document.getElementById('alertaMensaje');
            const textoAlerta = document.getElementById('textoAlerta');
            
            // UI en estado de carga
            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader-2" class="spin"></i><span>Enviando...</span>';
            lucide.createIcons();
            alerta.className = 'alerta';
            
            try {
                const response = await fetch('<?= URL_BASE ?>api/recuperar_password.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'solicitar_token',
                        email: email
                    })
                });
                
                const data = await response.json();
                
                alerta.className = data.success ? 'alerta exito' : 'alerta error';
                textoAlerta.textContent = data.message || 'Error al procesar la solicitud';
                
                if (data.success) {
                    document.getElementById('formRecuperar').reset();
                }
                
            } catch (error) {
                alerta.className = 'alerta error';
                textoAlerta.textContent = 'Error de conexión. Intenta nuevamente.';
            } finally {
                // UI vuelve a la normalidad
                btn.disabled = false;
                btn.innerHTML = '<i data-lucide="send"></i><span>Enviar enlace</span>';
                lucide.createIcons();
                
                // Actualiza el icono de la alerta segun su clase
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
