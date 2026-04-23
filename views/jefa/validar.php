<?php
/**
 * SI FUNCIONA NO LE MUEVAS!!!!!
 * SIAE-IMSS - Validar Movimientos
 * Permite a la Jefa validar tablas creadas por empleados
 */

// Titulo de la pagina
$tituloPagina = 'Validar';

// Carga archivos necesarios
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Verifica permisos - Solo Jefa puede validar
requerirRol([ROL_JEFA_SERVICIOS, ROL_SUPERADMIN]);

// Incluye header
include __DIR__ . '/../layouts/header.php';

// Incluye sidebar
include __DIR__ . '/../layouts/sidebar-jefa.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Validar Movimientos</h1>
        <p class="page-subtitle">Revisa y aprueba las tablas creadas por tus empleados</p>
    </div>
</div>

<?php // Contenedor de tablas pendientes ?>
<div id="contenedorPendientes">
    <div class="loading-state" style="text-align: center; padding: 60px;">
        <i data-lucide="loader-2" class="spin" style="width: 40px; height: 40px; color: var(--primary);"></i>
        <p style="margin-top: 16px; color: var(--text-secondary);">Cargando tablas pendientes...</p>
    </div>
</div>

<?php // Modal para ver detalles ?>
<div class="modal-overlay" id="modalDetalle">
    <div class="modal" style="max-width: 800px;">
        <div class="modal-header">
            <h3 class="modal-title" id="modalDetalleTitulo">Detalle de la Tabla</h3>
            <button class="modal-close" onclick="cerrarModalDetalle()">
                <i data-lucide="x"></i>
            </button>
        </div>
        <div class="modal-body" id="modalDetalleContenido">
            <div style="text-align: center; padding: 40px;">
                <i data-lucide="loader-2" class="spin"></i>
                Cargando...
            </div>
        </div>
        <div class="modal-footer" id="modalDetalleFooter">
        </div>
    </div>
</div>

<?php // Modal para rechazar ?>
<div class="modal-overlay" id="modalRechazar">
    <div class="modal" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Rechazar Tabla</h3>
            <button class="modal-close" onclick="cerrarModalRechazar()">
                <i data-lucide="x"></i>
            </button>
        </div>
        <form id="formRechazar" onsubmit="confirmarRechazo(event)">
            <div class="modal-body">
                <input type="hidden" name="id_tabla" id="rechazarIdTabla">
                
                <p>Se rechazará y eliminará la tabla <strong id="rechazarNombreTabla"></strong>.</p>
                
                <div class="form-group" style="margin-top: 16px;">
                    <label class="form-label">Motivo del rechazo (opcional)</label>
                    <textarea name="motivo" id="rechazarMotivo" class="form-control" rows="3" 
                              placeholder="Indica el motivo del rechazo..."></textarea>
                </div>
                
                <div class="alert alert-danger" style="margin-top: 16px; display: flex; align-items: center; gap: 12px; padding: 12px; background: #FEF2F2; border: 1px solid #FECACA; border-radius: 8px;">
                    <i data-lucide="alert-triangle" style="color: #DC2626;"></i>
                    <span style="color: #991B1B;">Esta acción no se puede deshacer.</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="cerrarModalRechazar()">Cancelar</button>
                <button type="submit" class="btn btn-danger">
                    <i data-lucide="x-circle"></i>
                    Rechazar y Eliminar
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.tabla-pendiente {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 16px;
}

.tabla-pendiente-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}

