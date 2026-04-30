<?php
/**
 * SIAE-IMSS - Sidebar para Admin Servicios Escolares (ROL 3)
 * Menu lateral con navegación del módulo
 */

// Obtiene la página actual para marcar el menú activo
$paginaActual = basename($_SERVER['PHP_SELF'], '.php');

// Obtiene los datos del usuario logueado
$currentUser = obtenerUsuarioActual();

// Si no hay nombre, usa "SA" (Superadmin) como valor por defecto
$userInitials = obtenerIniciales($currentUser['nombre_completo'] ?? 'SA');

// Genera un color de fondo para el avatar basado en el nombre
$avatarColor = obtenerColorAvatar($currentUser['nombre_completo'] ?? 'Superadmin');
?>

<aside class="sidebar">
    <!-- Logo -->
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <div class="sidebar-logo-icon">A</div>
            <div>
                <div class="sidebar-logo-text">SIAE-IMSS</div>
                <div class="sidebar-logo-sub">Admin Servicios</div>
            </div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <a href="<?= URL_BASE ?>views/admin-se/dashboard.php" class="nav-item <?= $paginaActual === 'dashboard' ? 'active' : '' ?>">
            <i data-lucide="layout-dashboard"></i>
            <span>Inicio</span>
        </a>
        
        <a href="<?= URL_BASE ?>views/admin-se/carpetas.php" class="nav-item <?= $paginaActual === 'carpetas' ? 'active' : '' ?>">
            <i data-lucide="folders"></i>
            <span>Carpetas</span>
        </a>

        <a href="<?= URL_BASE ?>views/admin-se/importar.php"  class="nav-item <?= $paginaActual === 'importar' ? 'active' : '' ?>">
        <i data-lucide="file-spreadsheet"></i>
        <span>Importar Excel</span>
        </a>

        <a href="<?= URL_BASE ?>views/admin-se/historial.php" class="nav-item <?= $paginaActual === 'historial' ? 'active' : '' ?>">
            <i data-lucide="history"></i>
            <span>Mi Historial</span>
            <?php
            // Contar notificaciones de problemas pendientes
            try {
                $conexion = obtenerConexion();
                $consulta = $conexion->prepare("
                    SELECT COUNT(*) as total 
                    FROM notificaciones 
                    WHERE id_usuario_destino = ? 
                    AND tipo = 'alerta_problema' 
                    AND leida = 0
                ");
                $consulta->execute([obtenerIdUsuarioActual()]);
                $alertasPendientes = $consulta->fetch()['total'] ?? 0;
                if ($alertasPendientes > 0):
            ?>
            <span class="nav-badge" style="background: #EF4444;"><?= $alertasPendientes ?></span>
            <?php 
                endif;
            } catch (Exception $e) {}
            ?>
        </a>
    </nav>
    
    <div class="sidebar-footer">
        <a href="<?= URL_BASE ?>views/perfil/cambiar-password.php" class="nav-item <?= $paginaActual === 'cambiar-password' ? 'active' : '' ?>">
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
    <!-- Header -->
    <header class="main-header">
        <div class="header-search"></div>
        
        <div class="header-actions">
            <!-- Campana de notificaciones -->
            <div class="notif-container" style="position: relative;">
                <button class="header-icon-btn" title="Notificaciones" onclick="toggleNotificaciones()" id="btnNotificaciones">
                    <i data-lucide="bell"></i>
                    <span class="notif-badge" id="notifBadge" style="display: none;">0</span>
                </button>
                
                <div class="notif-dropdown" id="notifDropdown" style="display: none;">
                    <div class="notif-dropdown-header">
                        <span>Notificaciones</span>
                        <div class="notif-header-actions">
                            <button onclick="marcarTodasLeidas()" class="notif-header-btn" title="Marcar todas como leídas">Leídas</button>
                            <button onclick="limpiarLeidas()" class="notif-header-btn" title="Eliminar leídas">Limpiar</button>
                        </div>
                    </div>
                    <div class="notif-dropdown-body" id="notifLista">
                        <div class="notif-loading">Cargando...</div>
                    </div>
                    <div class="notif-dropdown-footer">
                        <a href="<?= URL_BASE ?>views/admin-se/notificaciones.php">Ver todas las notificaciones</a>
                    </div>
                </div>
            </div>
            
            <button class="header-icon-btn" title="Ayuda">
                <i data-lucide="help-circle"></i>
            </button>
            
            <div class="header-user">
                <div class="header-user-info">
                    <div class="header-user-name"><?= htmlspecialchars($currentUser['nombre_completo'] ?? 'Usuario') ?></div>
                    <div class="header-user-role"><?= htmlspecialchars($currentUser['rol_nombre'] ?? 'Admin SE') ?></div>
                </div>
                <div class="header-user-avatar" style="background: <?= $avatarColor ?>">
                    <?= $userInitials ?>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Page Content -->
    <div class="page-content">
<link rel="stylesheet" href="<?= URL_RECURSOS ?>css/notifications.css">
<!-- JavaScript para notificaciones -->
<script>
const API_NOTIF = '<?= URL_BASE ?>api/notificaciones.php';
let notifDropdownAbierto = false;

document.addEventListener('DOMContentLoaded', () => {
    cargarContadorNotificaciones();
    setInterval(cargarContadorNotificaciones, 60000);
});

document.addEventListener('click', (e) => {
    if (!e.target.closest('.notif-container') && notifDropdownAbierto) {
        cerrarNotificaciones();
    }
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
    const dropdown = document.getElementById('notifDropdown');
    if (notifDropdownAbierto) {
        cerrarNotificaciones();
    } else {
        dropdown.style.display = 'block';
        notifDropdownAbierto = true;
        cargarNotificaciones();
    }
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
            if(typeof lucide !== 'undefined') lucide.createIcons();
        } else {
            lista.innerHTML = `
                <div class="notif-empty">
                    <i data-lucide="bell-off" style="width:32px;height:32px;display:block;margin:0 auto 8px;"></i>
                    Sin notificaciones
                </div>`;
            if(typeof lucide !== 'undefined') lucide.createIcons();
        }
    } catch (e) {
        lista.innerHTML = '<div class="notif-empty">No se pudieron cargar</div>';
    }
}

