<?php
/**
 * SIAE-IMSS - Dashboard Superadmin
 */
$pageTitle = 'Panel de Control General';

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Verificar acceso
requireRole([ROL_SUPERADMIN]);

// Obtener estadísticas
$pdo = getConnection();

// Usuarios activos
$stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios WHERE activo = 1");
$totalUsuarios = $stmt->fetch()['total'];

// Alumnos registrados
$stmt = $pdo->query("SELECT COUNT(*) as total FROM alumnos WHERE activo = 1");
$totalAlumnos = $stmt->fetch()['total'];

// Roles configurados
$stmt = $pdo->query("SELECT COUNT(*) as total FROM roles");
$totalRoles = $stmt->fetch()['total'];

// Acciones hoy
$stmt = $pdo->query("SELECT COUNT(*) as total FROM bitacora WHERE DATE(fecha) = CURDATE()");
$accionesHoy = $stmt->fetch()['total'];

// Últimas acciones en bitácora
$stmt = $pdo->query("
    SELECT b.*, u.nombre_completo, u.username 
    FROM bitacora b 
    JOIN usuarios u ON b.id_usuario = u.id_usuario 
    ORDER BY b.fecha DESC 
    LIMIT 10
");
$ultimasAcciones = $stmt->fetchAll();

// Incluir header y sidebar
include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar-superadmin.php';
?>

<!-- Page Header -->
<div class="page-header">
    <h1 class="page-title">Panel de Control General</h1>
    <p class="page-subtitle">Resumen ejecutivo del estado del sistema institucional.</p>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary">
            <i data-lucide="users"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Usuarios Activos</div>
            <div class="stat-value primary"><?= number_format($totalUsuarios) ?></div>
            <div class="stat-change up">↗ +12% vs mes anterior</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon success">
            <i data-lucide="graduation-cap"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Alumnos Registrados</div>
            <div class="stat-value success"><?= number_format($totalAlumnos) ?></div>
            <div class="stat-change">📊 Base de datos nacional</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon info">
            <i data-lucide="shield"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Roles Configurados</div>
            <div class="stat-value info"><?= $totalRoles ?></div>
            <div class="stat-change">⚙️ <?= $totalRoles ?> permisos pendientes</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon warning">
            <i data-lucide="zap"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Acciones Hoy</div>
            <div class="stat-value warning"><?= number_format($accionesHoy) ?></div>
            <div class="stat-change">🕐 Pico de tráfico: 11:00 AM</div>
        </div>
    </div>
</div>

<!-- Content Grid -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
    
    <!-- Últimas acciones en bitácora -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i data-lucide="scroll-text" style="width: 18px; height: 18px; margin-right: 8px;"></i>
                Últimas acciones en bitácora
            </h3>
            <a href="<?= BASE_URL ?>views/superadmin/bitacora.php" class="btn btn-outline btn-sm">
                Exportar CSV
            </a>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Acción</th>
                        <th>Fecha</th>
                        <th>Detalles</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ultimasAcciones)): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted" style="padding: 40px;">
                            No hay acciones registradas
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($ultimasAcciones as $accion): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-center gap-2">
                                    <div class="avatar avatar-sm" style="background: <?= getAvatarColor($accion['nombre_completo']) ?>">
                                        <?= getInitials($accion['nombre_completo']) ?>
                                    </div>
                                    <span><?= htmlspecialchars($accion['nombre_completo']) ?></span>
                                </div>
                            </td>
                            <td>
                                <?php
                                $badgeClass = 'badge-secondary';
                                if (strpos($accion['accion'], 'LOGIN') !== false) $badgeClass = 'badge-success';
                                elseif (strpos($accion['accion'], 'CREAR') !== false || strpos($accion['accion'], 'ALTA') !== false) $badgeClass = 'badge-info';
                                elseif (strpos($accion['accion'], 'EDITAR') !== false || strpos($accion['accion'], 'EDICIÓN') !== false) $badgeClass = 'badge-warning';
                                elseif (strpos($accion['accion'], 'ELIMINAR') !== false || strpos($accion['accion'], 'BAJA') !== false) $badgeClass = 'badge-danger';
                                elseif (strpos($accion['accion'], 'EXPORTAR') !== false) $badgeClass = 'badge-info';
                                ?>
                                <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($accion['accion']) ?></span>
                            </td>
                            <td>
                                <?php 
                                $fecha = strtotime($accion['fecha']);
                                $hoy = strtotime('today');
                                $ayer = strtotime('yesterday');
                                
                                if ($fecha >= $hoy) {
                                    echo 'Hoy, ' . date('H:i', $fecha);
                                } elseif ($fecha >= $ayer) {
                                    echo 'Ayer, ' . date('H:i', $fecha);
                                } else {
                                    echo date('d/m/Y H:i', $fecha);
                                }
                                ?>
                            </td>
                            <td>
                                <button class="btn btn-ghost btn-icon" title="Ver detalles" onclick="verDetalle('<?= htmlspecialchars(addslashes($accion['detalle'])) ?>')">
                                    <i data-lucide="eye"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Accesos rápidos -->
    <div>
        <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
            <i data-lucide="zap" style="width: 18px; height: 18px;"></i>
            Accesos rápidos
        </h3>
        
        <div class="quick-actions">
            <a href="<?= BASE_URL ?>views/superadmin/usuarios.php?action=nuevo" class="quick-action-btn primary">
                <i data-lucide="user-plus"></i>
                <div class="quick-action-text">
                    Administrar usuarios
                    <small>Altas, bajas y modificaciones</small>
                </div>
            </a>
            
            <a href="<?= BASE_URL ?>views/superadmin/catalogos.php" class="quick-action-btn secondary">
                <i data-lucide="database"></i>
                <div class="quick-action-text">
                    Gestionar catálogos
                    <small>Parámetros del sistema</small>
                </div>
            </a>
            
            <a href="<?= BASE_URL ?>views/superadmin/bitacora.php" class="quick-action-btn secondary">
                <i data-lucide="file-text"></i>
                <div class="quick-action-text">
                    Ver bitácora
                    <small>Auditoría completa</small>
                </div>
            </a>
        </div>
    </div>
    
</div>

<script>
function verDetalle(detalle) {
    Swal.fire({
        title: 'Detalle de la acción',
        text: detalle || 'Sin detalles adicionales',
        icon: 'info',
        confirmButtonColor: '#2563EB'
    });
}
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
