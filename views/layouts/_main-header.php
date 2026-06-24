<?php
/**
 * Componente: apertura del <main> y <header> superior
 *
 * Variables PHP que deben estar definidas antes de incluir:
 *   array  $currentUser         datos del usuario logueado
 *   string $avatarColor         color de fondo del avatar
 *   string $userInitials        iniciales del usuario
 *   bool   $conNotificaciones   true = mostrar campana con dropdown completo
 *                               false = mostrar campana simple (sin dropdown)
 *   string $urlVerTodas         URL a página de notificaciones (solo si $conNotificaciones)
 *   bool   $puedeAprobarExport  true solo para Jefa (default false)
 *   string $urlCorregirAlumno   URL base corregir-alumno.php (default '')
 */
$conNotificaciones  = $conNotificaciones  ?? false;
$puedeAprobarExport = $puedeAprobarExport ?? false;
$urlCorregirAlumno  = $urlCorregirAlumno  ?? '';
$urlVerTodas        = $urlVerTodas        ?? '#';
$userInitials = obtenerIniciales($currentUser['nombre_completo'] ?? 'SA');
$avatarColor  = obtenerColorAvatar($currentUser['nombre_completo'] ?? 'Superadmin');
?>
<main class="main-content">
    <header class="main-header">
        <div class="header-search"></div>

        <div class="header-actions">

            <?php if ($conNotificaciones): ?>
            <!-- Campana con dropdown de notificaciones -->
            <div class="notif-container" style="position: relative;">
                <button class="header-icon-btn" title="Notificaciones" onclick="toggleNotificaciones()" id="btnNotificaciones">
                    <i data-lucide="bell"></i>
                    <span class="notif-badge" id="notifBadge" style="display: none;">0</span>
                </button>
                <div class="notif-dropdown" id="notifDropdown" style="display: none;">
                    <div class="notif-dropdown-header">
                        <span>Notificaciones</span>
                        <div class="notif-header-actions">
                            <button onclick="marcarTodasLeidas()" class="notif-header-btn">Leídas</button>
                            <button onclick="limpiarLeidas()"     class="notif-header-btn">Limpiar</button>
                        </div>
                    </div>
                    <div class="notif-dropdown-body" id="notifLista">
                        <div class="notif-loading">Cargando...</div>
                    </div>
                    <div class="notif-dropdown-footer">
                        <a href="<?= htmlspecialchars($urlVerTodas) ?>">Ver todas las notificaciones</a>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Campana simple sin funcionalidad (Superadmin) -->
            <button class="header-icon-btn" title="Notificaciones">
                <i data-lucide="bell"></i>
            </button>
            <?php endif; ?>

            <button class="header-icon-btn" title="Ayuda">
                <i data-lucide="help-circle"></i>
            </button>

            <div class="header-user">
                <div class="header-user-info">
                    <div class="header-user-name"><?= htmlspecialchars($currentUser['nombre_completo'] ?? 'Usuario') ?></div>
                    <div class="header-user-role"><?= htmlspecialchars($currentUser['rol_nombre'] ?? '') ?></div>
                </div>
                <div class="header-user-avatar" style="background: <?= $avatarColor ?>">
                    <?= $userInitials ?>
                </div>
            </div>

        </div>
    </header>

    <div class="page-content">

<?php if ($conNotificaciones): ?>
<?php include __DIR__ . '/_notificaciones.js.php'; ?>
<?php endif; ?>
