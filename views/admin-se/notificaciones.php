<?php
/**
 * SIAE-IMSS - Notificaciones (Admin Servicios Escolares)
 * Vista completa del historial de notificaciones
 */

$tituloPagina = 'Notificaciones';
$currentPage = 'notificaciones';

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requerirLogin();
requerirRol(ROL_ADMIN_SERVICIOS);

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
    
    // Total general
    $consulta = $conexion->prepare("SELECT COUNT(*) as total FROM notificaciones WHERE id_usuario_destino = ?");
    $consulta->execute([$idUsuario]);
    $totalGeneral = $consulta->fetch()['total'];
    
} catch (Exception $e) {
    $notificaciones = [];
    $totalNoLeidas = 0;
    $totalGeneral = 0;
}

include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar-admin-se.php';
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start;">
    <div>
        <h1 class="page-title">Notificaciones</h1>
        <p class="page-subtitle"><?= $totalGeneral ?> total &middot; <?= $totalNoLeidas ?> sin leer</p>
    </div>
    <div style="display: flex; gap: 8px;">
        <?php if ($totalNoLeidas > 0): ?>
        <button onclick="marcarTodasLeidasYRecargar()" class="btn btn-ghost btn-sm">
            <i data-lucide="check-check" style="width:16px;height:16px;"></i>
            Marcar leidas
        </button>
        <?php endif; ?>
        <?php if (count($notificaciones) > 0): ?>
        <button onclick="limpiarLeidasYRecargar()" class="btn btn-ghost btn-sm" style="color: var(--danger);">
            <i data-lucide="trash-2" style="width:16px;height:16px;"></i>
            Limpiar leidas
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Filtros -->
<div style="display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap;">
    <a href="<?= URL_BASE ?>views/admin-se/notificaciones.php" 
       class="nf-filtro-chip <?= !$filtroEstado && !$filtroTipo ? 'activo' : '' ?>">
        Todas
    </a>
    <a href="<?= URL_BASE ?>views/admin-se/notificaciones.php?estado=no_leidas" 
       class="nf-filtro-chip <?= $filtroEstado === 'no_leidas' ? 'activo' : '' ?>">
        Sin leer
    </a>
    <a href="<?= URL_BASE ?>views/admin-se/notificaciones.php?tipo=alerta_problema"
       class="nf-filtro-chip <?= $filtroTipo === 'alerta_problema' ? 'activo' : '' ?>">
        Alertas
    </a>
    <a href="<?= URL_BASE ?>views/admin-se/notificaciones.php?tipo=validacion_aprobada"
       class="nf-filtro-chip <?= $filtroTipo === 'validacion_aprobada' ? 'activo' : '' ?>">
        Aprobadas
    </a>
    <a href="<?= URL_BASE ?>views/admin-se/notificaciones.php?tipo=validacion_rechazada"
       class="nf-filtro-chip <?= $filtroTipo === 'validacion_rechazada' ? 'activo' : '' ?>">
        Rechazadas
    </a>
    <a href="<?= URL_BASE ?>views/admin-se/notificaciones.php?estado=leidas" 
       class="nf-filtro-chip <?= $filtroEstado === 'leidas' ? 'activo' : '' ?>">
        Leidas
    </a>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <?php if (count($notificaciones) > 0): ?>
        <div class="nf-lista">
            <?php foreach ($notificaciones as $notif): ?>
            <?php
                $noLeida = !$notif['leida'];
                $datosExtra = $notif['datos_extra'] ? json_decode($notif['datos_extra'], true) : [];
                
                // Determinar tipo visual
                $tipoClase = 'tipo-info';
                $tipoIcono = 'info';
                if ($notif['tipo'] === 'exportacion_txt') {
                    $tipoClase = 'tipo-exportacion';
                    $tipoIcono = 'file-output';
                } elseif ($notif['tipo'] === 'alerta_problema') {
                    $tipoClase = 'tipo-alerta';
                    $tipoIcono = 'alert-triangle';
                } elseif ($notif['tipo'] === 'validacion_aprobada') {
                    $tipoClase = 'tipo-aprobado';
                    $tipoIcono = 'check-circle';
                } elseif ($notif['tipo'] === 'validacion_rechazada') {
                    $tipoClase = 'tipo-rechazado';
                    $tipoIcono = 'x-circle';
                }
            ?>
            <div class="nf-row <?= $noLeida ? 'no-leida' : '' ?>" id="nf-<?= $notif['id_notificacion'] ?>">
                <div class="nf-indicador <?= $tipoClase ?>">
                    <i data-lucide="<?= $tipoIcono ?>" style="width:18px;height:18px;"></i>
                </div>
                
                <div class="nf-cuerpo">
                    <div class="nf-cabecera">
                        <span class="nf-titulo"><?= htmlspecialchars($notif['titulo']) ?></span>
                        <span class="nf-fecha"><?= date('d/m/Y H:i', strtotime($notif['fecha_creacion'])) ?></span>
                    </div>
                    
                    <?php if ($notif['nombre_origen']): ?>
                    <div class="nf-origen">
                        <i data-lucide="user" style="width:12px;height:12px;"></i>
                        <?= htmlspecialchars($notif['nombre_origen']) ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($notif['mensaje']): ?>
                    <div class="nf-mensaje"><?= nl2br(htmlspecialchars($notif['mensaje'])) ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($datosExtra)): ?>
                    <div class="nf-extras">
                        <?php if (isset($datosExtra['archivo'])): ?>
                            <span class="nf-tag">
                                <i data-lucide="file-text" style="width:11px;height:11px;"></i>
                                <?= htmlspecialchars($datosExtra['archivo']) ?>
                            </span>
                        <?php endif; ?>
                        <?php if (isset($datosExtra['registros'])): ?>
                            <span class="nf-tag">
                                <i data-lucide="users" style="width:11px;height:11px;"></i>
                                <?= $datosExtra['registros'] ?> registros
                            </span>
                        <?php endif; ?>
                        <?php if (isset($datosExtra['tipo'])): ?>
                            <span class="nf-tag <?= $datosExtra['tipo'] === 'alta' ? 'tag-alta' : 'tag-baja' ?>">
                                <?= $datosExtra['tipo'] === 'alta' ? 'Alta' : 'Baja' ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="nf-barra-acciones">
                        <?php if ($notif['estado'] === 'revisada'): ?>
                        <span class="nf-estado-label aprobado">
                            <i data-lucide="check-circle" style="width:13px;height:13px;"></i> Revisada
                        </span>
                        <?php elseif ($notif['estado'] === 'problema'): ?>
                        <span class="nf-estado-label rechazado">
                            <i data-lucide="alert-circle" style="width:13px;height:13px;"></i> Problema
                        </span>
                        <?php endif; ?>
                        
                        <?php if ($noLeida): ?>
                        <button onclick="marcarLeidaNotif(<?= $notif['id_notificacion'] ?>)" class="nf-btn-accion leer">
                            Marcar leida
                        </button>
                        <?php endif; ?>
                        
                        <button onclick="eliminarNotifCompleta(<?= $notif['id_notificacion'] ?>)" class="nf-btn-accion eliminar" title="Eliminar">
                            <i data-lucide="trash-2" style="width:13px;height:13px;"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div style="text-align: center; padding: 60px 20px;">
            <i data-lucide="bell-off" style="width: 48px; height: 48px; color: var(--text-muted); opacity: 0.3; margin-bottom: 16px;"></i>
            <h3 style="margin-bottom: 6px; color: var(--text-primary); font-size: 16px;">Sin notificaciones</h3>
            <p style="color: var(--text-muted); font-size: 13px;">No hay notificaciones con los filtros seleccionados.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Filtros tipo chip */
