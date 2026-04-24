<?php
/**
 * SIAE-IMSS - Sidebar para Admin Servicios Escolares (ROL 3)
 * Menu lateral con navegación del módulo
 */

// Obtiene la página actual para marcar el menú activo
$paginaActual = basename($_SERVER['PHP_SELF'], '.php');

// Obtiene los datos del usuario logueado
$currentUser = obtenerUsuarioActual();
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

        <a href="<?= URL_BASE ?>views/admin-se/exportar.php" class="nav-item <?= $paginaActual === 'exportar' ? 'active' : '' ?>">
            <i data-lucide="file-output"></i>
            <span>Exportar TXT</span>
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
                        <button onclick="marcarTodasLeidas()" class="notif-mark-all">Marcar todas leídas</button>
                    </div>
                    <div class="notif-dropdown-body" id="notifLista">
                        <div class="notif-loading">Cargando...</div>
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

<!-- Estilos para notificaciones -->
<style>
.notif-container { position: relative; }
.notif-badge {
    position: absolute; top: -4px; right: -4px;
    background: #EF4444; color: white;
    font-size: 11px; font-weight: 600;
    min-width: 18px; height: 18px;
    border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    padding: 0 4px;
}
.notif-dropdown {
    position: absolute; top: 100%; right: 0; margin-top: 8px;
    width: 340px; max-height: 400px;
    background: white; border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    z-index: 1000; overflow: hidden;
}
.notif-dropdown-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 14px 16px; border-bottom: 1px solid #E2E8F0;
    font-weight: 600; color: #1E293B;
}
.notif-mark-all { background: none; border: none; color: #2563EB; font-size: 12px; cursor: pointer; }
.notif-dropdown-body { max-height: 300px; overflow-y: auto; }
.notif-item {
    display: flex; gap: 12px; padding: 12px 16px;
    border-bottom: 1px solid #F1F5F9; cursor: pointer;
}
.notif-item:hover { background: #F8FAFC; }
.notif-item.no-leida { background: #FEF2F2; }
.notif-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; }
.notif-icon.alerta { background: #FEE2E2; }
.notif-content { flex: 1; }
.notif-titulo { font-size: 13px; font-weight: 500; color: #1E293B; }
.notif-tiempo { font-size: 11px; color: #94A3B8; margin-top: 2px; }
.notif-empty, .notif-loading { padding: 30px; text-align: center; color: #64748B; }
</style>

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
            lista.innerHTML = data.data.map(n => {
                const noLeida = !n.leida ? 'no-leida' : '';
                const tiempo = tiempoRelativo(n.fecha_creacion);
                return `
                    <div class="notif-item ${noLeida}" onclick="verNotificacion(${n.id_notificacion})">
                        <div class="notif-icon alerta">⚠️</div>
                        <div class="notif-content">
                            <div class="notif-titulo">${escapeHtml(n.titulo)}</div>
                            <div class="notif-tiempo">${tiempo}</div>
                        </div>
                    </div>
                `;
            }).join('');
        } else {
            lista.innerHTML = '<div class="notif-empty">🔔 No hay notificaciones</div>';
        }
    } catch (e) {
        lista.innerHTML = '<div class="notif-empty">Error al cargar</div>';
    }
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

function tiempoRelativo(fecha) {
    const diff = Math.floor((new Date() - new Date(fecha)) / 1000);
    if (diff < 60) return 'Hace un momento';
    if (diff < 3600) return `Hace ${Math.floor(diff / 60)} min`;
    if (diff < 86400) return `Hace ${Math.floor(diff / 3600)} hrs`;
    return `Hace ${Math.floor(diff / 86400)} días`;
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>
