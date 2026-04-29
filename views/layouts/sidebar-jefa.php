<?php
/**
 * SI FUNCIONA NO LE MUEVAS!!!!!
 * SIAE-IMSS - Sidebar para Jefa de Servicios Escolares
 * Menu lateral con navegacion del modulo
 */

// Obtiene la pagina actual para marcar el menu activo
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Obtiene los datos del usuario que esta logueado
$currentUser = obtenerUsuarioActual();
// Obtiene las iniciales del nombre para mostrar en el avatar
// Si no hay nombre, usa "SA" (Superadmin) como valor por defecto
$userInitials = obtenerIniciales($currentUser['nombre_completo'] ?? 'SA');

// Genera un color de fondo para el avatar basado en el nombre
$avatarColor = obtenerColorAvatar($currentUser['nombre_completo'] ?? 'Superadmin');

?>

<aside class="sidebar">
    <!-- Logo -->
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <div class="sidebar-logo-icon">J</div>
            <div>
                <div class="sidebar-logo-text">SIAE-IMSS</div>
                <div class="sidebar-logo-sub">Jefa de Servicios</div>
            </div>
        </div>
    </div>
    

    <nav class="sidebar-nav">
        <?php // Cada enlace usa URL_BASE para formar la ruta completa ?>
        <?php // Si $currentPage coincide con el nombre de la pagina, agrega la clase 'active' para resaltarla ?>
            <a href="<?= URL_BASE ?>views/jefa/dashboard.php"  class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
            <i data-lucide="layout-dashboard"></i>
            <span>Inicio</span>
        </a>
        <a href="<?= URL_BASE ?>views/jefa/carpetas.php" class="nav-item <?= $currentPage === 'carpetas' ? 'active' : '' ?>">
        <i data-lucide="folders"></i>
        <span>Carpetas</span>
         </a>
            
        <a href="<?= URL_BASE ?>views/jefa/importar.php"  class="nav-item <?= $currentPage === 'importar' ? 'active' : '' ?>">
        <i data-lucide="file-spreadsheet"></i>
        <span>Importar Excel</span>
        </a>
        <a href="<?= URL_BASE ?>views/jefa/validar.php"  class="nav-item <?= $currentPage === 'validar' ? 'active' : '' ?>">
        <i data-lucide="check-circle"></i>
        <span>Validar</span>
                    <?php
                    // Cuenta las tablas pendientes de validacion
                    try {
                        $conexion = obtenerConexion();
                        $consulta = $conexion->query("SELECT COUNT(*) as total FROM tablas_movimientos WHERE estado = 'borrador' AND id_usuario_creacion != " . obtenerIdUsuarioActual());
                        $pendientes = $consulta->fetch()['total'] ?? 0;
                        if ($pendientes > 0):
                    ?>
                    <span class="nav-badge"><?= $pendientes ?></span>
                    <?php 
                        endif;
                    } catch (Exception $e) {
                        // Si la tabla no existe aun, no muestra nada
                    }
                    ?>
             </a>
       <a href="<?= URL_BASE ?>views/jefa/reportes.php"  class="nav-item <?= $currentPage === 'reportes' ? 'active' : '' ?>">
     <i data-lucide="bar-chart-3"></i>
     <span>Reportes</span>

     </a>
     
    </nav>
    <div class="sidebar-footer">
        <?php // Enlace que llama al API de autenticacion con la accion logout para cerrar sesion ?>
        <a href="<?= URL_BASE ?>api/auth.php?action=logout" class="nav-item">
            <i data-lucide="log-out"></i>
            <span>Cerrar sesión</span>
        </a>
    </div>
</aside>