function renderNotificacion(n) {
    const tipoClase = n.tipo === 'exportacion_txt' ? 'tipo-exportacion' : (n.tipo === 'alerta_problema' ? 'tipo-alerta' : 'tipo-info');
    const icono = n.tipo === 'exportacion_txt' ? 'file-output' : (n.tipo === 'alerta_problema' ? 'alert-triangle' : 'info');
    const noLeida = !n.leida ? 'no-leida' : '';
    const tiempo = tiempoRelativo(n.fecha_creacion);
    
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
            </div>
            <button class="notif-delete-btn" onclick="event.stopPropagation(); eliminarNotif(${n.id_notificacion})" title="Eliminar">
                <i data-lucide="x" style="width:14px;height:14px;"></i>
            </button>
        </div>
    `;
}

async function verNotificacion(id) {
    await fetch(API_NOTIF + '?action=marcar_leida&id=' + id);
    cargarContadorNotificaciones();
    cargarNotificaciones();
}

async function marcarTodasLeidas() {
    await fetch(API_NOTIF + '?action=marcar_todas_leidas');
    cargarContadorNotificaciones();
    cargarNotificaciones();
}

async function eliminarNotif(id) {
    await fetch(API_NOTIF + '?action=eliminar&id=' + id);
    cargarContadorNotificaciones();
    cargarNotificaciones();
}

async function limpiarLeidas() {
    const confirmado = await Swal.fire({
        title: 'Limpiar leídas',
        text: 'Se eliminarán las notificaciones ya leídas.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#2563EB',
        cancelButtonColor: '#64748B',
        confirmButtonText: 'Sí, limpiar',
        cancelButtonText: 'Cancelar'
    });
    if (!confirmado.isConfirmed) return;
    
    await fetch(API_NOTIF + '?action=eliminar_leidas');
    cargarContadorNotificaciones();
    cargarNotificaciones();
}

function tiempoRelativo(fecha) {
    const diff = Math.floor((new Date() - new Date(fecha)) / 1000);
    if (diff < 60) return 'Ahora';
    if (diff < 3600) return Math.floor(diff / 60) + ' min';
    if (diff < 86400) return Math.floor(diff / 3600) + ' h';
    if (diff < 604800) return Math.floor(diff / 86400) + ' d';
    return new Date(fecha).toLocaleDateString('es-MX', { day: '2-digit', month: 'short' });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>
