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
                        <button onclick="marcarTodasLeidas()" class="notif-mark-all">Marcar todas leídas</button>
                    </div>
                    <div class="notif-dropdown-body" id="notifLista">
                        <div class="notif-loading">Cargando...</div>
                    </div>
                    <div class="notif-dropdown-footer">
                        <a href="<?= URL_BASE ?>views/jefa/notificaciones.php">Ver todas</a>
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

<!-- Estilos para notificaciones -->
<style>
.notif-container { position: relative; }
.notif-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    background: #EF4444;
    color: white;
    font-size: 11px;
    font-weight: 600;
    min-width: 18px;
    height: 18px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
}
.notif-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 8px;
    width: 360px;
    max-height: 480px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    z-index: 1000;
    overflow: hidden;
}
.notif-dropdown-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px;
    border-bottom: 1px solid #E2E8F0;
    font-weight: 600;
    color: #1E293B;
}
.notif-mark-all {
    background: none;
    border: none;
    color: #2563EB;
    font-size: 12px;
    cursor: pointer;
}
.notif-mark-all:hover { text-decoration: underline; }
.notif-dropdown-body {
    max-height: 340px;
    overflow-y: auto;
}
.notif-item {
    display: flex;
    gap: 12px;
    padding: 14px 16px;
    border-bottom: 1px solid #F1F5F9;
    cursor: pointer;
    transition: background 0.15s;
}
.notif-item:hover { background: #F8FAFC; }
.notif-item.no-leida { background: #EFF6FF; }
.notif-item.no-leida:hover { background: #DBEAFE; }
.notif-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
}
.notif-icon.exportacion { background: #FEF3C7; }
.notif-icon.alerta { background: #FEE2E2; }
.notif-icon.info { background: #DBEAFE; }
.notif-content { flex: 1; min-width: 0; }
.notif-titulo {
    font-size: 13px;
    font-weight: 500;
    color: #1E293B;
    margin-bottom: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.notif-mensaje {
    font-size: 12px;
    color: #64748B;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.notif-tiempo {
    font-size: 11px;
    color: #94A3B8;
    margin-top: 4px;
}
.notif-dropdown-footer {
    padding: 12px 16px;
    text-align: center;
    border-top: 1px solid #E2E8F0;
}
.notif-dropdown-footer a {
    color: #2563EB;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
}
.notif-dropdown-footer a:hover { text-decoration: underline; }
.notif-empty {
    padding: 40px 20px;
    text-align: center;
    color: #94A3B8;
}
.notif-loading {
    padding: 30px;
    text-align: center;
    color: #64748B;
}
.notif-acciones {
    display: flex;
    gap: 8px;
    margin-top: 8px;
}
.notif-btn {
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    border: none;
    cursor: pointer;
}
.notif-btn-ok { background: #DCFCE7; color: #166534; }
.notif-btn-problema { background: #FEE2E2; color: #991B1B; }
</style>

<!-- JavaScript para notificaciones -->
<script>
const API_NOTIF = '<?= URL_BASE ?>api/notificaciones.php';
let notifDropdownAbierto = false;

// Cargar contador de notificaciones al iniciar
document.addEventListener('DOMContentLoaded', () => {
    cargarContadorNotificaciones();
    // Actualizar cada 60 segundos
    setInterval(cargarContadorNotificaciones, 60000);
});

// Cerrar dropdown al hacer clic fuera
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
        } else {
            lista.innerHTML = '<div class="notif-empty">🔔 No hay notificaciones</div>';
        }
    } catch (e) {
        lista.innerHTML = '<div class="notif-empty">Error al cargar</div>';
    }
}

function renderNotificacion(n) {
    const icono = n.tipo === 'exportacion_txt' ? '📤' : (n.tipo === 'alerta_problema' ? '⚠️' : 'ℹ️');
    const iconoClass = n.tipo === 'exportacion_txt' ? 'exportacion' : (n.tipo === 'alerta_problema' ? 'alerta' : 'info');
    const noLeida = !n.leida ? 'no-leida' : '';
    const tiempo = tiempoRelativo(n.fecha_creacion);
    
    let acciones = '';
    if (n.tipo === 'exportacion_txt' && n.estado === 'nueva') {
        acciones = `
            <div class="notif-acciones">
                <button class="notif-btn notif-btn-ok" onclick="event.stopPropagation(); cambiarEstado(${n.id_notificacion}, 'revisada')">✓ OK</button>
                <button class="notif-btn notif-btn-problema" onclick="event.stopPropagation(); cambiarEstado(${n.id_notificacion}, 'problema')">⚠️ Revisar</button>
            </div>
        `;
    }
    
    return `
        <div class="notif-item ${noLeida}" onclick="verNotificacion(${n.id_notificacion})">
            <div class="notif-icon ${iconoClass}">${icono}</div>
            <div class="notif-content">
                <div class="notif-titulo">${escapeHtml(n.titulo)}</div>
                <div class="notif-mensaje">${n.nombre_origen ? 'Por: ' + escapeHtml(n.nombre_origen) : ''}</div>
                <div class="notif-tiempo">${tiempo}</div>
                ${acciones}
            </div>
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

async function cambiarEstado(id, estado) {
    await fetch(API_NOTIF + '?action=cambiar_estado&id=' + id + '&estado=' + estado);
    cargarContadorNotificaciones();
    cargarNotificaciones();
    
    if (estado === 'problema') {
        mostrarNotificacion('Se ha notificado al usuario que debe revisar esta exportación', 'warning');
    } else {
        mostrarNotificacion('Marcada como revisada', 'success');
    }
}

function tiempoRelativo(fecha) {
    const ahora = new Date();
    const notif = new Date(fecha);
    const diff = Math.floor((ahora - notif) / 1000);
    
    if (diff < 60) return 'Hace un momento';
    if (diff < 3600) return `Hace ${Math.floor(diff / 60)} min`;
    if (diff < 86400) return `Hace ${Math.floor(diff / 3600)} hrs`;
    if (diff < 604800) return `Hace ${Math.floor(diff / 86400)} días`;
    return notif.toLocaleDateString('es-MX');
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>
