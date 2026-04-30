<?php
/**
 * SIAE-IMSS - Cambio voluntario de contrasena
 * Accesible desde el sidebar de cualquier rol
 */

$tituloPagina = 'Cambiar Contrasena';
$currentPage  = 'cambiar-password';

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

requerirLogin();

$currentUser = obtenerUsuarioActual();
$rolId = $currentUser['id_rol'];

// Determina que sidebar mostrar segun el rol
$sidebars = [
    ROL_SUPERADMIN      => 'sidebar-superadmin',
    ROL_JEFA_SERVICIOS  => 'sidebar-jefa',
    ROL_ADMIN_SERVICIOS => 'sidebar-admin-se',
    ROL_ADMIN_IMSS      => 'sidebar-admin-imss',
];
$sidebarArchivo = $sidebars[$rolId] ?? null;

include __DIR__ . '/../layouts/header.php';
if ($sidebarArchivo) {
    include __DIR__ . '/../layouts/' . $sidebarArchivo . '.php';
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Cambiar Contrasena</h1>
        <p class="page-subtitle">Actualiza tu contrasena de acceso al sistema</p>
    </div>
</div>

<div class="card" style="max-width: 480px;">
    <div class="card-body" style="padding: 32px;">

        <div id="msgError" style="display:none; background:#FEE2E2; color:#991B1B; padding:12px 16px; border-radius:8px; font-size:14px; margin-bottom:16px; gap:10px; align-items:center;">
            <i data-lucide="alert-circle" style="width:16px;height:16px;flex-shrink:0;"></i>
            <span id="msgErrorTexto"></span>
        </div>
        <div id="msgExito" style="display:none; background:#DCFCE7; color:#166534; padding:12px 16px; border-radius:8px; font-size:14px; margin-bottom:16px; gap:10px; align-items:center;">
            <i data-lucide="check-circle" style="width:16px;height:16px;flex-shrink:0;"></i>
            <span>Contrasena actualizada correctamente</span>
        </div>

        <form id="formCambiar">

            <div style="margin-bottom:18px;">
                <label style="display:block; font-size:13px; font-weight:500; margin-bottom:6px;">Contrasena actual</label>
                <div style="position:relative;">
                    <input type="password" id="passwordActual" class="form-control" placeholder="Tu contrasena actual" style="padding-right:40px;">
                    <button type="button" onclick="toggleOjo('passwordActual','ojo0')" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:#94A3B8;cursor:pointer;">
                        <i data-lucide="eye" id="ojo0" style="width:16px;height:16px;"></i>
                    </button>
                </div>
            </div>

            <div style="margin-bottom:18px;">
                <label style="display:block; font-size:13px; font-weight:500; margin-bottom:6px;">Nueva contrasena</label>
                <div style="position:relative;">
                    <input type="password" id="nuevaPassword" class="form-control" placeholder="Minimo 8 caracteres" style="padding-right:40px;">
                    <button type="button" onclick="toggleOjo('nuevaPassword','ojo1')" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:#94A3B8;cursor:pointer;">
                        <i data-lucide="eye" id="ojo1" style="width:16px;height:16px;"></i>
                    </button>
                </div>
                <p style="font-size:12px;color:var(--text-muted);margin-top:5px;">Minimo 8 caracteres. Usa letras, numeros y simbolos.</p>
            </div>

            <div style="margin-bottom:24px;">
                <label style="display:block; font-size:13px; font-weight:500; margin-bottom:6px;">Confirmar nueva contrasena</label>
                <div style="position:relative;">
                    <input type="password" id="confirmarPassword" class="form-control" placeholder="Repite la nueva contrasena" style="padding-right:40px;">
                    <button type="button" onclick="toggleOjo('confirmarPassword','ojo2')" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:#94A3B8;cursor:pointer;">
                        <i data-lucide="eye" id="ojo2" style="width:16px;height:16px;"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" id="btnGuardar" style="width:100%;">
                <i data-lucide="lock" style="width:15px;height:15px;"></i>
                Actualizar contrasena
            </button>

        </form>
    </div>
</div>

<script>
const API = '<?= URL_BASE ?>api/usuarios.php';

function toggleOjo(campoId, iconoId) {
    const campo = document.getElementById(campoId);
    const icono = document.getElementById(iconoId);
    campo.type = campo.type === 'password' ? 'text' : 'password';
    icono.setAttribute('data-lucide', campo.type === 'password' ? 'eye' : 'eye-off');
    lucide.createIcons();
}

document.getElementById('formCambiar').addEventListener('submit', async function(e) {
    e.preventDefault();

    const actual    = document.getElementById('passwordActual').value;
    const nueva     = document.getElementById('nuevaPassword').value;
    const confirmar = document.getElementById('confirmarPassword').value;
    const btn       = document.getElementById('btnGuardar');
    const msgError  = document.getElementById('msgError');
    const msgExito  = document.getElementById('msgExito');

    msgError.style.display = 'none';
    msgExito.style.display = 'none';

    if (!actual || !nueva || !confirmar) {
        document.getElementById('msgErrorTexto').textContent = 'Completa todos los campos';
        msgError.style.display = 'flex';
        return;
    }

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

    try {
        const resp = await fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({
                action: 'cambiar_password',
                password_actual: actual,
                nueva_password: nueva,
                confirmar_password: confirmar,
                forzado: false
            })
        });

        const data = await resp.json();

        if (data.success) {
            msgExito.style.display = 'flex';
            document.getElementById('formCambiar').reset();
        } else {
            document.getElementById('msgErrorTexto').textContent = data.message || 'Error al actualizar';
            msgError.style.display = 'flex';
        }
    } catch (err) {
        document.getElementById('msgErrorTexto').textContent = 'Error de conexion';
        msgError.style.display = 'flex';
    }

    btn.disabled = false;
});
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