.tabla-pendiente-info h4 {
    margin: 0 0 4px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.tabla-pendiente-meta {
    display: flex;
    gap: 16px;
    color: var(--text-secondary);
    font-size: 14px;
}

.tabla-pendiente-acciones {
    display: flex;
    gap: 8px;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: var(--bg-secondary);
    border-radius: 12px;
}

.empty-state i {
    width: 64px;
    height: 64px;
    color: var(--text-muted);
    margin-bottom: 16px;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.spin {
    animation: spin 1s linear infinite;
}
</style>

<script>
const API_VALIDAR = '<?= URL_BASE ?>api/validar.php';
const API_TABLAS = '<?= URL_BASE ?>api/tablas.php';

// Carga las tablas pendientes al iniciar
document.addEventListener('DOMContentLoaded', function() {
    cargarPendientes();
});

// Carga tablas pendientes de validacion
async function cargarPendientes() {
    const contenedor = document.getElementById('contenedorPendientes');
    
    const respuesta = await llamarApi(API_VALIDAR + '?action=listar_pendientes');
    
    if (!respuesta || !respuesta.success) {
        contenedor.innerHTML = `
            <div class="empty-state">
                <i data-lucide="alert-circle"></i>
                <h3>Error al cargar</h3>
                <p>No se pudieron cargar las tablas pendientes</p>
                <button class="btn btn-primary" onclick="cargarPendientes()">Reintentar</button>
            </div>
        `;
        lucide.createIcons();
        return;
    }
    
    const tablas = respuesta.data;
    
    if (tablas.length === 0) {
        contenedor.innerHTML = `
            <div class="empty-state">
                <i data-lucide="check-circle" style="color: #22C55E;"></i>
                <h3>Todo al día</h3>
                <p>No hay tablas pendientes de validación</p>
            </div>
        `;
        lucide.createIcons();
        return;
    }
    
    // Genera HTML
    let html = `<h4 style="margin-bottom: 16px;">⏳ Pendientes de validación (${tablas.length})</h4>`;
    
    tablas.forEach(tabla => {
        const esAlta = tabla.tipo === 'alta';
        const tieneErrores = tabla.alumnos_con_errores > 0;
        
        html += `
            <div class="tabla-pendiente">
                <div class="tabla-pendiente-header">
                    <div class="tabla-pendiente-info">
                        <h4>
                            <i data-lucide="${esAlta ? 'trending-up' : 'trending-down'}" style="color: ${esAlta ? '#22C55E' : '#EF4444'};"></i>
                            ${escapeHtml(tabla.nombre)}
                        </h4>
                        <div style="color: var(--text-secondary); font-size: 14px;">
                            📍 ${escapeHtml(tabla.carpeta_nombre)} › ${escapeHtml(tabla.subcarpeta_nombre)}
                        </div>
                    </div>
                    <span class="badge ${esAlta ? 'badge-success' : 'badge-danger'}">${esAlta ? 'Alta' : 'Baja'}</span>
                </div>
                
                <div class="tabla-pendiente-meta">
                    <span><i data-lucide="user" style="width: 14px; height: 14px;"></i> ${escapeHtml(tabla.creador)}</span>
                    <span><i data-lucide="calendar" style="width: 14px; height: 14px;"></i> ${formatearFecha(tabla.fecha_creacion)}</span>
                    <span><i data-lucide="users" style="width: 14px; height: 14px;"></i> ${tabla.total_alumnos} alumno(s)</span>
                    ${tieneErrores ? `<span style="color: #EF4444;"><i data-lucide="alert-triangle" style="width: 14px; height: 14px;"></i> ${tabla.alumnos_con_errores} con errores</span>` : ''}
                </div>
                
                <div class="tabla-pendiente-acciones" style="margin-top: 16px;">
                    <button class="btn btn-ghost" onclick="verDetalle(${tabla.id_tabla})">
                        <i data-lucide="eye"></i>
                        Ver detalle
                    </button>
                    <button class="btn btn-success" onclick="aprobarTabla(${tabla.id_tabla}, '${escapeHtml(tabla.nombre)}')" ${tieneErrores ? 'disabled title="Corrige los errores primero"' : ''}>
                        <i data-lucide="check"></i>
                        Aprobar
                    </button>
                    <button class="btn btn-danger btn-ghost" onclick="abrirModalRechazar(${tabla.id_tabla}, '${escapeHtml(tabla.nombre)}')">
                        <i data-lucide="x"></i>
                        Rechazar
                    </button>
                </div>
            </div>
        `;
    });
    
    contenedor.innerHTML = html;
    lucide.createIcons();
}

// Ver detalle de una tabla
async function verDetalle(idTabla) {
    document.getElementById('modalDetalle').classList.add('active');
    document.getElementById('modalDetalleContenido').innerHTML = `
        <div style="text-align: center; padding: 40px;">
            <i data-lucide="loader-2" class="spin"></i>
            Cargando...
        </div>
    `;
    lucide.createIcons();
    
    const respuesta = await llamarApi(API_TABLAS + '?action=obtener&id_tabla=' + idTabla);
    
    if (!respuesta || !respuesta.success) {
        document.getElementById('modalDetalleContenido').innerHTML = `
            <div style="text-align: center; padding: 40px; color: var(--danger);">
                Error al cargar los detalles
            </div>
        `;
        return;
    }
    
    const tabla = respuesta.data;
    const alumnos = tabla.alumnos || [];
    const esAlta = tabla.tipo === 'alta';
    
    document.getElementById('modalDetalleTitulo').textContent = tabla.nombre;
    
    let html = `
        <div style="margin-bottom: 20px;">
            <p><strong>Tipo:</strong> ${esAlta ? '▲ Alta' : '▼ Baja'}</p>
            <p><strong>Fecha:</strong> ${formatearFecha(tabla.fecha_movimiento)}</p>
            <p><strong>Creado por:</strong> ${escapeHtml(tabla.creador)}</p>
        </div>
        
        <div class="table-container" style="max-height: 400px; overflow-y: auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>NSS</th>
                        <th>Nombre Completo</th>
                        <th>CURP</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    alumnos.forEach((alumno, index) => {
        const tieneErrores = alumno.tiene_errores == 1;
        html += `
            <tr class="${tieneErrores ? 'fila-error' : ''}">
                <td>${index + 1}</td>
                <td>${alumno.numero_afiliacion}-${alumno.digito_verificador}</td>
                <td>${escapeHtml(alumno.apellido_paterno)} ${escapeHtml(alumno.apellido_materno)} ${escapeHtml(alumno.nombres)}</td>
                <td>${alumno.curp || '---'}</td>
                <td>
                    ${tieneErrores ? 
                        `<span class="badge badge-danger">⚠️ ${escapeHtml(alumno.errores_detalle)}</span>` : 
                        '<span class="badge badge-success">✅ OK</span>'}
                </td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    document.getElementById('modalDetalleContenido').innerHTML = html;
    
    // Footer con acciones
    const tieneErrores = alumnos.some(a => a.tiene_errores == 1);
    document.getElementById('modalDetalleFooter').innerHTML = `
        <button class="btn btn-ghost" onclick="cerrarModalDetalle()">Cerrar</button>
        <div style="display: flex; gap: 8px;">
            <button class="btn btn-danger btn-ghost" onclick="cerrarModalDetalle(); abrirModalRechazar(${tabla.id_tabla}, '${escapeHtml(tabla.nombre)}')">
                <i data-lucide="x"></i>
                Rechazar
            </button>
            <button class="btn btn-success" onclick="cerrarModalDetalle(); aprobarTabla(${tabla.id_tabla}, '${escapeHtml(tabla.nombre)}')" ${tieneErrores ? 'disabled' : ''}>
                <i data-lucide="check"></i>
                Aprobar
            </button>
        </div>
    `;
    
    lucide.createIcons();
}

function cerrarModalDetalle() {
    document.getElementById('modalDetalle').classList.remove('active');
}

// Aprobar tabla
async function aprobarTabla(idTabla, nombre) {
    const resultado = await Swal.fire({
        title: '¿Aprobar tabla?',
        html: `Se aprobará la tabla <strong>"${nombre}"</strong> y quedará lista para generar el TXT.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, aprobar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#22C55E'
    });
    
    if (resultado.isConfirmed) {
        const respuesta = await llamarApi(API_VALIDAR, {
            method: 'POST',
            body: JSON.stringify({
                action: 'aprobar',
                id_tabla: idTabla
            })
        });
        
        if (respuesta && respuesta.success) {
            mostrarNotificacion(respuesta.message);
            cargarPendientes();
        } else {
            mostrarNotificacion(respuesta?.message || 'Error al aprobar', 'error');
        }
    }
}

// Modal rechazar
function abrirModalRechazar(idTabla, nombre) {
    document.getElementById('rechazarIdTabla').value = idTabla;
    document.getElementById('rechazarNombreTabla').textContent = nombre;
    document.getElementById('rechazarMotivo').value = '';
    document.getElementById('modalRechazar').classList.add('active');
    lucide.createIcons();
}

function cerrarModalRechazar() {
    document.getElementById('modalRechazar').classList.remove('active');
}

async function confirmarRechazo(evento) {
    evento.preventDefault();
    
    const idTabla = document.getElementById('rechazarIdTabla').value;
    const motivo = document.getElementById('rechazarMotivo').value;
    
    const respuesta = await llamarApi(API_VALIDAR, {
        method: 'POST',
        body: JSON.stringify({
            action: 'rechazar',
            id_tabla: idTabla,
            motivo: motivo
        })
    });
    
    if (respuesta && respuesta.success) {
        mostrarNotificacion(respuesta.message);
        cerrarModalRechazar();
        cargarPendientes();
    } else {
        mostrarNotificacion(respuesta?.message || 'Error al rechazar', 'error');
    }
}

// Utilidades
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatearFecha(fecha) {
    if (!fecha) return '';
    const d = new Date(fecha);
    return d.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
}

// Cerrar modales con Escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        cerrarModalDetalle();
        cerrarModalRechazar();
    }
});

// Cerrar modales al hacer clic fuera
['modalDetalle', 'modalRechazar'].forEach(id => {
    document.getElementById(id).addEventListener('click', (e) => {
        if (e.target === e.currentTarget) {
            e.target.classList.remove('active');
        }
    });
});
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
