<?php
/**
 * SIAE-IMSS - Sidebar dinámico para roles personalizados (ID > 5)
 * Muestra únicamente los accesos que corresponden a los permisos del rol
 */

$paginaActual        = basename($_SERVER['PHP_SELF'], '.php');
$rutaActual          = $_SERVER['PHP_SELF'];
$currentUser         = obtenerUsuarioActual();
$userInitials        = obtenerIniciales($currentUser['nombre_completo'] ?? 'U');
$avatarColor         = obtenerColorAvatar($currentUser['nombre_completo'] ?? 'Usuario');
$permisos            = $currentUser['permisos'] ?? [];

// Flags de página activa usando ruta completa para evitar colisiones entre dashboard.php
$esCustomDashboard   = strpos($rutaActual, '/custom/') !== false && $paginaActual === 'dashboard';
$esEstudianteDash    = strpos($rutaActual, '/estudiante/') !== false;

// Helper local para verificar un permiso
function tienePermiso(string $p): bool {
    global $permisos;
    return in_array($p, $permisos);
}

// Si los únicos permisos son ver_datos/reportar_falla, "Inicio" apunta directo a Mi información
// y el ítem separado "Mi información" no aparece (evita duplicado)
$permisosExtra    = array_diff($permisos, ['ver_datos', 'reportar_falla']);
$soloInformacion  = empty($permisosExtra) && !empty(array_intersect(['ver_datos', 'reportar_falla'], $permisos));
$urlInicio        = $soloInformacion
    ? URL_BASE . 'views/estudiante/dashboard.php'
    : URL_BASE . 'views/custom/dashboard.php';
?>

