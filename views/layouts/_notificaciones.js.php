<?php
/**
 * Componente: JS + CSS del dropdown de notificaciones
 *
 * Variables PHP que deben estar definidas antes de incluir este archivo:
 *   string $urlVerTodas         URL a la página completa de notificaciones
 *   bool   $puedeAprobarExport  true solo para Jefa (muestra botones Aprobar/Revisar)
 *   string $urlCorregirAlumno   URL base de corregir-alumno.php (vacío si no aplica)
 */
?>
<link rel="stylesheet" href="<?= URL_RECURSOS ?>css/notifications.css">
<script>
const API_NOTIF            = '<?= URL_BASE ?>api/notificaciones.php';
const URL_VER_TODAS        = '<?= htmlspecialchars($urlVerTodas ?? '') ?>';
const PUEDE_APROBAR_EXPORT = <?= !empty($puedeAprobarExport) ? 'true' : 'false' ?>;
const URL_CORREGIR         = '<?= htmlspecialchars($urlCorregirAlumno ?? '') ?>';
let notifDropdownAbierto   = false;

document.addEventListener('DOMContentLoaded', () => {
    cargarContadorNotificaciones();
    setInterval(cargarContadorNotificaciones, 60000);
});

document.addEventListener('click', (e) => {
    if (!e.target.closest('.notif-container') && notifDropdownAbierto) {
        cerrarNotificaciones();
    }
});

async function cargarContadorNotificaciones() {
    try {
        const resp = await fetch(API_NOTIF + '?action=contar');
        const data = await resp.json();
        if (data.success) {
            const badge = document.getElementById('notifBadge');
            const total = data.data.total;
            badge.textContent = total > 99 ? '99+' : total;
            badge.style.display = total > 0 ? 'flex' : 'none';
        }
    } catch (e) {}
}

function toggleNotificaciones() {
    if (notifDropdownAbierto) {
        cerrarNotificaciones();
    } else {
        document.getElementById('notifDropdown').style.display = 'block';
        notifDropdownAbierto = true;
        cargarNotificaciones();
    }
}

function cerrarNotificaciones() {
    document.getElementById('notifDropdown').style.display = 'none';
    notifDropdownAbierto = false;
}

