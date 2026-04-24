<?php
/**
 * SIAE-IMSS - Dashboard Admin Servicios Escolares
 * Vista principal del módulo
 */

$tituloPagina = 'Dashboard';
$currentPage = 'dashboard';

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Verificar autenticación y rol
requerirLogin();
requerirRol(ROL_ADMIN_SERVICIOS);

$conexion = obtenerConexion();
$idUsuario = obtenerIdUsuarioActual();

// Estadísticas
try {
    // Tablas listas para exportar
    $consulta = $conexion->query("SELECT COUNT(*) as total FROM tablas_movimientos WHERE estado = 'validado'");
    $tablasListas = $consulta->fetch()['total'] ?? 0;
    
    // Mis exportaciones de hoy
    $consulta = $conexion->prepare("
        SELECT COUNT(*) as total 
        FROM bitacora 
        WHERE accion = 'EXPORTAR_TXT' 
        AND id_usuario = ? 
        AND DATE(fecha) = CURDATE()
    ");
    $consulta->execute([$idUsuario]);
    $misExportacionesHoy = $consulta->fetch()['total'] ?? 0;
    
    // Alertas pendientes (problemas reportados por Jefa)
    $consulta = $conexion->prepare("
        SELECT COUNT(*) as total 
        FROM notificaciones 
        WHERE id_usuario_destino = ? 
        AND tipo = 'alerta_problema' 
        AND leida = 0
    ");
    $consulta->execute([$idUsuario]);
    $alertasPendientes = $consulta->fetch()['total'] ?? 0;
    
    // Total exportaciones del mes
    $consulta = $conexion->prepare("
        SELECT COUNT(*) as total 
        FROM bitacora 
        WHERE accion = 'EXPORTAR_TXT' 
        AND id_usuario = ? 
        AND MONTH(fecha) = MONTH(CURDATE())
        AND YEAR(fecha) = YEAR(CURDATE())
    ");
    $consulta->execute([$idUsuario]);
    $exportacionesMes = $consulta->fetch()['total'] ?? 0;
    
    // Últimas exportaciones
    $consulta = $conexion->prepare("
        SELECT b.*, t.nombre as tabla_nombre, t.tipo
        FROM bitacora b
        LEFT JOIN tablas_movimientos t ON b.detalle LIKE CONCAT('%Tabla ID: ', t.id_tabla, '%')
        WHERE b.accion = 'EXPORTAR_TXT' 
        AND b.id_usuario = ?
        ORDER BY b.fecha DESC
        LIMIT 5
    ");
    $consulta->execute([$idUsuario]);
    $ultimasExportaciones = $consulta->fetchAll();
    
} catch (Exception $e) {
    $tablasListas = 0;
    $misExportacionesHoy = 0;
    $alertasPendientes = 0;
    $exportacionesMes = 0;
    $ultimasExportaciones = [];
}

include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar-admin-se.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">Bienvenido, <?= htmlspecialchars($currentUser['nombre_completo'] ?? 'Usuario') ?></p>
    </div>
</div>

<?php if ($alertasPendientes > 0): ?>
<div class="alert alert-warning" style="margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
    <i data-lucide="alert-triangle" style="width: 24px; height: 24px;"></i>
    <div>
        <strong>Tienes <?= $alertasPendientes ?> alerta(s) pendiente(s)</strong><br>
        <span style="font-size: 14px;">La Jefa de Servicios ha marcado algunas exportaciones para revisión.</span>
    </div>
    <a href="<?= URL_BASE ?>views/admin-se/historial.php" class="btn btn-warning" style="margin-left: auto;">Ver alertas</a>
</div>
<?php endif; ?>

<!-- Tarjetas de estadísticas -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: #DBEAFE; color: #2563EB;">
            <i data-lucide="file-check"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= $tablasListas ?></div>
            <div class="stat-label">Tablas listas para exportar</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: #DCFCE7; color: #16A34A;">
            <i data-lucide="file-output"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= $misExportacionesHoy ?></div>
            <div class="stat-label">Mis exportaciones hoy</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: #FEF3C7; color: #D97706;">
            <i data-lucide="calendar"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= $exportacionesMes ?></div>
            <div class="stat-label">Exportaciones este mes</div>
        </div>
    </div>
    
    <div class="stat-card <?= $alertasPendientes > 0 ? 'stat-card-danger' : '' ?>">
        <div class="stat-icon" style="background: <?= $alertasPendientes > 0 ? '#FEE2E2' : '#F1F5F9' ?>; color: <?= $alertasPendientes > 0 ? '#DC2626' : '#64748B' ?>;">
            <i data-lucide="alert-circle"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= $alertasPendientes ?></div>
            <div class="stat-label">Alertas pendientes</div>
        </div>
    </div>
</div>

<!-- Acciones rápidas -->
<div class="card" style="margin-top: 24px;">
    <div class="card-header">
        <h3 class="card-title">Acciones rápidas</h3>
    </div>
    <div class="card-body">
        <div style="display: flex; gap: 16px; flex-wrap: wrap;">
            <a href="<?= URL_BASE ?>views/admin-se/exportar.php" class="btn btn-primary" style="display: flex; align-items: center; gap: 8px;">
                <i data-lucide="file-output" style="width: 18px; height: 18px;"></i>
                Exportar TXT
            </a>
            <a href="<?= URL_BASE ?>views/admin-se/carpetas.php" class="btn btn-secondary" style="display: flex; align-items: center; gap: 8px;">
                <i data-lucide="folders" style="width: 18px; height: 18px;"></i>
                Ver Carpetas
            </a>
            <a href="<?= URL_BASE ?>views/admin-se/historial.php" class="btn btn-secondary" style="display: flex; align-items: center; gap: 8px;">
                <i data-lucide="history" style="width: 18px; height: 18px;"></i>
                Mi Historial
            </a>
        </div>
    </div>
</div>

<!-- Últimas exportaciones -->
<div class="card" style="margin-top: 24px;">
    <div class="card-header">
        <h3 class="card-title">Mis últimas exportaciones</h3>
    </div>
    <div class="card-body">
        <?php if (count($ultimasExportaciones) > 0): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Archivo</th>
                    <th>Detalle</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ultimasExportaciones as $exp): ?>
                <tr>
                    <td><?= date('d/m/Y H:i', strtotime($exp['fecha'])) ?></td>
                    <td>
                        <?php
                        // Extraer nombre del archivo del detalle
                        preg_match('/TXT generado: ([^\s]+)/', $exp['detalle'], $matches);
                        $archivo = $matches[1] ?? 'N/A';
                        ?>
                        <code style="background: #F1F5F9; padding: 2px 8px; border-radius: 4px;"><?= htmlspecialchars($archivo) ?></code>
                    </td>
                    <td style="font-size: 13px; color: #64748B;"><?= htmlspecialchars($exp['detalle']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div style="text-align: center; padding: 40px; color: #64748B;">
            <i data-lucide="inbox" style="width: 48px; height: 48px; margin-bottom: 12px; opacity: 0.5;"></i>
            <p>No has realizado exportaciones aún</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.stat-card-danger {
    border: 2px solid #FCA5A5;
    animation: pulse-danger 2s infinite;
}
@keyframes pulse-danger {
    0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.2); }
    50% { box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
}
.alert-warning {
    background: #FEF3C7;
    border: 1px solid #FCD34D;
    border-radius: 8px;
    padding: 16px 20px;
    color: #92400E;
}
</style>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
