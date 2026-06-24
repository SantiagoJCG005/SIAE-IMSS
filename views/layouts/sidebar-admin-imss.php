<?php
/**
 * SIAE-IMSS - Sidebar Admin IMSS
 */

if (obtenerRolActual() > 5) {
    include __DIR__ . '/sidebar-custom.php';
    return;
}

$paginaActual = basename($_SERVER['PHP_SELF'], '.php');
$currentUser  = obtenerUsuarioActual();
$userInitials = obtenerIniciales($currentUser['nombre_completo'] ?? 'SA');
$avatarColor  = obtenerColorAvatar($currentUser['nombre_completo'] ?? 'Superadmin');
?>

<aside class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <div class="sidebar-logo-icon">A</div>
            <div>
                <div class="sidebar-logo-text">SIAE-IMSS</div>
                <div class="sidebar-logo-sub">Admin IMSS</div>
            </div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <a href="<?= URL_BASE ?>views/admin-imss/dashboard.php" class="nav-item <?= $paginaActual === 'dashboard' ? 'active' : '' ?>">
            <i data-lucide="layout-dashboard"></i><span>Inicio</span>
        </a>
        <a href="<?= URL_BASE ?>views/admin-imss/reportes.php" class="nav-item <?= $paginaActual === 'reportes' ? 'active' : '' ?>">
            <i data-lucide="bar-chart-3"></i><span>Reportes</span>
        </a>
        <a href="<?= URL_BASE ?>views/admin-imss/exportar.php" class="nav-item <?= $paginaActual === 'exportar' ? 'active' : '' ?>">
            <i data-lucide="file-down"></i><span>Exportar TXT</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="<?= URL_BASE ?>views/perfil/cambiar-password.php" class="nav-item <?= $paginaActual === 'cambiar-password' ? 'active' : '' ?>">
            <i data-lucide="key-round"></i><span>Cambiar contraseña</span>
        </a>
        <a href="<?= URL_BASE ?>api/auth.php?action=logout" class="nav-item">
            <i data-lucide="log-out"></i><span>Cerrar sesión</span>
        </a>
    </div>
</aside>

<?php
$conNotificaciones  = true;
$urlVerTodas        = URL_BASE . 'views/admin-imss/notificaciones.php';
$puedeAprobarExport = false;
$urlCorregirAlumno  = '';
include __DIR__ . '/_main-header.php';
?>
