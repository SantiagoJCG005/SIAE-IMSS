<?php
/**
 * SIAE-IMSS - Notificaciones (Jefa de Servicios)
 * Vista completa del historial de notificaciones
 */

$tituloPagina = 'Notificaciones';
$currentPage = 'notificaciones';

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requerirLogin();
requerirRol(ROL_JEFA_SERVICIOS);

$conexion = obtenerConexion();
$idUsuario = obtenerIdUsuarioActual();

// Filtros
$filtroTipo = $_GET['tipo'] ?? '';
$filtroEstado = $_GET['estado'] ?? '';

// Obtener notificaciones
try {
    $sql = "
        SELECT 
            n.*,
            u.nombre_completo as nombre_origen,
            u.username as username_origen
        FROM notificaciones n
        LEFT JOIN usuarios u ON n.id_usuario_origen = u.id_usuario
        WHERE n.id_usuario_destino = ?
    ";
    $params = [$idUsuario];
    
    if ($filtroTipo) {
        $sql .= " AND n.tipo = ?";
        $params[] = $filtroTipo;
    }
    
    if ($filtroEstado === 'no_leidas') {
        $sql .= " AND n.leida = 0";
    } elseif ($filtroEstado === 'leidas') {
        $sql .= " AND n.leida = 1";
    }
    
    $sql .= " ORDER BY n.fecha_creacion DESC LIMIT 100";
    
    $consulta = $conexion->prepare($sql);
    $consulta->execute($params);
    $notificaciones = $consulta->fetchAll();
    
    // Contar por estado
    $consulta = $conexion->prepare("SELECT COUNT(*) as total FROM notificaciones WHERE id_usuario_destino = ? AND leida = 0");
    $consulta->execute([$idUsuario]);
    $totalNoLeidas = $consulta->fetch()['total'];
    
} catch (Exception $e) {
    $notificaciones = [];
    $totalNoLeidas = 0;
}

include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar-jefa.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Notificaciones</h1>
        <p class="page-subtitle"><?= $totalNoLeidas ?> notificaciones sin leer</p>
    </div>
    <div class="page-actions">
        <?php if ($totalNoLeidas > 0): ?>
        <button onclick="marcarTodasLeidasYRecargar()" class="btn btn-secondary">
            <i data-lucide="check-check" style="width:18px;height:18px;"></i>
            Marcar todas como leídas
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Filtros -->
<div class="filtros-bar" style="margin-bottom: 24px; display: flex; gap: 12px; flex-wrap: wrap;">
    <select onchange="aplicarFiltros()" id="filtroTipo" class="form-select" style="width: auto;">
        <option value="">Todos los tipos</option>
        <option value="exportacion_txt" <?= $filtroTipo === 'exportacion_txt' ? 'selected' : '' ?>>📤 Exportaciones</option>
        <option value="alerta_problema" <?= $filtroTipo === 'alerta_problema' ? 'selected' : '' ?>>⚠️ Alertas</option>
    </select>
    
    <select onchange="aplicarFiltros()" id="filtroEstado" class="form-select" style="width: auto;">
        <option value="">Todos</option>
        <option value="no_leidas" <?= $filtroEstado === 'no_leidas' ? 'selected' : '' ?>>No leídas</option>
        <option value="leidas" <?= $filtroEstado === 'leidas' ? 'selected' : '' ?>>Leídas</option>
    </select>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <?php if (count($notificaciones) > 0): ?>
        <div class="notif-lista-completa">
            <?php foreach ($notificaciones as $notif): ?>
            <?php
                $icono = $notif['tipo'] === 'exportacion_txt' ? '📤' : '⚠️';
                $iconoClass = $notif['tipo'] === 'exportacion_txt' ? 'exportacion' : 'alerta';
                $noLeida = !$notif['leida'];
                
                // Parsear datos extra
                $datosExtra = $notif['datos_extra'] ? json_decode($notif['datos_extra'], true) : [];
            ?>
            <div class="notif-item-full <?= $noLeida ? 'no-leida' : '' ?>" data-id="<?= $notif['id_notificacion'] ?>">
                <div class="notif-icon-full <?= $iconoClass ?>"><?= $icono ?></div>
                
                <div class="notif-content-full">
                    <div class="notif-header-full">
                        <span class="notif-titulo-full"><?= htmlspecialchars($notif['titulo']) ?></span>
                        <span class="notif-tiempo-full"><?= date('d/m/Y H:i', strtotime($notif['fecha_creacion'])) ?></span>
                    </div>
                    
                    <?php if ($notif['nombre_origen']): ?>
                    <div class="notif-origen">
                        Por: <strong><?= htmlspecialchars($notif['nombre_origen']) ?></strong>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($datosExtra)): ?>
                    <div class="notif-detalles">
                        <?php if (isset($datosExtra['archivo'])): ?>
                            <span>📄 <?= htmlspecialchars($datosExtra['archivo']) ?></span>
                        <?php endif; ?>
                        <?php if (isset($datosExtra['registros'])): ?>
                            <span>👥 <?= $datosExtra['registros'] ?> registros</span>
                        <?php endif; ?>
                        <?php if (isset($datosExtra['tipo'])): ?>
                            <span><?= $datosExtra['tipo'] === 'alta' ? '📈 Alta' : '📉 Baja' ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="notif-acciones-full">
                        <?php if ($notif['tipo'] === 'exportacion_txt'): ?>
                            <?php if ($notif['estado'] === 'nueva' || $notif['estado'] === 'vista'): ?>
                            <button onclick="cambiarEstadoNotif(<?= $notif['id_notificacion'] ?>, 'revisada')" class="btn btn-sm btn-success">
                                ✓ Marcar OK
                            </button>
                            <button onclick="cambiarEstadoNotif(<?= $notif['id_notificacion'] ?>, 'problema')" class="btn btn-sm btn-danger">
                                ⚠️ Reportar problema
                            </button>
                            <?php elseif ($notif['estado'] === 'revisada'): ?>
                            <span class="estado-tag revisada">✓ Revisada</span>
                            <?php elseif ($notif['estado'] === 'problema'): ?>
                            <span class="estado-tag problema">⚠️ Problema reportado</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php if ($noLeida): ?>
                        <button onclick="marcarLeidaNotif(<?= $notif['id_notificacion'] ?>)" class="btn btn-sm btn-ghost">
                            Marcar leída
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div style="text-align: center; padding: 80px 20px; color: #64748B;">
            <i data-lucide="bell-off" style="width: 64px; height: 64px; margin-bottom: 16px; opacity: 0.3;"></i>
            <h3 style="margin-bottom: 8px; color: #1E293B;">No hay notificaciones</h3>
            <p>No tienes notificaciones que coincidan con los filtros seleccionados.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.notif-lista-completa { }
