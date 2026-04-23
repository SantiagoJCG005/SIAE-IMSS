<?php
/**
 * SI FUNCIONA NO LE MUEVAS!!!!!
 * SIAE-IMSS - Dashboard Jefa de Servicios Escolares
 * Panel principal con estadisticas y accesos rapidos
 */

// Titulo de la pagina
$tituloPagina = 'Dashboard';

// Carga archivos necesarios
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Verifica permisos
requerirRol([ROL_JEFA_SERVICIOS, ROL_SUPERADMIN]);

// Conexion a la base de datos
$conexion = obtenerConexion();

// Obtiene estadisticas
$estadisticas = [
    'carpetas' => 0,
    'borradores' => 0,
    'por_validar' => 0,
    'enviados' => 0,
    'altas_mes' => 0,
    'bajas_mes' => 0
];

try {
    // Total de carpetas activas
    $consulta = $conexion->query("SELECT COUNT(*) as total FROM carpetas_imss WHERE activo = 1");
    $estadisticas['carpetas'] = $consulta->fetch()['total'];
    
    // Tablas en borrador (propias)
    $consulta = $conexion->prepare("SELECT COUNT(*) as total FROM tablas_movimientos WHERE estado = 'borrador' AND id_usuario_creacion = ?");
    $consulta->execute([obtenerIdUsuarioActual()]);
    $estadisticas['borradores'] = $consulta->fetch()['total'];
    
    // Tablas por validar (de otros usuarios)
    $consulta = $conexion->prepare("SELECT COUNT(*) as total FROM tablas_movimientos WHERE estado = 'borrador' AND id_usuario_creacion != ?");
    $consulta->execute([obtenerIdUsuarioActual()]);
    $estadisticas['por_validar'] = $consulta->fetch()['total'];
    
    // Tablas enviadas
    $consulta = $conexion->query("SELECT COUNT(*) as total FROM tablas_movimientos WHERE estado = 'enviado'");
    $estadisticas['enviados'] = $consulta->fetch()['total'];
    
    // Altas del mes actual
    $consulta = $conexion->query("
        SELECT COALESCE(SUM(total_registros), 0) as total 
        FROM tablas_movimientos 
        WHERE tipo = 'alta' 
          AND estado = 'enviado'
          AND MONTH(fecha_envio) = MONTH(CURRENT_DATE())
          AND YEAR(fecha_envio) = YEAR(CURRENT_DATE())
    ");
    $estadisticas['altas_mes'] = $consulta->fetch()['total'];
    
    // Bajas del mes actual
    $consulta = $conexion->query("
        SELECT COALESCE(SUM(total_registros), 0) as total 
        FROM tablas_movimientos 
        WHERE tipo = 'baja' 
          AND estado = 'enviado'
          AND MONTH(fecha_envio) = MONTH(CURRENT_DATE())
          AND YEAR(fecha_envio) = YEAR(CURRENT_DATE())
    ");
    $estadisticas['bajas_mes'] = $consulta->fetch()['total'];
    
} catch (Exception $e) {
    // Si las tablas no existen, mantiene los valores en 0
}

// Obtiene ultimas tablas
$ultimasTablas = [];
try {
    $consulta = $conexion->query("
        SELECT t.*, s.nombre as subcarpeta_nombre, c.nombre as carpeta_nombre
        FROM tablas_movimientos t
        INNER JOIN subcarpetas_imss s ON t.id_subcarpeta = s.id_subcarpeta
        INNER JOIN carpetas_imss c ON s.id_carpeta = c.id_carpeta
        ORDER BY t.fecha_creacion DESC
        LIMIT 5
    ");
    $ultimasTablas = $consulta->fetchAll();
} catch (Exception $e) {
    // Sin datos
}

// Incluye header
include __DIR__ . '/../layouts/header.php';

// Incluye sidebar
include __DIR__ . '/../layouts/sidebar-jefa.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">Panel de control - Jefa de Servicios Escolares</p>
    </div>
</div>

<?php // Tarjetas de estadisticas ?>
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 24px;">
    
    <div class="stat-card">
        <div class="stat-icon" style="background: #DBEAFE;">
            <i data-lucide="folders" style="color: #2563EB;"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Carpetas</div>
            <div class="stat-value"><?= $estadisticas['carpetas'] ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: #FEF3C7;">
            <i data-lucide="file-edit" style="color: #D97706;"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Borradores</div>
            <div class="stat-value"><?= $estadisticas['borradores'] ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: #FCE7F3;">
            <i data-lucide="clock" style="color: #DB2777;"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Por Validar</div>
            <div class="stat-value"><?= $estadisticas['por_validar'] ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: #D1FAE5;">
            <i data-lucide="check-circle" style="color: #059669;"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Enviados</div>
            <div class="stat-value"><?= $estadisticas['enviados'] ?></div>
        </div>
    </div>
    
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
    
    <?php // Ultimas tablas ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i data-lucide="clock" style="width: 18px; height: 18px; margin-right: 8px;"></i>
                Últimas Tablas
            </h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (empty($ultimasTablas)): ?>
            <div class="empty-state" style="padding: 40px;">
                <div class="empty-state-icon"><i data-lucide="folder-open"></i></div>
                <div class="empty-state-title">Sin tablas aún</div>
                <div class="empty-state-text">Crea tu primera carpeta para empezar</div>
            </div>
            <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Tipo</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ultimasTablas as $tabla): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($tabla['nombre']) ?></strong>
                            <br>
                            <small class="text-muted"><?= htmlspecialchars($tabla['carpeta_nombre']) ?> › <?= htmlspecialchars($tabla['subcarpeta_nombre']) ?></small>
                        </td>
                        <td>
                            <?php if ($tabla['tipo'] === 'alta'): ?>
                            <span class="badge badge-success">Alta</span>
                            <?php else: ?>
                            <span class="badge badge-danger">Baja</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $estadoClase = [
                                'borrador' => 'badge-warning',
                                'validado' => 'badge-info',
                                'enviado' => 'badge-success'
                            ];
                            $estadoTexto = [
                                'borrador' => '📝 Borrador',
                                'validado' => '🟢 Validado',
                                'enviado' => '🚀 Enviado'
                            ];
                            ?>
                            <span class="badge <?= $estadoClase[$tabla['estado']] ?? 'badge-secondary' ?>">
                                <?= $estadoTexto[$tabla['estado']] ?? $tabla['estado'] ?>
                            </span>
                        </td>
                        <td><?= date('d/m/Y', strtotime($tabla['fecha_creacion'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php if (!empty($ultimasTablas)): ?>
        <div class="card-footer">
            <a href="<?= URL_BASE ?>views/jefa/carpetas.php" class="btn btn-ghost btn-sm">
                Ver todas las carpetas →
            </a>
        </div>
        <?php endif; ?>
    </div>
    
    <?php // Acciones rapidas y stats del mes ?>
    <div>
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-header">
                <h3 class="card-title">
                    <i data-lucide="zap" style="width: 18px; height: 18px; margin-right: 8px;"></i>
                    Acciones Rápidas
                </h3>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <a href="<?= URL_BASE ?>views/jefa/carpetas.php?nueva=1" class="btn btn-primary" style="justify-content: flex-start;">
                        <i data-lucide="folder-plus"></i>
                        Nueva Carpeta
                    </a>
                    <a href="<?= URL_BASE ?>views/jefa/importar.php" class="btn btn-outline" style="justify-content: flex-start;">
                        <i data-lucide="file-spreadsheet"></i>
                        Importar Excel
                    </a>
                    <a href="<?= URL_BASE ?>views/jefa/carpetas.php" class="btn btn-outline" style="justify-content: flex-start;">
                        <i data-lucide="folders"></i>
                        Ver Carpetas
                    </a>
                    <?php if ($estadisticas['por_validar'] > 0): ?>
                    <a href="<?= URL_BASE ?>views/jefa/validar.php" class="btn btn-outline" style="justify-content: flex-start; border-color: #DB2777; color: #DB2777;">
                        <i data-lucide="check-circle"></i>
                        Validar Pendientes (<?= $estadisticas['por_validar'] ?>)
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i data-lucide="bar-chart-3" style="width: 18px; height: 18px; margin-right: 8px;"></i>
                    Este Mes
                </h3>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="display: flex; align-items: center; gap: 8px;">
                            <span style="width: 12px; height: 12px; background: #22C55E; border-radius: 50%;"></span>
                            Altas
                        </span>
                        <strong style="font-size: 20px;"><?= $estadisticas['altas_mes'] ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="display: flex; align-items: center; gap: 8px;">
                            <span style="width: 12px; height: 12px; background: #EF4444; border-radius: 50%;"></span>
                            Bajas
                        </span>
                        <strong style="font-size: 20px;"><?= $estadisticas['bajas_mes'] ?></strong>
                    </div>
                    <hr style="margin: 8px 0; border-color: var(--border-color);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span>Total movimientos</span>
                        <strong style="font-size: 20px;"><?= $estadisticas['altas_mes'] + $estadisticas['bajas_mes'] ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
</div>

<?php // Incluye footer ?>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
