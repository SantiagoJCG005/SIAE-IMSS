<?php
/**
 * SI FUNCIONA NO LE MUEVAS!!!!!
 * SIAE-IMSS - Explorador de Carpetas
 * Vista principal para gestion de carpetas, subcarpetas y tablas de movimientos
 */

// Titulo de la pagina
$tituloPagina = 'Carpetas';

// Carga archivos necesarios
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Verifica permisos
requerirRol([ROL_JEFA_SERVICIOS, ROL_ADMIN_SERVICIOS, ROL_SUPERADMIN]);

// Conexion a la base de datos
$conexion = obtenerConexion();

// Verifica si debe abrir modal de nueva carpeta
$abrirModalCarpeta = obtenerGet('nueva', '') === '1';

// Incluye header
include __DIR__ . '/../layouts/header.php';

// Incluye sidebar
include __DIR__ . '/../layouts/sidebar-jefa.php';
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start;">
    <div>
        <h1 class="page-title">Gestión de Movimientos IMSS</h1>
        <p class="page-subtitle">Organiza tus altas y bajas por ciclo y carrera</p>
    </div>
    <button class="btn btn-primary" onclick="abrirModalCarpeta()">
        <i data-lucide="folder-plus"></i>
        Nueva Carpeta
    </button>
</div>

<?php // Contenedor de carpetas ?>
<div id="contenedorCarpetas">
    <div class="loading-state" style="text-align: center; padding: 60px;">
        <i data-lucide="loader-2" class="spin" style="width: 40px; height: 40px; color: var(--primary);"></i>
        <p style="margin-top: 16px; color: var(--text-secondary);">Cargando carpetas...</p>
    </div>
</div>

<?php // Modal para crear/editar carpeta ?>
<div class="modal-overlay" id="modalCarpeta">
    <div class="modal" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title" id="modalCarpetaTitulo">Nueva Carpeta</h3>
            <button class="modal-close" onclick="cerrarModalCarpeta()">
                <i data-lucide="x"></i>
            </button>
        </div>
        <form id="formCarpeta" onsubmit="guardarCarpeta(event)">
            <div class="modal-body">
                <input type="hidden" name="id_carpeta" id="carpetaId">
                
                <div class="form-group">
                    <label class="form-label">Nombre de la Carpeta *</label>
                    <input type="text" name="nombre" id="carpetaNombre" class="form-control" 
                           placeholder="Ej: Ciclo Ago-Dic 2025" maxlength="100" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Descripción (opcional)</label>
                    <textarea name="descripcion" id="carpetaDescripcion" class="form-control" 
                              placeholder="Descripción de la carpeta..." rows="2" maxlength="255"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="cerrarModalCarpeta()">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="save"></i>
                    Guardar
                </button>
            </div>
        </form>
    </div>
</div>

<?php // Modal para crear/editar subcarpeta ?>
<div class="modal-overlay" id="modalSubcarpeta">
    <div class="modal" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title" id="modalSubcarpetaTitulo">Nueva Subcarpeta</h3>
            <button class="modal-close" onclick="cerrarModalSubcarpeta()">
                <i data-lucide="x"></i>
            </button>
        </div>
        <form id="formSubcarpeta" onsubmit="guardarSubcarpeta(event)">
            <div class="modal-body">
                <input type="hidden" name="id_subcarpeta" id="subcarpetaId">
                <input type="hidden" name="id_carpeta" id="subcarpetaCarpetaId">
                
                <div class="form-group">
                    <label class="form-label">Carpeta padre</label>
                    <input type="text" id="subcarpetaCarpetaNombre" class="form-control" disabled>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Nombre de la Subcarpeta *</label>
                    <input type="text" name="nombre" id="subcarpetaNombre" class="form-control" 
                           placeholder="Ej: ISC Semestre 1" maxlength="100" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Descripción (opcional)</label>
                    <textarea name="descripcion" id="subcarpetaDescripcion" class="form-control" 
                              placeholder="Descripción de la subcarpeta..." rows="2" maxlength="255"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="cerrarModalSubcarpeta()">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="save"></i>
                    Guardar
                </button>
            </div>
        </form>
    </div>
</div>

