<?php
/**
 * SIAE-IMSS - Sidebar Superadmin
 */
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentUser = getCurrentUser();
$userInitials = getInitials($currentUser['nombre_completo'] ?? 'SA');
$avatarColor = getAvatarColor($currentUser['nombre_completo'] ?? 'Superadmin');
?>

<!-- Sidebar -->
<aside class="sidebar">
    <!-- Logo -->
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <div class="sidebar-logo-icon">S</div>
            <div>
                <div class="sidebar-logo-text">SIAE-IMSS</div>
                <div class="sidebar-logo-sub">Superadministrador</div>
            </div>
        </div>
    </div>
    
    <!-- Usuario -->
    <div class="sidebar-user">
        <div class="sidebar-user-avatar" style="background: <?= $avatarColor ?>">
            <?= $userInitials ?>
        </div>
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?= htmlspecialchars($currentUser['nombre_completo'] ?? 'Usuario') ?></div>
            <div class="sidebar-user-role"><?= htmlspecialchars($currentUser['rol_nombre'] ?? 'Superadmin') ?></div>
        </div>
    </div>
    
    <!-- Navegación -->
    <nav class="sidebar-nav">
        <a href="<?= BASE_URL ?>views/superadmin/dashboard.php" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
            <i data-lucide="layout-dashboard"></i>
            <span>Inicio</span>
        </a>
        <a href="<?= BASE_URL ?>views/superadmin/usuarios.php" class="nav-item <?= $currentPage === 'usuarios' ? 'active' : '' ?>">
            <i data-lucide="users"></i>
            <span>Usuarios</span>
        </a>
        <a href="<?= BASE_URL ?>views/superadmin/roles.php" class="nav-item <?= $currentPage === 'roles' ? 'active' : '' ?>">
            <i data-lucide="shield"></i>
            <span>Roles</span>
        </a>
        <a href="<?= BASE_URL ?>views/superadmin/catalogos.php" class="nav-item <?= $currentPage === 'catalogos' ? 'active' : '' ?>">
            <i data-lucide="database"></i>
            <span>Catálogos</span>
        </a>
        <a href="<?= BASE_URL ?>views/superadmin/bitacora.php" class="nav-item <?= $currentPage === 'bitacora' ? 'active' : '' ?>">
            <i data-lucide="scroll-text"></i>
            <span>Bitácora</span>
        </a>
        <a href="<?= BASE_URL ?>views/superadmin/configuracion.php" class="nav-item <?= $currentPage === 'configuracion' ? 'active' : '' ?>">
            <i data-lucide="settings"></i>
            <span>Configuración</span>
        </a>
    </nav>
    
    <!-- Footer -->
    <div class="sidebar-footer">
        <a href="<?= BASE_URL ?>api/auth.php?action=logout" class="nav-item">
            <i data-lucide="log-out"></i>
            <span>Cerrar sesión</span>
        </a>
    </div>
</aside>

<!-- Main Content -->
<main class="main-content">
    <!-- Header -->
    <header class="main-header">
        <div class="header-search">
            <i data-lucide="search"></i>
            <input type="text" placeholder="Buscar en el sistema..." id="globalSearch">
        </div>
        
        <div class="header-actions">
            <button class="header-icon-btn" title="Notificaciones">
                <i data-lucide="bell"></i>
            </button>
            <button class="header-icon-btn" title="Ayuda">
                <i data-lucide="help-circle"></i>
            </button>
            <div class="header-user">
                <div class="header-user-info">
                    <div class="header-user-name"><?= htmlspecialchars($currentUser['nombre_completo'] ?? 'Usuario') ?></div>
                    <div class="header-user-role"><?= htmlspecialchars($currentUser['rol_nombre'] ?? 'Superadmin') ?></div>
                </div>
                <div class="header-user-avatar" style="background: <?= $avatarColor ?>">
                    <?= $userInitials ?>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Page Content -->
    <div class="page-content">