.nf-filtro-chip {
    display: inline-flex;
    align-items: center;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 500;
    color: var(--text-secondary);
    background: var(--bg-white);
    border: 1px solid var(--border-color);
    transition: var(--transition);
    text-decoration: none;
}
.nf-filtro-chip:hover {
    border-color: var(--secondary);
    color: var(--secondary);
}
.nf-filtro-chip.activo {
    background: var(--secondary);
    color: white;
    border-color: var(--secondary);
}

/* Lista de notificaciones */
.nf-row {
    display: flex;
    gap: 14px;
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-color);
    transition: background 0.15s;
}
.nf-row:last-child { border-bottom: none; }
.nf-row:hover { background: var(--bg-secondary); }
.nf-row.no-leida {
    background: #f0f5ff;
    border-left: 3px solid var(--secondary);
}
.nf-row.no-leida:hover { background: #e8effc; }

/* Indicador de tipo */
.nf-indicador {
    width: 40px;
    height: 40px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.nf-indicador.tipo-exportacion { background: var(--warning-bg); color: var(--warning-text); }
.nf-indicador.tipo-alerta { background: var(--danger-bg); color: var(--danger-text); }
.nf-indicador.tipo-info { background: var(--info-bg); color: var(--info-text); }
.nf-indicador.tipo-aprobado { background: var(--success-bg); color: var(--success-text); }
.nf-indicador.tipo-rechazado { background: var(--danger-bg); color: var(--danger-text); }

/* Cuerpo */
.nf-cuerpo { flex: 1; min-width: 0; }

.nf-cabecera {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 3px;
}
.nf-titulo {
    font-weight: 500;
    font-size: 14px;
    color: var(--text-primary);
    line-height: 1.4;
}
.nf-fecha {
    font-size: 12px;
    color: var(--text-muted);
    white-space: nowrap;
    flex-shrink: 0;
}

.nf-origen {
    font-size: 12px;
    color: var(--text-secondary);
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 4px;
}
.nf-mensaje {
    font-size: 13px;
    color: var(--text-secondary);
    margin-bottom: 8px;
    line-height: 1.5;
}

/* Tags de datos extra */
.nf-extras {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 8px;
}
.nf-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: var(--radius-sm);
    font-size: 11px;
    color: var(--text-secondary);
    background: var(--bg-secondary);
}
.nf-tag.tag-alta { background: var(--success-bg); color: var(--success-text); }
.nf-tag.tag-baja { background: var(--danger-bg); color: var(--danger-text); }

/* Barra de acciones */
.nf-barra-acciones {
    display: flex;
    gap: 6px;
    align-items: center;
    flex-wrap: wrap;
}

.nf-btn-accion {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: var(--radius-sm);
    font-size: 12px;
    font-weight: 500;
    border: none;
    cursor: pointer;
    transition: var(--transition);
}
.nf-btn-accion.leer {
    background: transparent;
    color: var(--text-muted);
}
.nf-btn-accion.leer:hover { background: var(--bg-secondary); color: var(--text-secondary); }
.nf-btn-accion.eliminar {
    background: transparent;
    color: var(--text-muted);
    margin-left: auto;
}
.nf-btn-accion.eliminar:hover { background: var(--danger-bg); color: var(--danger); }

/* Labels de estado */
.nf-estado-label {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
}
.nf-estado-label.aprobado { background: var(--success-bg); color: var(--success-text); }
.nf-estado-label.rechazado { background: var(--danger-bg); color: var(--danger-text); }
</style>

<script>
const API_NF = '<?= URL_BASE ?>api/notificaciones.php';

async function marcarLeidaNotif(id) {
    await fetch(API_NF + '?action=marcar_leida&id=' + id);
    const row = document.getElementById('nf-' + id);
    if (row) row.classList.remove('no-leida');
    location.reload();
}

async function marcarTodasLeidasYRecargar() {
    await fetch(API_NF + '?action=marcar_todas_leidas');
    location.reload();
}

async function eliminarNotifCompleta(id) {
    const row = document.getElementById('nf-' + id);
    if (row) {
        row.style.opacity = '0.4';
        row.style.transition = 'opacity 0.2s';
    }
    await fetch(API_NF + '?action=eliminar&id=' + id);
    if (row) row.remove();
    mostrarNotificacion('Notificacion eliminada', 'success');
}

async function limpiarLeidasYRecargar() {
    const confirmado = await Swal.fire({
        title: 'Limpiar notificaciones leidas',
        text: 'Se eliminaran todas las notificaciones que ya hayas leido.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#2563EB',
        cancelButtonColor: '#64748B',
        confirmButtonText: 'Si, limpiar',
        cancelButtonText: 'Cancelar'
    });
    if (!confirmado.isConfirmed) return;
    
    await fetch(API_NF + '?action=eliminar_leidas');
    mostrarNotificacion('Notificaciones leidas eliminadas', 'success');
    setTimeout(() => location.reload(), 800);
}
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