<?php // Modal para crear tabla de movimientos ?>
<div class="modal-overlay" id="modalTabla">
    <div class="modal" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Nueva Tabla de Movimientos</h3>
            <button class="modal-close" onclick="cerrarModalTabla()">
                <i data-lucide="x"></i>
            </button>
        </div>
        <form id="formTabla" onsubmit="guardarTabla(event)">
            <div class="modal-body">
                <input type="hidden" name="id_subcarpeta" id="tablaSubcarpetaId">
                
                <div class="form-group">
                    <label class="form-label">Ubicación</label>
                    <input type="text" id="tablaUbicacion" class="form-control" disabled>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Tipo de Movimiento *</label>
                    <div style="display: flex; gap: 16px; margin-top: 8px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="tipo" value="alta" required>
                            <span style="display: flex; align-items: center; gap: 4px;">
                                <span style="color: #22C55E;">▲</span> Alta
                            </span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="tipo" value="baja">
                            <span style="display: flex; align-items: center; gap: 4px;">
                                <span style="color: #EF4444;">▼</span> Baja
                            </span>
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Fecha del Movimiento *</label>
                    <input type="date" name="fecha_movimiento" id="tablaFecha" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Nombre (opcional)</label>
                    <input type="text" name="nombre" id="tablaNombre" class="form-control" 
                           placeholder="Se genera automáticamente si lo dejas vacío" maxlength="100">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="cerrarModalTabla()">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="plus"></i>
                    Crear Tabla
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* Estilos para el explorador de carpetas */
.carpeta-item {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    margin-bottom: 16px;
    overflow: hidden;
}

.carpeta-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    background: var(--bg-secondary);
    cursor: pointer;
    transition: background 0.2s;
}

.carpeta-header:hover {
    background: #E2E8F0;
}

.carpeta-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.carpeta-icon {
    width: 40px;
    height: 40px;
    background: #DBEAFE;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #2563EB;
}

.carpeta-nombre {
    font-weight: 600;
    font-size: 16px;
}

.carpeta-meta {
    font-size: 12px;
    color: var(--text-secondary);
}

.carpeta-acciones {
    display: flex;
    gap: 8px;
}

.carpeta-contenido {
    padding: 0 20px 20px 20px;
    display: none;
}

.carpeta-contenido.activo {
    display: block;
}

.subcarpeta-item {
    margin-left: 20px;
    padding: 12px 16px;
    border-left: 2px solid var(--border-color);
    margin-top: 12px;
}

.subcarpeta-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.subcarpeta-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.subcarpeta-icon {
    color: #F97316;
}

.subcarpeta-nombre {
    font-weight: 500;
}

.tabla-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 12px;
    margin-top: 8px;
    margin-left: 20px;
    background: var(--bg-secondary);
    border-radius: 8px;
}

.tabla-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.tabla-tipo-alta {
    color: #22C55E;
}

.tabla-tipo-baja {
    color: #EF4444;
}

.estado-borrador {
    background: #FEF3C7;
    color: #D97706;
}

.estado-validado {
    background: #DBEAFE;
    color: #2563EB;
}

.estado-enviado {
    background: #D1FAE5;
    color: #059669;
}

.agregar-subcarpeta {
    margin-left: 20px;
    margin-top: 12px;
}

.empty-carpetas {
    text-align: center;
    padding: 60px 20px;
    background: var(--bg-secondary);
    border-radius: 12px;
}

