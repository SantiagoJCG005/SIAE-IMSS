<?php
/**
 * SIAE-IMSS - Sidebar Superadmin
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
            <div class="sidebar-logo-icon">S</div>
            <div>
                <div class="sidebar-logo-text">SIAE-IMSS</div>
                <div class="sidebar-logo-sub">Superadministrador</div>
            </div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <a href="<?= URL_BASE ?>views/superadmin/dashboard.php" class="nav-item <?= $paginaActual === 'dashboard' ? 'active' : '' ?>">
            <i data-lucide="layout-dashboard"></i><span>Inicio</span>
        </a>
        <a href="<?= URL_BASE ?>views/superadmin/usuarios.php" class="nav-item <?= $paginaActual === 'usuarios' ? 'active' : '' ?>">
            <i data-lucide="users"></i><span>Usuarios</span>
        </a>
        <a href="<?= URL_BASE ?>views/superadmin/roles.php" class="nav-item <?= $paginaActual === 'roles' ? 'active' : '' ?>">
            <i data-lucide="shield"></i><span>Roles</span>
        </a>
        <a href="<?= URL_BASE ?>views/superadmin/catalogos.php" class="nav-item <?= $paginaActual === 'catalogos' ? 'active' : '' ?>">
            <i data-lucide="database"></i><span>Catálogos</span>
        </a>
        <a href="<?= URL_BASE ?>views/superadmin/bitacora.php" class="nav-item <?= $paginaActual === 'bitacora' ? 'active' : '' ?>">
            <i data-lucide="scroll-text"></i><span>Bitácora</span>
        </a>
        <a href="<?= URL_BASE ?>views/superadmin/configuracion.php" class="nav-item <?= $paginaActual === 'configuracion' ? 'active' : '' ?>">
            <i data-lucide="settings"></i><span>Configuración</span>
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
// Superadmin no usa notificaciones — campana simple sin dropdown
$conNotificaciones = false;
include __DIR__ . '/_main-header.php';
?>
