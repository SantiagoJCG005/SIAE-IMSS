<?php
/**
 * SIAE-IMSS - Sidebar para Estudiante (ROL 5)
 * Menu lateral del portal del alumno con solo la vista de estatus IMSS
 */

// PHP - obtener nombre del archivo actual para marcar el item activo del menu
$paginaActual = basename($_SERVER['PHP_SELF'], '.php');
// PHP - obtener datos del estudiante logueado
$currentUser  = obtenerUsuarioActual();
// PHP - iniciales y color de avatar generados desde el nombre del usuario
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
        <a href="<?= URL_BASE ?>views/estudiante/dashboard.php"
           class="nav-item <?= $paginaActual === 'dashboard' ? 'active' : '' ?>">
            <i data-lucide="layout-dashboard"></i>
            <span>Mi Estatus IMSS</span>
        </a>
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
            <button class="header-icon-btn" title="Ayuda">
                <i data-lucide="help-circle"></i>
            </button>
            <div class="header-user">
                <div class="header-user-info">
                    <div class="header-user-name"><?= htmlspecialchars($currentUser['nombre_completo'] ?? 'Alumno') ?></div>
                    <div class="header-user-role">Estudiante</div>
                </div>
                <div class="header-user-avatar" style="background: <?= $avatarColor ?>">
                    <?= $userInitials ?>
                </div>
            </div>
        </div>
    </header>

    <div class="page-content">