<aside class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <div class="sidebar-logo-icon" style="background: #6366F1;">
                <i data-lucide="shield" style="width:16px;height:16px;color:white;"></i>
            </div>
            <div>
                <div class="sidebar-logo-text">SIAE-IMSS</div>
                <div class="sidebar-logo-sub"><?= htmlspecialchars($currentUser['rol_nombre'] ?? 'Rol Personalizado') ?></div>
            </div>
        </div>
    </div>

    <nav class="sidebar-nav">

        <!-- Inicio: si solo tiene ver_datos apunta directo a Mi información -->
        <a href="<?= $urlInicio ?>"
           class="nav-item <?= ($soloInformacion ? $esEstudianteDash : $esCustomDashboard) ? 'active' : '' ?>">
            <i data-lucide="<?= $soloInformacion ? 'user-circle' : 'layout-dashboard' ?>"></i>
            <span><?= $soloInformacion ? 'Mi información' : 'Inicio' ?></span>
        </a>

        <?php if (!$soloInformacion && (tienePermiso('ver_datos') || tienePermiso('reportar_falla'))): ?>
        <a href="<?= URL_BASE ?>views/estudiante/dashboard.php"
           class="nav-item <?= $esEstudianteDash ? 'active' : '' ?>">
            <i data-lucide="user-circle"></i>
            <span>Mi información</span>
        </a>
        <?php endif; ?>

        <?php if (tienePermiso('altas_bajas') || tienePermiso('editar_datos') || tienePermiso('exportar_txt')): ?>
        <a href="<?= URL_BASE ?>views/admin-se/carpetas.php"
           class="nav-item <?= $paginaActual === 'carpetas' ? 'active' : '' ?>">
            <i data-lucide="folders"></i>
            <span>Carpetas</span>
        </a>
        <?php endif; ?>

        <?php if (tienePermiso('importar_excel')): ?>
        <a href="<?= URL_BASE ?>views/admin-se/importar.php"
           class="nav-item <?= $paginaActual === 'importar' ? 'active' : '' ?>">
            <i data-lucide="file-spreadsheet"></i>
            <span>Importar Excel</span>
        </a>
        <?php endif; ?>

        <?php if (tienePermiso('ver_alumnos') || tienePermiso('altas_bajas')): ?>
        <a href="<?= URL_BASE ?>views/jefa/estatus-imss.php"
           class="nav-item <?= $paginaActual === 'estatus-imss' ? 'active' : '' ?>">
            <i data-lucide="shield-check"></i>
            <span>Estatus IMSS</span>
        </a>
        <?php endif; ?>

        <?php if (tienePermiso('altas_bajas')): ?>
        <a href="<?= URL_BASE ?>views/jefa/acuses.php"
           class="nav-item <?= $paginaActual === 'acuses' ? 'active' : '' ?>">
            <i data-lucide="file-badge"></i>
            <span>Acuses IMSS</span>
        </a>
        <?php endif; ?>

        <?php if (tienePermiso('validar_movimientos')): ?>
        <a href="<?= URL_BASE ?>views/jefa/validar.php"
           class="nav-item <?= $paginaActual === 'validar' ? 'active' : '' ?>">
            <i data-lucide="check-circle"></i>
            <span>Validar</span>
            <?php
            try {
                $conexionSb = obtenerConexion();
                $stmtSb = $conexionSb->query(
                    "SELECT COUNT(*) as t FROM tablas_movimientos
                     WHERE estado = 'borrador' AND id_usuario_creacion != " . obtenerIdUsuarioActual()
                );
                $pendSb = $stmtSb->fetch()['t'] ?? 0;
                if ($pendSb > 0): ?>
            <span class="nav-badge"><?= $pendSb ?></span>
            <?php endif;
            } catch (Exception $e) {} ?>
        </a>
        <?php endif; ?>

        <?php if (tienePermiso('ver_reportes')): ?>
        <a href="<?= URL_BASE ?>views/jefa/reportes.php"
           class="nav-item <?= $paginaActual === 'reportes' ? 'active' : '' ?>">
            <i data-lucide="bar-chart-3"></i>
            <span>Reportes</span>
        </a>
        <?php endif; ?>

        <?php if (tienePermiso('exportar_txt')): ?>
        <a href="<?= URL_BASE ?>views/admin-imss/exportar.php"
           class="nav-item <?= $paginaActual === 'exportar' ? 'active' : '' ?>">
            <i data-lucide="file-output"></i>
            <span>Exportar TXT</span>
        </a>
        <?php endif; ?>

        <?php if (tienePermiso('altas_bajas') || tienePermiso('editar_datos')): ?>
        <a href="<?= URL_BASE ?>views/admin-se/historial.php"
           class="nav-item <?= $paginaActual === 'historial' ? 'active' : '' ?>">
            <i data-lucide="history"></i>
            <span>Mi Historial</span>
            <?php
            try {
                $conexionSb2 = obtenerConexion();
                $stmtSb2 = $conexionSb2->prepare(
                    "SELECT COUNT(*) as t FROM notificaciones
                     WHERE id_usuario_destino = ? AND tipo = 'alerta_problema' AND leida = 0"
                );
                $stmtSb2->execute([obtenerIdUsuarioActual()]);
                $alertSb = $stmtSb2->fetch()['t'] ?? 0;
                if ($alertSb > 0): ?>
            <span class="nav-badge" style="background:#EF4444;"><?= $alertSb ?></span>
            <?php endif;
            } catch (Exception $e) {} ?>
        </a>
        <?php endif; ?>

        <?php if (tienePermiso('atender_incidencias')): ?>
        <a href="<?= URL_BASE ?>views/admin-se/notificaciones.php"
           class="nav-item <?= $paginaActual === 'notificaciones' ? 'active' : '' ?>">
            <i data-lucide="bell-ring"></i>
            <span>Notificaciones</span>
        </a>
        <?php endif; ?>

        <?php if (tienePermiso('ver_bitacora')): ?>
        <a href="<?= URL_BASE ?>views/superadmin/bitacora.php"
           class="nav-item <?= $paginaActual === 'bitacora' ? 'active' : '' ?>">
            <i data-lucide="scroll-text"></i>
            <span>Bitácora</span>
        </a>
        <?php endif; ?>

        <?php if (tienePermiso('crud_usuarios')): ?>
        <a href="<?= URL_BASE ?>views/superadmin/usuarios.php"
           class="nav-item <?= $paginaActual === 'usuarios' ? 'active' : '' ?>">
            <i data-lucide="users"></i>
            <span>Usuarios</span>
        </a>
        <?php endif; ?>

        <?php if (tienePermiso('gestion_catalogos')): ?>
        <a href="<?= URL_BASE ?>views/superadmin/catalogos.php"
           class="nav-item <?= $paginaActual === 'catalogos' ? 'active' : '' ?>">
            <i data-lucide="database"></i>
            <span>Catálogos</span>
        </a>
        <?php endif; ?>

        <?php if (tienePermiso('config_patronal')): ?>
        <a href="<?= URL_BASE ?>views/superadmin/configuracion.php"
           class="nav-item <?= $paginaActual === 'configuracion' ? 'active' : '' ?>">
            <i data-lucide="settings"></i>
            <span>Configuración</span>
        </a>
        <?php endif; ?>

    </nav>

    <div class="sidebar-footer">
        <a href="<?= URL_BASE ?>views/perfil/cambiar-password.php"
           class="nav-item <?= $paginaActual === 'cambiar-password' ? 'active' : '' ?>">
            <i data-lucide="key-round"></i>
            <span>Cambiar contraseña</span>
        </a>
        <a href="<?= URL_BASE ?>api/auth.php?action=logout" class="nav-item">
            <i data-lucide="log-out"></i>
            <span>Cerrar sesión</span>
        </a>
    </div>
