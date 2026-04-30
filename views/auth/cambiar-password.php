<?php
/**
 * SIAE-IMSS - Cambio de contrasena obligatorio
 * Se muestra al usuario la primera vez que inicia sesion
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Requiere sesion activa
requerirLogin();

// Si ya cambio su contrasena, redirige a su dashboard
if (empty($_SESSION['user']['debe_cambiar_password'])) {
    header('Location: ' . obtenerPaginaInicio());
    exit;
}

$currentUser = obtenerUsuarioActual();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar Contrasena - SIAE-IMSS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #F8FAFC; min-height: 100vh; display: flex; flex-direction: column; }

        .encabezado {
            background: #fff;
            padding: 16px 40px;
            border-bottom: 1px solid #E2E8F0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .logo-icono {
            width: 36px; height: 36px;
            background: #2563EB;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            color: white;
        }
        .logo-texto { font-weight: 600; font-size: 18px; color: #1E293B; }

        .principal {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .card {
            background: white;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            width: 100%;
            max-width: 420px;
        }

        .alerta-obligatorio {
            background: #FEF3C7;
            border-left: 4px solid #F59E0B;
            padding: 12px 16px;
            border-radius: 0 8px 8px 0;
            font-size: 13px;
            color: #92400E;
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        h2 { font-size: 22px; font-weight: 600; color: #1E293B; margin-bottom: 6px; }
        .subtitulo { font-size: 14px; color: #64748B; margin-bottom: 24px; }

        .grupo { margin-bottom: 18px; }
        label { display: block; font-size: 13px; font-weight: 500; color: #1E293B; margin-bottom: 6px; }

        .campo-wrap { position: relative; }
        input[type="password"], input[type="text"] {
            width: 100%;
            padding: 11px 40px 11px 14px;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        input:focus { outline: none; border-color: #2563EB; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }

        .btn-ojo {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: #94A3B8; cursor: pointer; padding: 4px;
        }

        .requisitos {
            font-size: 12px;
            color: #64748B;
            margin-top: 6px;
        }

        .btn-submit {
            width: 100%;
            padding: 13px;
            background: #2563EB;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            margin-top: 8px;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-submit:hover { background: #1D4ED8; }
        .btn-submit:disabled { background: #94A3B8; cursor: not-allowed; }

        .mensaje-error {
            background: #FEE2E2;
            color: #991B1B;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 16px;
            display: none;
            align-items: center;
            gap: 10px;
        }
        .mensaje-exito {
            background: #DCFCE7;
            color: #166534;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 16px;
            display: none;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>

<header class="encabezado">
    <div class="logo-icono">
        <i data-lucide="shield-check" style="width:20px;height:20px;"></i>
    </div>
    <span class="logo-texto">SIAE-IMSS</span>
</header>

<main class="principal">
    <div class="card">

        <div class="alerta-obligatorio">
            <i data-lucide="alert-triangle" style="width:16px;height:16px;flex-shrink:0;margin-top:1px;"></i>
            <span>Por seguridad debes crear una nueva contrasena antes de continuar. Esta es la unica vez que se te pedira esto al ingresar.</span>
        </div>

        <h2>Crea tu contrasena</h2>
        <p class="subtitulo">Hola, <strong><?= htmlspecialchars($currentUser['nombre_completo']) ?></strong>. Elige una contrasena segura.</p>

        <div class="mensaje-error" id="msgError">
            <i data-lucide="alert-circle" style="width:16px;height:16px;"></i>
            <span id="msgErrorTexto"></span>
        </div>
        <div class="mensaje-exito" id="msgExito">
            <i data-lucide="check-circle" style="width:16px;height:16px;"></i>
            <span>Contrasena actualizada. Redirigiendo...</span>
        </div>

        <form id="formCambiar">
            <div class="grupo">
                <label>Nueva contrasena</label>
                <div class="campo-wrap">
                    <input type="password" id="nuevaPassword" placeholder="Minimo 8 caracteres" required>
                    <button type="button" class="btn-ojo" onclick="toggleOjo('nuevaPassword', 'ojo1')">
                        <i data-lucide="eye" id="ojo1" style="width:16px;height:16px;"></i>
                    </button>
                </div>
                <p class="requisitos">Minimo 8 caracteres. Usa letras, numeros y simbolos.</p>
            </div>

            <div class="grupo">
                <label>Confirmar contrasena</label>
                <div class="campo-wrap">
                    <input type="password" id="confirmarPassword" placeholder="Repite la contrasena" required>
                    <button type="button" class="btn-ojo" onclick="toggleOjo('confirmarPassword', 'ojo2')">
                        <i data-lucide="eye" id="ojo2" style="width:16px;height:16px;"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-submit" id="btnGuardar">
                <i data-lucide="lock" style="width:16px;height:16px;"></i>
                Guardar contrasena
            </button>
        </form>

    </div>
</main>

<script>
const API = '<?= URL_BASE ?>api/usuarios.php';

lucide.createIcons();

function toggleOjo(campoId, iconoId) {
    const campo = document.getElementById(campoId);
    const icono = document.getElementById(iconoId);
    if (campo.type === 'password') {
        campo.type = 'text';
        icono.setAttribute('data-lucide', 'eye-off');
    } else {
        campo.type = 'password';
        icono.setAttribute('data-lucide', 'eye');
    }
    lucide.createIcons();
}

document.getElementById('formCambiar').addEventListener('submit', async function(e) {
    e.preventDefault();

    const nueva = document.getElementById('nuevaPassword').value;
    const confirmar = document.getElementById('confirmarPassword').value;
    const btn = document.getElementById('btnGuardar');
    const msgError = document.getElementById('msgError');
    const msgExito = document.getElementById('msgExito');

    msgError.style.display = 'none';
    msgExito.style.display = 'none';

    if (nueva.length < 8) {
        document.getElementById('msgErrorTexto').textContent = 'La contrasena debe tener al menos 8 caracteres';
        msgError.style.display = 'flex';
        return;
    }

    if (nueva !== confirmar) {
        document.getElementById('msgErrorTexto').textContent = 'Las contrasenas no coinciden';
        msgError.style.display = 'flex';
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader" style="width:16px;height:16px;"></i> Guardando...';
    lucide.createIcons();

    try {
        const resp = await fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({
                action: 'cambiar_password',
                nueva_password: nueva,
                confirmar_password: confirmar,
                forzado: true
            })
        });

        const data = await resp.json();

        if (data.success) {
            msgExito.style.display = 'flex';
            // Redirige al dashboard despues de 1.5 segundos
            setTimeout(() => {
                window.location.href = '<?= URL_BASE ?>index.php';
            }, 1500);
        } else {
            document.getElementById('msgErrorTexto').textContent = data.message || 'Error al actualizar';
            msgError.style.display = 'flex';
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="lock" style="width:16px;height:16px;"></i> Guardar contrasena';
            lucide.createIcons();
        }
    } catch (e) {
        document.getElementById('msgErrorTexto').textContent = 'Error de conexion';
        msgError.style.display = 'flex';
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="lock" style="width:16px;height:16px;"></i> Guardar contrasena';
        lucide.createIcons();
    }
});
</script>

</body>
</html>