async function cargarNotificaciones() {
    const lista = document.getElementById('notifLista');
    lista.innerHTML = '<div class="notif-loading">Cargando...</div>';
    try {
        const resp = await fetch(API_NOTIF + '?action=listar&limite=10');
        const data = await resp.json();
        if (data.success && data.data.length > 0) {
            lista.innerHTML = data.data.map(n => renderNotificacion(n)).join('');
            if (typeof lucide !== 'undefined') lucide.createIcons();
        } else {
            lista.innerHTML = '<div class="notif-empty"><i data-lucide="bell-off" style="width:32px;height:32px;display:block;margin:0 auto 8px;"></i>Sin notificaciones</div>';
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    } catch (e) {
        lista.innerHTML = '<div class="notif-empty">No se pudieron cargar</div>';
    }
}

function renderNotificacion(n) {
    const tipoClase = n.tipo === 'exportacion_txt' ? 'tipo-exportacion' : (n.tipo === 'alerta_problema' ? 'tipo-alerta' : 'tipo-info');
    const icono     = n.tipo === 'exportacion_txt' ? 'file-output'    : (n.tipo === 'alerta_problema' ? 'alert-triangle'  : 'info');
    const noLeida   = !n.leida ? 'no-leida' : '';
    const tiempo    = tiempoRelativo(n.fecha_creacion);

    let acciones = '';
    if (PUEDE_APROBAR_EXPORT && n.tipo === 'exportacion_txt' && (n.estado === 'nueva' || n.estado === 'vista')) {
        acciones = `
            <div class="notif-actions-inline">
                <button class="notif-action-btn ok"      onclick="event.stopPropagation(); cambiarEstado(${n.id_notificacion}, 'revisada')">Aprobar</button>
                <button class="notif-action-btn problema" onclick="event.stopPropagation(); cambiarEstado(${n.id_notificacion}, 'problema')">Revisar</button>
            </div>`;
    }
    if (URL_CORREGIR && n.referencia_tipo === 'reporte_alumno') {
        acciones = `
            <div class="notif-actions-inline">
                <button class="notif-action-btn ok"
                        onclick="event.stopPropagation(); window.location.href=URL_CORREGIR+'?id=${n.id_notificacion}'">
                    Corregir datos
                </button>
            </div>`;
    }

    return `
        <div class="notif-item ${noLeida}" onclick="verNotificacion(${n.id_notificacion})">
            <div class="notif-indicator ${tipoClase}">
                <i data-lucide="${icono}" style="width:16px;height:16px;"></i>
            </div>
            <div class="notif-content">
                <div class="notif-titulo">${escapeHtml(n.titulo)}</div>
                <div class="notif-meta">
                    ${n.nombre_origen ? '<span>' + escapeHtml(n.nombre_origen) + '</span><span class="notif-meta-dot"></span>' : ''}
                    <span>${tiempo}</span>
                </div>
                ${acciones}
            </div>
            <button class="notif-delete-btn" onclick="event.stopPropagation(); eliminarNotif(${n.id_notificacion})" title="Eliminar">
                <i data-lucide="x" style="width:14px;height:14px;"></i>
            </button>
        </div>`;
}

async function verNotificacion(id) {
    await fetch(API_NOTIF + '?action=marcar_leida&id=' + id);
    cargarContadorNotificaciones();
    cargarNotificaciones();
}

async function marcarTodasLeidas() {
    await fetch(API_NOTIF + '?action=marcar_todas_leidas');
    cargarContadorNotificaciones();
    cargarNotificaciones();
}

async function eliminarNotif(id) {
    await fetch(API_NOTIF + '?action=eliminar&id=' + id);
    cargarContadorNotificaciones();
    cargarNotificaciones();
}

async function limpiarLeidas() {
    const confirmado = await Swal.fire({
        title: 'Limpiar leídas',
        text: 'Se eliminarán las notificaciones ya leídas.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: 'var(--secondary)',
        cancelButtonColor: 'var(--text-muted)',
        confirmButtonText: 'Sí, limpiar',
        cancelButtonText: 'Cancelar'
    });
    if (!confirmado.isConfirmed) return;
    await fetch(API_NOTIF + '?action=eliminar_leidas');
    cargarContadorNotificaciones();
    cargarNotificaciones();
    mostrarNotificacion('Notificaciones leídas eliminadas', 'success');
}

async function cambiarEstado(id, estado) {
    if (estado === 'problema') {
        const confirmado = await Swal.fire({
            title: '¿Reportar problema?',
            text: 'Se notificará al usuario que realizó la exportación.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: 'var(--danger)',
            cancelButtonColor: 'var(--text-muted)',
            confirmButtonText: 'Sí, reportar',
            cancelButtonText: 'Cancelar'
        });
        if (!confirmado.isConfirmed) return;
    }
    await fetch(API_NOTIF + '?action=cambiar_estado&id=' + id + '&estado=' + estado);
    cargarContadorNotificaciones();
    cargarNotificaciones();
    mostrarNotificacion(estado === 'problema' ? 'Problema reportado al usuario' : 'Marcada como revisada',
                        estado === 'problema' ? 'warning' : 'success');
}

function tiempoRelativo(fecha) {
    const diff = Math.floor((new Date() - new Date(fecha)) / 1000);
    if (diff < 60)     return 'Ahora';
    if (diff < 3600)   return Math.floor(diff / 60) + ' min';
    if (diff < 86400)  return Math.floor(diff / 3600) + ' h';
    if (diff < 604800) return Math.floor(diff / 86400) + ' d';
    return new Date(fecha).toLocaleDateString('es-MX', { day: '2-digit', month: 'short' });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>