</aside>

<main class="main-content">
    <header class="main-header">
        <div class="header-search"></div>
        <div class="header-actions">

            <!-- Campana de notificaciones -->
            <div class="notif-container" style="position:relative;">
                <button class="header-icon-btn" title="Notificaciones"
                        onclick="toggleNotificaciones()" id="btnNotificaciones">
                    <i data-lucide="bell"></i>
                    <span class="notif-badge" id="notifBadge" style="display:none;">0</span>
                </button>
                <div class="notif-dropdown" id="notifDropdown" style="display:none;">
                    <div class="notif-dropdown-header">
                        <span>Notificaciones</span>
                        <div class="notif-header-actions">
                            <button onclick="marcarTodasLeidas()" class="notif-header-btn">Leídas</button>
                            <button onclick="limpiarLeidas()" class="notif-header-btn">Limpiar</button>
                        </div>
                    </div>
                    <div class="notif-dropdown-body" id="notifLista">
                        <div class="notif-loading">Cargando...</div>
                    </div>
                    <div class="notif-dropdown-footer">
                        <a href="<?= URL_BASE ?>views/admin-se/notificaciones.php">Ver todas</a>
                    </div>
                </div>
            </div>

            <button class="header-icon-btn" title="Ayuda">
                <i data-lucide="help-circle"></i>
            </button>

            <div class="header-user">
                <div class="header-user-info">
                    <div class="header-user-name"><?= htmlspecialchars($currentUser['nombre_completo'] ?? 'Usuario') ?></div>
                    <div class="header-user-role"><?= htmlspecialchars($currentUser['rol_nombre'] ?? 'Rol Personalizado') ?></div>
                </div>
                <div class="header-user-avatar" style="background:<?= $avatarColor ?>">
                    <?= $userInitials ?>
                </div>
            </div>
        </div>
    </header>

    <div class="page-content">
<link rel="stylesheet" href="<?= URL_RECURSOS ?>css/notifications.css">
<script>
const API_NOTIF = '<?= URL_BASE ?>api/notificaciones.php';
let notifDropdownAbierto = false;

document.addEventListener('DOMContentLoaded', () => {
    cargarContadorNotificaciones();
    setInterval(cargarContadorNotificaciones, 60000);
});

document.addEventListener('click', (e) => {
    if (!e.target.closest('.notif-container') && notifDropdownAbierto) cerrarNotificaciones();
});

async function cargarContadorNotificaciones() {
    try {
        const resp = await fetch(API_NOTIF + '?action=contar');
        const data = await resp.json();
        if (data.success) {
            const badge = document.getElementById('notifBadge');
            const total = data.data.total;
            badge.textContent = total > 99 ? '99+' : total;
            badge.style.display = total > 0 ? 'flex' : 'none';
        }
    } catch (e) {}
}

function toggleNotificaciones() {
    if (notifDropdownAbierto) { cerrarNotificaciones(); return; }
    document.getElementById('notifDropdown').style.display = 'block';
    notifDropdownAbierto = true;
    cargarNotificaciones();
}

function cerrarNotificaciones() {
    document.getElementById('notifDropdown').style.display = 'none';
    notifDropdownAbierto = false;
}