.notif-item-full {
    display: flex;
    gap: 16px;
    padding: 20px 24px;
    border-bottom: 1px solid #E2E8F0;
    transition: background 0.15s;
}
.notif-item-full:hover { background: #F8FAFC; }
.notif-item-full.no-leida { background: #EFF6FF; }
.notif-item-full.no-leida:hover { background: #DBEAFE; }

.notif-icon-full {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}
.notif-icon-full.exportacion { background: #FEF3C7; }
.notif-icon-full.alerta { background: #FEE2E2; }

.notif-content-full { flex: 1; }

.notif-header-full {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 4px;
}
.notif-titulo-full {
    font-weight: 600;
    color: #1E293B;
}
.notif-tiempo-full {
    font-size: 12px;
    color: #94A3B8;
    white-space: nowrap;
}

.notif-origen {
    font-size: 13px;
    color: #64748B;
    margin-bottom: 8px;
}

.notif-detalles {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    font-size: 13px;
    color: #64748B;
    margin-bottom: 12px;
}

.notif-acciones-full {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn-success { background: #22C55E; color: white; }
.btn-success:hover { background: #16A34A; }
.btn-danger { background: #EF4444; color: white; }
.btn-danger:hover { background: #DC2626; }
.btn-ghost { background: transparent; color: #64748B; }
.btn-ghost:hover { background: #F1F5F9; }

.estado-tag {
    display: inline-flex;
    align-items: center;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}
.estado-tag.revisada { background: #DCFCE7; color: #166534; }
.estado-tag.problema { background: #FEE2E2; color: #991B1B; }

.form-select {
    padding: 8px 12px;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    font-size: 14px;
    background: white;
}
</style>

<script>
const API_NOTIF = '<?= URL_BASE ?>api/notificaciones.php';

function aplicarFiltros() {
    const tipo = document.getElementById('filtroTipo').value;
    const estado = document.getElementById('filtroEstado').value;
    
    let url = '<?= URL_BASE ?>views/jefa/notificaciones.php?';
    if (tipo) url += 'tipo=' + tipo + '&';
    if (estado) url += 'estado=' + estado;
    
    window.location.href = url;
}

async function marcarLeidaNotif(id) {
    await fetch(API_NOTIF + '?action=marcar_leida&id=' + id);
    document.querySelector(`[data-id="${id}"]`).classList.remove('no-leida');
    location.reload();
}

async function marcarTodasLeidasYRecargar() {
    await fetch(API_NOTIF + '?action=marcar_todas_leidas');
    location.reload();
}

async function cambiarEstadoNotif(id, estado) {
    if (estado === 'problema') {
        const confirmado = await Swal.fire({
            title: '¿Reportar problema?',
            text: 'Se notificará al usuario que realizó la exportación para que revise los datos.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#EF4444',
            cancelButtonColor: '#64748B',
            confirmButtonText: 'Sí, reportar',
            cancelButtonText: 'Cancelar'
        });
        
        if (!confirmado.isConfirmed) return;
    }
    
    await fetch(API_NOTIF + '?action=cambiar_estado&id=' + id + '&estado=' + estado);
    
    if (estado === 'problema') {
        mostrarNotificacion('Se ha notificado al usuario sobre el problema', 'warning');
    } else {
        mostrarNotificacion('Marcada como revisada', 'success');
    }
    
    setTimeout(() => location.reload(), 1000);
}
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