.empty-carpetas i {
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
// URL base para las APIs
const API_CARPETAS = '<?= URL_BASE ?>api/carpetas.php';
const API_TABLAS = '<?= URL_BASE ?>api/tablas.php';

// Carga las carpetas al iniciar
document.addEventListener('DOMContentLoaded', function() {
    cargarCarpetas();
    
    // Si viene con parametro nueva=1, abre modal
    <?php if ($abrirModalCarpeta): ?>
    setTimeout(() => abrirModalCarpeta(), 500);
    <?php endif; ?>
});

// Carga todas las carpetas con sus subcarpetas y tablas
async function cargarCarpetas() {
    const contenedor = document.getElementById('contenedorCarpetas');
    
    const respuesta = await llamarApi(API_CARPETAS + '?action=listar');
    
    if (!respuesta || !respuesta.success) {
        contenedor.innerHTML = `
            <div class="empty-carpetas">
                <i data-lucide="folder-x"></i>
                <h3>Error al cargar</h3>
                <p>No se pudieron cargar las carpetas</p>
                <button class="btn btn-primary" onclick="cargarCarpetas()">Reintentar</button>
            </div>
        `;
        lucide.createIcons();
        return;
    }
    
    const carpetas = respuesta.data;
    
    if (carpetas.length === 0) {
        contenedor.innerHTML = `
            <div class="empty-carpetas">
                <i data-lucide="folder-plus"></i>
                <h3>Sin carpetas aún</h3>
                <p>Crea tu primera carpeta para organizar los movimientos IMSS</p>
                <button class="btn btn-primary" onclick="abrirModalCarpeta()">
                    <i data-lucide="plus"></i>
                    Crear Carpeta
                </button>
            </div>
        `;
        lucide.createIcons();
        return;
    }
    
    // Genera HTML de carpetas
    let html = '';
    
    carpetas.forEach(carpeta => {
        html += generarHtmlCarpeta(carpeta);
    });
    
    contenedor.innerHTML = html;
    lucide.createIcons();
}

// Genera el HTML de una carpeta
function generarHtmlCarpeta(carpeta) {
    let html = `
        <div class="carpeta-item" data-id="${carpeta.id_carpeta}">
            <div class="carpeta-header" onclick="toggleCarpeta(${carpeta.id_carpeta})">
                <div class="carpeta-info">
                    <div class="carpeta-icon">
                        <i data-lucide="folder"></i>
                    </div>
                    <div>
                        <div class="carpeta-nombre">${escapeHtml(carpeta.nombre)}</div>
                        <div class="carpeta-meta">
                            ${carpeta.total_subcarpetas} subcarpeta(s) • Creado: ${formatearFecha(carpeta.fecha_creacion)}
                        </div>
                    </div>
                </div>
                <div class="carpeta-acciones" onclick="event.stopPropagation()">
                    <button class="btn btn-ghost btn-sm btn-icon" onclick="editarCarpeta(${carpeta.id_carpeta}, '${escapeHtml(carpeta.nombre)}', '${escapeHtml(carpeta.descripcion || '')}')" title="Editar">
                        <i data-lucide="pencil"></i>
                    </button>
                    <button class="btn btn-ghost btn-sm btn-icon text-danger" onclick="eliminarCarpeta(${carpeta.id_carpeta}, '${escapeHtml(carpeta.nombre)}')" title="Eliminar">
                        <i data-lucide="trash-2"></i>
                    </button>
                </div>
            </div>
            <div class="carpeta-contenido" id="carpeta-contenido-${carpeta.id_carpeta}">
    `;
    
    // Subcarpetas
    if (carpeta.subcarpetas && carpeta.subcarpetas.length > 0) {
        carpeta.subcarpetas.forEach(subcarpeta => {
            html += generarHtmlSubcarpeta(subcarpeta, carpeta.nombre);
        });
    }
    
    // Boton para agregar subcarpeta
    html += `
                <div class="agregar-subcarpeta">
                    <button class="btn btn-ghost btn-sm" onclick="abrirModalSubcarpeta(${carpeta.id_carpeta}, '${escapeHtml(carpeta.nombre)}')">
                        <i data-lucide="folder-plus"></i>
                        Nueva Subcarpeta
                    </button>
                </div>
            </div>
        </div>
    `;
    
    return html;
}

// Genera el HTML de una subcarpeta
function generarHtmlSubcarpeta(subcarpeta, carpetaNombre) {
    let html = `
        <div class="subcarpeta-item" data-id="${subcarpeta.id_subcarpeta}">
            <div class="subcarpeta-header">
                <div class="subcarpeta-info">
                    <i data-lucide="folder-open" class="subcarpeta-icon"></i>
                    <span class="subcarpeta-nombre">${escapeHtml(subcarpeta.nombre)}</span>
                    <span class="badge badge-secondary">${subcarpeta.total_tablas} tabla(s)</span>
                </div>
                <div style="display: flex; gap: 8px;">
                    <button class="btn btn-ghost btn-sm" onclick="abrirModalTabla(${subcarpeta.id_subcarpeta}, '${escapeHtml(carpetaNombre)} › ${escapeHtml(subcarpeta.nombre)}')" title="Nueva tabla">
                        <i data-lucide="plus"></i>
                        Tabla
                    </button>
                    <button class="btn btn-ghost btn-sm btn-icon" onclick="editarSubcarpeta(${subcarpeta.id_subcarpeta}, '${escapeHtml(subcarpeta.nombre)}', '${escapeHtml(subcarpeta.descripcion || '')}')" title="Editar">
                        <i data-lucide="pencil"></i>
                    </button>
                    <button class="btn btn-ghost btn-sm btn-icon text-danger" onclick="eliminarSubcarpeta(${subcarpeta.id_subcarpeta}, '${escapeHtml(subcarpeta.nombre)}')" title="Eliminar">
                        <i data-lucide="trash-2"></i>
                    </button>
                </div>
            </div>
    `;
    
    // Tablas de la subcarpeta
    if (subcarpeta.tablas && subcarpeta.tablas.length > 0) {
        subcarpeta.tablas.forEach(tabla => {
            html += generarHtmlTabla(tabla);
        });
    }
    
    html += '</div>';
    return html;
}

// Genera el HTML de una tabla
function generarHtmlTabla(tabla) {
    const esTipoAlta = tabla.tipo === 'alta';
    const tieneErrores = tabla.alumnos_con_errores > 0;
    
    let estadoClase = 'estado-' + tabla.estado;
    let estadoTexto = tabla.estado === 'borrador' ? '📝 Borrador' : 
                      tabla.estado === 'validado' ? '🟢 Validado' : '🚀 Enviado';
    
    let acciones = '';
    
    // Boton ver siempre
    acciones += `<a href="<?= URL_BASE ?>views/jefa/tabla.php?id=${tabla.id_tabla}" class="btn btn-ghost btn-sm btn-icon" title="Ver"><i data-lucide="eye"></i></a>`;
    
    // Si esta enviado, boton descargar TXT
    if (tabla.estado === 'enviado') {
        acciones += `<a href="<?= URL_BASE ?>api/exportar-txt.php?action=generar&id_tabla=${tabla.id_tabla}" class="btn btn-ghost btn-sm btn-icon" title="Descargar TXT"><i data-lucide="download"></i></a>`;
    }
    
    // Si es borrador, botones editar y eliminar
    if (tabla.estado === 'borrador') {
        acciones += `<button class="btn btn-ghost btn-sm btn-icon text-danger" onclick="eliminarTabla(${tabla.id_tabla}, '${escapeHtml(tabla.nombre)}')" title="Eliminar"><i data-lucide="trash-2"></i></button>`;
    }
    
    return `
        <div class="tabla-item">
            <div class="tabla-info">
                <i data-lucide="${esTipoAlta ? 'trending-up' : 'trending-down'}" class="${esTipoAlta ? 'tabla-tipo-alta' : 'tabla-tipo-baja'}"></i>
                <div>
                    <strong>${escapeHtml(tabla.nombre)}</strong>
                    <div style="font-size: 12px; color: var(--text-secondary);">
                        ${tabla.total_alumnos} alumno(s)
                        ${tieneErrores ? `<span style="color: #EF4444;">• ${tabla.alumnos_con_errores} con errores</span>` : ''}
                    </div>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 12px;">
                <span class="badge ${estadoClase}">${estadoTexto}</span>
                <div style="display: flex; gap: 4px;">
                    ${acciones}
                </div>
            </div>
        </div>
    `;
}

// Toggle carpeta expandida/colapsada
function toggleCarpeta(idCarpeta) {
    const contenido = document.getElementById('carpeta-contenido-' + idCarpeta);
    contenido.classList.toggle('activo');
}

// ========== MODALES CARPETA ==========

function abrirModalCarpeta(id = null, nombre = '', descripcion = '') {
    document.getElementById('carpetaId').value = id || '';
    document.getElementById('carpetaNombre').value = nombre;
    document.getElementById('carpetaDescripcion').value = descripcion;
    document.getElementById('modalCarpetaTitulo').textContent = id ? 'Editar Carpeta' : 'Nueva Carpeta';
    document.getElementById('modalCarpeta').classList.add('active');
    document.getElementById('carpetaNombre').focus();
    lucide.createIcons();
}

function cerrarModalCarpeta() {
    document.getElementById('modalCarpeta').classList.remove('active');
    document.getElementById('formCarpeta').reset();
}

function editarCarpeta(id, nombre, descripcion) {
    abrirModalCarpeta(id, nombre, descripcion);
}

async function guardarCarpeta(evento) {
    evento.preventDefault();
    
    const id = document.getElementById('carpetaId').value;
    const nombre = document.getElementById('carpetaNombre').value.trim();
    const descripcion = document.getElementById('carpetaDescripcion').value.trim();
    
    const respuesta = await llamarApi(API_CARPETAS, {
        method: 'POST',
        body: JSON.stringify({
            action: id ? 'editar_carpeta' : 'crear_carpeta',
            id_carpeta: id,
            nombre: nombre,
            descripcion: descripcion
        })
    });
    
    if (respuesta && respuesta.success) {
        mostrarNotificacion(respuesta.message);
        cerrarModalCarpeta();
        cargarCarpetas();
    } else {
        mostrarNotificacion(respuesta?.message || 'Error al guardar', 'error');
    }
}

async function eliminarCarpeta(id, nombre) {
    const resultado = await Swal.fire({
        title: '¿Eliminar carpeta?',
        html: `Se eliminará la carpeta <strong>"${nombre}"</strong> y todas sus subcarpetas.<br><br><span style="color: var(--danger);">Esta acción no se puede deshacer.</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#EF4444'
    });
    
    if (resultado.isConfirmed) {
        const respuesta = await llamarApi(API_CARPETAS, {
            method: 'POST',
            body: JSON.stringify({
                action: 'eliminar_carpeta',
                id_carpeta: id
            })
        });
        
        if (respuesta && respuesta.success) {
            mostrarNotificacion(respuesta.message);
            cargarCarpetas();
        } else {
            mostrarNotificacion(respuesta?.message || 'Error al eliminar', 'error');
        }
    }
}

// ========== MODALES SUBCARPETA ==========

function abrirModalSubcarpeta(idCarpeta, nombreCarpeta, idSubcarpeta = null, nombre = '', descripcion = '') {
    document.getElementById('subcarpetaId').value = idSubcarpeta || '';
    document.getElementById('subcarpetaCarpetaId').value = idCarpeta;
    document.getElementById('subcarpetaCarpetaNombre').value = nombreCarpeta;
    document.getElementById('subcarpetaNombre').value = nombre;
    document.getElementById('subcarpetaDescripcion').value = descripcion;
    document.getElementById('modalSubcarpetaTitulo').textContent = idSubcarpeta ? 'Editar Subcarpeta' : 'Nueva Subcarpeta';
    document.getElementById('modalSubcarpeta').classList.add('active');
    document.getElementById('subcarpetaNombre').focus();
    lucide.createIcons();
}

function cerrarModalSubcarpeta() {
    document.getElementById('modalSubcarpeta').classList.remove('active');
    document.getElementById('formSubcarpeta').reset();
}

function editarSubcarpeta(id, nombre, descripcion) {
    // Necesitamos obtener el id de la carpeta padre
    const subcarpetaItem = document.querySelector(`.subcarpeta-item[data-id="${id}"]`);
    const carpetaItem = subcarpetaItem.closest('.carpeta-item');
    const idCarpeta = carpetaItem.dataset.id;
    const nombreCarpeta = carpetaItem.querySelector('.carpeta-nombre').textContent;
    
    abrirModalSubcarpeta(idCarpeta, nombreCarpeta, id, nombre, descripcion);
}

async function guardarSubcarpeta(evento) {
    evento.preventDefault();
    
    const id = document.getElementById('subcarpetaId').value;
    const idCarpeta = document.getElementById('subcarpetaCarpetaId').value;
    const nombre = document.getElementById('subcarpetaNombre').value.trim();
    const descripcion = document.getElementById('subcarpetaDescripcion').value.trim();
    
    const respuesta = await llamarApi(API_CARPETAS, {
        method: 'POST',
        body: JSON.stringify({
            action: id ? 'editar_subcarpeta' : 'crear_subcarpeta',
            id_subcarpeta: id,
            id_carpeta: idCarpeta,
            nombre: nombre,
            descripcion: descripcion
        })
    });
    
    if (respuesta && respuesta.success) {
        mostrarNotificacion(respuesta.message);
        cerrarModalSubcarpeta();
        cargarCarpetas();
    } else {
        mostrarNotificacion(respuesta?.message || 'Error al guardar', 'error');
    }
}

async function eliminarSubcarpeta(id, nombre) {
    const resultado = await Swal.fire({
        title: '¿Eliminar subcarpeta?',
        html: `Se eliminará la subcarpeta <strong>"${nombre}"</strong> y todas sus tablas.<br><br><span style="color: var(--danger);">Esta acción no se puede deshacer.</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#EF4444'
    });
    
    if (resultado.isConfirmed) {
        const respuesta = await llamarApi(API_CARPETAS, {
            method: 'POST',
            body: JSON.stringify({
                action: 'eliminar_subcarpeta',
                id_subcarpeta: id
            })
        });
        
        if (respuesta && respuesta.success) {
            mostrarNotificacion(respuesta.message);
            cargarCarpetas();
        } else {
            mostrarNotificacion(respuesta?.message || 'Error al eliminar', 'error');
        }
    }
}

// ========== MODALES TABLA ==========

function abrirModalTabla(idSubcarpeta, ubicacion) {
    document.getElementById('tablaSubcarpetaId').value = idSubcarpeta;
    document.getElementById('tablaUbicacion').value = ubicacion;
    document.getElementById('tablaFecha').value = new Date().toISOString().split('T')[0];
    document.getElementById('tablaNombre').value = '';
    document.querySelector('input[name="tipo"][value="alta"]').checked = true;
    document.getElementById('modalTabla').classList.add('active');
    lucide.createIcons();
}

function cerrarModalTabla() {
    document.getElementById('modalTabla').classList.remove('active');
    document.getElementById('formTabla').reset();
}

async function guardarTabla(evento) {
    evento.preventDefault();
    
    const formData = new FormData(document.getElementById('formTabla'));
    const datos = Object.fromEntries(formData);
    
    const respuesta = await llamarApi(API_TABLAS, {
        method: 'POST',
        body: JSON.stringify({
            action: 'crear_tabla',
            ...datos
        })
    });
    
    if (respuesta && respuesta.success) {
        mostrarNotificacion(respuesta.message);
        cerrarModalTabla();
        // Redirige a la tabla creada
        window.location.href = '<?= URL_BASE ?>views/jefa/tabla.php?id=' + respuesta.data.id_tabla;
    } else {
        mostrarNotificacion(respuesta?.message || 'Error al crear tabla', 'error');
    }
}

async function eliminarTabla(id, nombre) {
    const resultado = await Swal.fire({
        title: '¿Eliminar tabla?',
        html: `Se eliminará la tabla <strong>"${nombre}"</strong> y todos sus alumnos.<br><br><span style="color: var(--danger);">Esta acción no se puede deshacer.</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#EF4444'
    });
    
    if (resultado.isConfirmed) {
        const respuesta = await llamarApi(API_TABLAS, {
            method: 'POST',
            body: JSON.stringify({
                action: 'eliminar_tabla',
                id_tabla: id
            })
        });
        
        if (respuesta && respuesta.success) {
            mostrarNotificacion(respuesta.message);
            cargarCarpetas();
        } else {
            mostrarNotificacion(respuesta?.message || 'Error al eliminar', 'error');
        }
    }
}

// ========== UTILIDADES ==========

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
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
        cerrarModalCarpeta();
        cerrarModalSubcarpeta();
        cerrarModalTabla();
    }
});

// Cerrar modales al hacer clic fuera
['modalCarpeta', 'modalSubcarpeta', 'modalTabla'].forEach(id => {
    document.getElementById(id).addEventListener('click', (e) => {
        if (e.target === e.currentTarget) {
            e.target.classList.remove('active');
        }
    });
});
</script>

<?php // Incluye footer ?>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
