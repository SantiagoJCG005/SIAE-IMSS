<?php
/**
 * SIAE-IMSS - Historial de Exportaciones (Admin SE)
 * Muestra el historial de exportaciones y alertas
 */

$tituloPagina = 'Mi Historial';
$currentPage = 'historial';

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requerirLogin();
requerirRol(ROL_ADMIN_SERVICIOS);

$conexion = obtenerConexion();
$idUsuario = obtenerIdUsuarioActual();

// Obtener alertas (notificaciones de problemas)
try {
    $consulta = $conexion->prepare("
        SELECT n.*, u.nombre_completo as reportado_por
        FROM notificaciones n
        LEFT JOIN usuarios u ON n.id_usuario_origen = u.id_usuario
        WHERE n.id_usuario_destino = ?
        AND n.tipo = 'alerta_problema'
        ORDER BY n.fecha_creacion DESC
        LIMIT 20
    ");
    $consulta->execute([$idUsuario]);
    $alertas = $consulta->fetchAll();
} catch (Exception $e) {
    $alertas = [];
}

// Obtener historial de exportaciones
try {
    $consulta = $conexion->prepare("
        SELECT *
        FROM bitacora
        WHERE accion = 'EXPORTAR_TXT'
        AND id_usuario = ?
        ORDER BY fecha DESC
        LIMIT 50
    ");
    $consulta->execute([$idUsuario]);
    $exportaciones = $consulta->fetchAll();
} catch (Exception $e) {
    $exportaciones = [];
}

include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar-admin-se.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Mi Historial</h1>
        <p class="page-subtitle">Exportaciones realizadas y alertas recibidas</p>
    </div>
</div>

<!-- Tabs -->
<div class="tabs" style="margin-bottom: 24px;">
    <button class="tab-btn active" onclick="mostrarTab('alertas')">
        ⚠️ Alertas
        <?php 
        $alertasNoLeidas = count(array_filter($alertas, fn($a) => !$a['leida']));
        if ($alertasNoLeidas > 0): 
        ?>
        <span class="tab-badge"><?= $alertasNoLeidas ?></span>
        <?php endif; ?>
    </button>
    <button class="tab-btn" onclick="mostrarTab('exportaciones')">
        📤 Mis Exportaciones
        <span class="tab-count">(<?= count($exportaciones) ?>)</span>
    </button>
</div>

<!-- Tab: Alertas -->
<div id="tab-alertas" class="tab-content active">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Alertas de la Jefa de Servicios</h3>
        </div>
        <div class="card-body">
            <?php if (count($alertas) > 0): ?>
            <div class="alertas-lista">
                <?php foreach ($alertas as $alerta): ?>
                <div class="alerta-item <?= !$alerta['leida'] ? 'no-leida' : '' ?>">
                    <div class="alerta-icon">⚠️</div>
                    <div class="alerta-content">
                        <div class="alerta-titulo"><?= htmlspecialchars($alerta['titulo']) ?></div>
                        <div class="alerta-mensaje"><?= nl2br(htmlspecialchars($alerta['mensaje'])) ?></div>
                        <div class="alerta-meta">
                            <span>Reportado por: <?= htmlspecialchars($alerta['reportado_por'] ?? 'Jefa de Servicios') ?></span>
                            <span>•</span>
                            <span><?= date('d/m/Y H:i', strtotime($alerta['fecha_creacion'])) ?></span>
                        </div>
                        <?php if (!$alerta['leida']): ?>
                        <button onclick="marcarAlertaLeida(<?= $alerta['id_notificacion'] ?>)" class="btn btn-sm btn-secondary" style="margin-top: 8px;">
                            Marcar como leída
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div style="text-align: center; padding: 60px 20px; color: #64748B;">
                <i data-lucide="check-circle" style="width: 64px; height: 64px; margin-bottom: 16px; color: #22C55E;"></i>
                <h3 style="margin-bottom: 8px; color: #1E293B;">¡Todo en orden!</h3>
                <p>No tienes alertas pendientes de la Jefa de Servicios.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Tab: Exportaciones -->
<div id="tab-exportaciones" class="tab-content">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Historial de exportaciones</h3>
        </div>
        <div class="card-body">
            <?php if (count($exportaciones) > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Archivo</th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($exportaciones as $exp): ?>
                    <tr>
                        <td style="white-space: nowrap;">
                            <?= date('d/m/Y', strtotime($exp['fecha'])) ?><br>
                            <small style="color: #64748B;"><?= date('H:i:s', strtotime($exp['fecha'])) ?></small>
                        </td>
                        <td>
                            <?php
                            preg_match('/TXT generado: ([^\s]+)/', $exp['detalle'], $matches);
                            $archivo = $matches[1] ?? 'N/A';
                            $esAlta = strpos($archivo, 'ALTA') !== false;
                            ?>
                            <code style="background: #F1F5F9; padding: 4px 10px; border-radius: 4px; font-size: 13px;">
                                <?= htmlspecialchars($archivo) ?>
                            </code>
                            <?php if ($esAlta): ?>
                                <span class="badge-tipo alta">ALTA</span>
                            <?php else: ?>
                                <span class="badge-tipo baja">BAJA</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 13px; color: #64748B; max-width: 400px;">
                            <?= htmlspecialchars($exp['detalle']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div style="text-align: center; padding: 60px 20px; color: #64748B;">
                <i data-lucide="inbox" style="width: 64px; height: 64px; margin-bottom: 16px; opacity: 0.3;"></i>
                <h3 style="margin-bottom: 8px; color: #1E293B;">Sin exportaciones</h3>
                <p>Aún no has realizado ninguna exportación de archivos TXT.</p>
                <a href="<?= URL_BASE ?>views/admin-se/exportar.php" class="btn btn-primary" style="margin-top: 16px;">
                    Ir a Exportar
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.tabs {
    display: flex;
    gap: 8px;
    border-bottom: 2px solid #E2E8F0;
    padding-bottom: 0;
}
.tab-btn {
    padding: 12px 20px;
    background: none;
    border: none;
    font-size: 14px;
    font-weight: 500;
    color: #64748B;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.tab-btn:hover { color: #1E293B; }
.tab-btn.active {
    color: #2563EB;
    border-bottom-color: #2563EB;
}
.tab-badge {
    background: #EF4444;
    color: white;
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 10px;
}
.tab-count {
    color: #94A3B8;
    font-weight: 400;
}
.tab-content { display: none; }
.tab-content.active { display: block; }

.alertas-lista { display: flex; flex-direction: column; gap: 16px; }
.alerta-item {
    display: flex;
    gap: 16px;
    padding: 20px;
    background: #F8FAFC;
    border-radius: 12px;
    border: 1px solid #E2E8F0;
}
.alerta-item.no-leida {
    background: #FEF2F2;
    border-color: #FECACA;
}
.alerta-icon {
    font-size: 24px;
    width: 48px;
    height: 48px;
    background: #FEE2E2;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.alerta-content { flex: 1; }
.alerta-titulo {
    font-weight: 600;
    color: #1E293B;
    margin-bottom: 8px;
}
.alerta-mensaje {
    color: #475569;
    font-size: 14px;
    line-height: 1.5;
}
.alerta-meta {
    display: flex;
    gap: 8px;
    margin-top: 12px;
    font-size: 12px;
    color: #94A3B8;
}
.badge-tipo {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    margin-left: 8px;
}
.badge-tipo.alta { background: #DCFCE7; color: #166534; }
.badge-tipo.baja { background: #FEE2E2; color: #991B1B; }
</style>

<script>
function mostrarTab(tab) {
    // Ocultar todos los tabs
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    
    // Mostrar el seleccionado
    document.getElementById('tab-' + tab).classList.add('active');
    event.target.classList.add('active');
}

async function marcarAlertaLeida(id) {
    try {
        await fetch('<?= URL_BASE ?>api/notificaciones.php?action=marcar_leida&id=' + id);
        location.reload();
    } catch (e) {
        mostrarNotificacion('Error al marcar la alerta', 'error');
    }
}
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