async function cargarNotificaciones() {
    const lista = document.getElementById('notifLista');
    lista.innerHTML = '<div class="notif-loading">Cargando...</div>';
    try {
        const resp = await fetch(API_NOTIF + '?action=listar&limite=10');
        const data = await resp.json();
        if (data.success && data.data.length > 0) {
            lista.innerHTML = data.data.map(n => renderNotificacion(n)).join('');
            if (typeof lucide !== 'undefined') lucide.createIcons();
        } else {
            lista.innerHTML = `<div class="notif-empty">
                <i data-lucide="bell-off" style="width:32px;height:32px;display:block;margin:0 auto 8px;"></i>
                Sin notificaciones</div>`;
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    } catch (e) {
        lista.innerHTML = '<div class="notif-empty">No se pudieron cargar</div>';
    }
}

function renderNotificacion(n) {
    const tipoClase = n.tipo === 'exportacion_txt' ? 'tipo-exportacion'
                    : n.tipo === 'alerta_problema' ? 'tipo-alerta' : 'tipo-info';
    const icono    = n.tipo === 'exportacion_txt' ? 'file-output'
                    : n.tipo === 'alerta_problema' ? 'alert-triangle' : 'info';
    const noLeida  = !n.leida ? 'no-leida' : '';
    const tiempo   = tiempoRelativo(n.fecha_creacion);

    let acciones = '';
    if (n.referencia_tipo === 'reporte_alumno') {
        acciones = `<div class="notif-actions-inline">
            <button class="notif-action-btn ok"
                    onclick="event.stopPropagation(); window.location.href='<?= URL_BASE ?>views/jefa/corregir-alumno.php?id=${n.id_notificacion}'">
                Corregir datos
            </button>
        </div>`;
    }

    return `
        <div class="notif-item ${noLeida}" onclick="verNotificacion(${n.id_notificacion})">
            <div class="notif-indicator ${tipoClase}">
                <i data-lucide="${icono}" style="width:16px;height:16px;"></i>
            </div>
            <div class="notif-content">
                <div class="notif-titulo">${escapeHtml(n.titulo)}</div>
                <div class="notif-meta">
                    ${n.nombre_origen ? '<span>' + escapeHtml(n.nombre_origen) + '</span><span class="notif-meta-dot"></span>' : ''}
                    <span>${tiempo}</span>
                </div>
                ${acciones}
            </div>
            <button class="notif-delete-btn" onclick="event.stopPropagation();eliminarNotif(${n.id_notificacion})" title="Eliminar">
                <i data-lucide="x" style="width:14px;height:14px;"></i>
            </button>
        </div>`;
}

async function verNotificacion(id) {
    await fetch(API_NOTIF + '?action=marcar_leida&id=' + id);
    cargarContadorNotificaciones(); cargarNotificaciones();
}
async function marcarTodasLeidas() {
    await fetch(API_NOTIF + '?action=marcar_todas_leidas');
    cargarContadorNotificaciones(); cargarNotificaciones();
}
async function eliminarNotif(id) {
    await fetch(API_NOTIF + '?action=eliminar&id=' + id);
    cargarContadorNotificaciones(); cargarNotificaciones();
}
async function limpiarLeidas() {
    const r = await Swal.fire({
        title: 'Limpiar leídas', text: 'Se eliminarán las notificaciones ya leídas.',
        icon: 'question', showCancelButton: true,
        confirmButtonColor: '#6366F1', cancelButtonColor: '#64748B',
        confirmButtonText: 'Sí, limpiar', cancelButtonText: 'Cancelar'
    });
    if (!r.isConfirmed) return;
    await fetch(API_NOTIF + '?action=eliminar_leidas');
    cargarContadorNotificaciones(); cargarNotificaciones();
}

function tiempoRelativo(fecha) {
    const diff = Math.floor((new Date() - new Date(fecha)) / 1000);
    if (diff < 60)    return 'Ahora';
    if (diff < 3600)  return Math.floor(diff / 60) + ' min';
    if (diff < 86400) return Math.floor(diff / 3600) + ' h';
    if (diff < 604800) return Math.floor(diff / 86400) + ' d';
    return new Date(fecha).toLocaleDateString('es-MX', { day: '2-digit', month: 'short' });
}

function escapeHtml(text) {
    if (!text) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}
</script>
