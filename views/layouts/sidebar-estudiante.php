<?php
/**
 * SIAE-IMSS - Sidebar Estudiante (ROL 5)
 */

if (obtenerRolActual() > 5) {
    include __DIR__ . '/sidebar-custom.php';
    return;
}

$paginaActual = basename($_SERVER['PHP_SELF'], '.php');
$currentUser  = obtenerUsuarioActual();
$userInitials = obtenerIniciales($currentUser['nombre_completo'] ?? 'AL');
$avatarColor  = obtenerColorAvatar($currentUser['nombre_completo'] ?? 'Alumno');
?>

<aside class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <div class="sidebar-logo-icon">E</div>
            <div>
                <div class="sidebar-logo-text">SIAE-IMSS</div>
                <div class="sidebar-logo-sub">Portal Alumno</div>
            </div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <a href="<?= URL_BASE ?>views/estudiante/dashboard.php" class="nav-item <?= $paginaActual === 'dashboard' ? 'active' : '' ?>">
            <i data-lucide="layout-dashboard"></i><span>Mi Estatus IMSS</span>
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
// Estudiante no tiene notificaciones ni campana
$conNotificaciones = false;
include __DIR__ . '/_main-header.php';
?>