<main class="main-content">
    <!-- Header -->
    <header class="main-header">
        <div class="header-search">  
        </div>
        
        <div class="header-actions">
            <!-- Campana de notificaciones con dropdown -->
            <div class="notif-container" style="position: relative;">
                <button class="header-icon-btn" title="Notificaciones" onclick="toggleNotificaciones()" id="btnNotificaciones">
                    <i data-lucide="bell"></i>
                    <span class="notif-badge" id="notifBadge" style="display: none;">0</span>
                </button>
                
                <!-- Dropdown de notificaciones -->
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
                        <a href="<?= URL_BASE ?>views/jefa/notificaciones.php">Ver todas las notificaciones</a>
                    </div>
                </div>
            </div>
            
            <button class="header-icon-btn" title="Ayuda">
                <i data-lucide="help-circle"></i>
            </button>
            <div class="header-user">
                <div class="header-user-info">
                    <?php // Muestra el nombre y rol del usuario en el header ?>
                    <div class="header-user-name"><?= htmlspecialchars($currentUser['nombre_completo'] ?? 'Usuario') ?></div>
                    <div class="header-user-role"><?= htmlspecialchars($currentUser['rol_nombre'] ?? 'Superadmin') ?></div>
                </div>
                <?php // Avatar del usuario con color e iniciales ?>
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
            if (total > 0) {
                badge.textContent = total > 99 ? '99+' : total;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }
    } catch (e) {
        console.error('Error al cargar notificaciones:', e);
    }
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
            lucide.createIcons();
        } else {
            lista.innerHTML = `
                <div class="notif-empty">
                    <i data-lucide="bell-off" style="width:32px;height:32px;display:block;margin:0 auto 8px;"></i>
                    Sin notificaciones
                </div>`;
            lucide.createIcons();
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
    
    let acciones = '';
    if (n.tipo === 'exportacion_txt' && (n.estado === 'nueva' || n.estado === 'vista')) {
        acciones = `
            <div class="notif-actions-inline">
                <button class="notif-action-btn ok" onclick="event.stopPropagation(); cambiarEstado(${n.id_notificacion}, 'revisada')">Aprobar</button>
                <button class="notif-action-btn problema" onclick="event.stopPropagation(); cambiarEstado(${n.id_notificacion}, 'problema')">Revisar</button>
            </div>
        `;
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
        confirmButtonColor: 'var(--secondary)',
        cancelButtonColor: 'var(--text-muted)',
        confirmButtonText: 'Sí, limpiar',
        cancelButtonText: 'Cancelar'
    });
    if (!confirmado.isConfirmed) return;
    
    await fetch(API_NOTIF + '?action=eliminar_leidas');
    cargarContadorNotificaciones();
    cargarNotificaciones();
    mostrarNotificacion('Notificaciones leídas eliminadas', 'success');
}

async function cambiarEstado(id, estado) {
    if (estado === 'problema') {
        const confirmado = await Swal.fire({
            title: '¿Reportar problema?',
            text: 'Se notificará al usuario que realizó la exportación.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: 'var(--danger)',
            cancelButtonColor: 'var(--text-muted)',
            confirmButtonText: 'Sí, reportar',
            cancelButtonText: 'Cancelar'
        });
        if (!confirmado.isConfirmed) return;
    }
    
    await fetch(API_NOTIF + '?action=cambiar_estado&id=' + id + '&estado=' + estado);
    cargarContadorNotificaciones();
    cargarNotificaciones();
    
    if (estado === 'problema') {
        mostrarNotificacion('Problema reportado al usuario', 'warning');
    } else {
        mostrarNotificacion('Marcada como revisada', 'success');
    }
}

function tiempoRelativo(fecha) {
    const ahora = new Date();
    const notif = new Date(fecha);
    const diff = Math.floor((ahora - notif) / 1000);
    
    if (diff < 60) return 'Ahora';
    if (diff < 3600) return Math.floor(diff / 60) + ' min';
    if (diff < 86400) return Math.floor(diff / 3600) + ' h';
    if (diff < 604800) return Math.floor(diff / 86400) + ' d';
    return notif.toLocaleDateString('es-MX', { day: '2-digit', month: 'short' });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>
