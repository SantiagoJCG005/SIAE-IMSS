<?php
/**
 * Vista compartida: Acuses IMSS
 * Requiere que $rolVista esté definido antes de incluir ('jefa' | 'admin-se').
 */
$tituloPagina      = 'Acuses IMSS';
$sidebarFile       = __DIR__ . "/../layouts/sidebar-$rolVista.php";
$urlCss            = URL_RECURSOS . "css/$rolVista/acuses.css";
$urlAcuseDetalle   = URL_BASE . "views/$rolVista/acuse-detalle.php";
$mostrarTooltips   = ($rolVista === 'admin-se');

include __DIR__ . '/../layouts/header.php';
include $sidebarFile;
?>

<link rel="stylesheet" href="<?= $urlCss ?>">

<div class="page-header">
    <div>
        <h1 class="page-title">Acuses IMSS</h1>
        <p class="page-subtitle">Sube los comprobantes PDF que envía el IMSS para actualizar el estatus de los alumnos</p>
    </div>
</div>

<!-- Subir PDF -->
<div class="card" id="seccionSubir">
    <div class="card-header">
        <h3 class="card-title"><i data-lucide="upload"></i> Subir comprobante PDF</h3>
    </div>
    <div class="card-body">
        <div class="upload-area" id="uploadArea" onclick="document.getElementById('inputPdf').click()">
            <i data-lucide="file-text"></i>
            <div class="upload-title">Haz clic o arrastra el PDF aquí</div>
            <div class="upload-sub">Archivos <strong>Recibo_XXXX_Lote_XXXX.pdf</strong> del IMSS — máx. 20 MB</div>
        </div>
        <input type="file" id="inputPdf" accept=".pdf,application/pdf" style="display:none">

        <div id="archivoSeleccionado" style="display:none; margin-top:16px;" class="alert alert-info">
            <i data-lucide="file-check"></i>
            <span id="nombreArchivo"></span>
            <button class="btn btn-ghost btn-sm" style="margin-left:auto;" onclick="limpiarSeleccion()">
                <i data-lucide="x"></i>
            </button>
        </div>

        <div style="margin-top:20px; display:flex; gap:12px; justify-content:flex-end;">
            <button class="btn btn-primary" id="btnAnalizar" onclick="analizarPdf()" disabled>
                <i data-lucide="scan-search"></i> Analizar PDF
            </button>
        </div>
    </div>
</div>

<!-- Preview / Confirmación -->
<div class="card" id="seccionPreview" style="display:none;">
    <div class="card-header">
        <h3 class="card-title"><i data-lucide="clipboard-check"></i> Vista previa del acuse</h3>
    </div>
    <div class="card-body">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:20px;">
            <div class="form-group">
                <label class="form-label">Número de Lote</label>
                <input type="text" id="prevLote" class="form-control" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">Tipo de movimiento</label>
                <input type="hidden" id="prevTipo" value="">
                <div id="prevTipoDisplay" class="form-control" style="background:var(--bg-secondary);cursor:default;display:flex;align-items:center;gap:6px;"></div>
            </div>
            <div class="form-group">
                <label class="form-label">Fecha del acuse *</label>
                <input type="date" id="prevFecha" class="form-control">
                <div id="prevFechaAviso" style="display:none; margin-top:6px; font-size:12px; color:#D97706; display:flex; align-items:center; gap:4px;">
                    <i data-lucide="alert-triangle" style="width:13px;height:13px;flex-shrink:0;"></i>
                    No se detectó la fecha en el PDF — ingresa la fecha manualmente.
                </div>
            </div>
        </div>

        <div class="preview-stats-grid">
            <div class="preview-stat">
                <div class="preview-stat-num" id="statTotal">0</div>
                <div class="preview-stat-label">Total en PDF <?php if ($mostrarTooltips): ?><span class="tip-icon" data-tip="Número total de NSS en el documento PDF del IMSS, incluyendo duplicados."><i data-lucide="info"></i></span><?php endif; ?></div>
                <div id="statDupNote" style="display:none; font-size:11px; color:#D97706; margin-top:2px;"></div>
            </div>
            <div class="preview-stat">
                <div class="preview-stat-num" style="color:#16A34A;" id="statEncontrados">0</div>
                <div class="preview-stat-label">Encontrados en sistema <?php if ($mostrarTooltips): ?><span class="tip-icon" data-tip="NSS que sí están registrados en el sistema. Se actualizará su estatus IMSS al confirmar."><i data-lucide="info"></i></span><?php endif; ?></div>
            </div>
            <div class="preview-stat">
                <div class="preview-stat-num" style="color:#DC2626;" id="statNoEncontrados">0</div>
                <div class="preview-stat-label">No encontrados <?php if ($mostrarTooltips): ?><span class="tip-icon" data-tip="NSS del PDF que no tienen alumno registrado en el sistema. No se actualiza nada para ellos."><i data-lucide="info"></i></span><?php endif; ?></div>
            </div>
        </div>

        <!-- Lista NSS -->
        <div style="margin-bottom:20px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                <strong>Detalle de NSS</strong>
                <div style="display:flex; gap:8px; align-items:center; font-size:13px;">
                    <button class="btn btn-ghost btn-sm" onclick="filtrarPreview('todos')" data-fprev="todos">Todos</button>
                    <button class="btn btn-ghost btn-sm" onclick="filtrarPreview('ok')" data-fprev="ok">Encontrados</button>
                    <button class="btn btn-ghost btn-sm" onclick="filtrarPreview('miss')" data-fprev="miss">No encontrados</button>
                </div>
            </div>
            <div class="nss-list" id="listaNss"></div>
        </div>

        <div id="alertaDuplicados" class="alert alert-warning" style="display:none; margin-bottom:16px;">
            <i data-lucide="alert-triangle"></i>
            <span id="alertaDuplicadosTexto"></span>
        </div>

        <div id="alertaNoDetectado" class="alert alert-warning" style="display:none; margin-bottom:16px;">
            <i data-lucide="alert-triangle"></i>
            <span>No se pudo detectar automáticamente el tipo o la fecha. Verifícalos antes de confirmar.</span>
        </div>

        <div style="display:flex; gap:12px; justify-content:flex-end;">
            <button class="btn btn-ghost" onclick="cancelarAcuse()">
                <i data-lucide="x"></i> Cancelar
            </button>
            <button class="btn btn-primary" onclick="confirmarAcuse()">
                <i data-lucide="check-circle"></i> Confirmar y guardar
            </button>
        </div>
    </div>
</div>

<!-- Historial -->
<div class="card" id="seccionHistorial">
    <div class="card-header">
        <div class="header-row">
            <h3 class="card-title"><i data-lucide="history"></i> Historial de acuses</h3>
            <div style="display:flex; gap:8px; align-items:center;">
                <select id="filtroTipo" class="form-control" style="width:auto;" onchange="cargarHistorial()">
                    <option value="">Todos</option>
                    <option value="alta">Altas</option>
                    <option value="baja">Bajas</option>
                </select>
            </div>
        </div>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Lote</th>
                        <th>Tipo</th>
                        <th>Fecha Acuse</th>
                        <th style="text-align:center;">Total</th>
                        <th style="text-align:center;">Procesados</th>
                        <th style="text-align:center;">No encontrados</th>
                        <th style="text-align:center;">Omitidos</th>
                        <th>Subido por</th>
                        <th>Fecha subida</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="cuerpoHistorial">
                    <tr><td colspan="11" style="text-align:center;padding:40px;">
                        <i data-lucide="loader-2" class="spin"></i> Cargando...
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer">
        <span id="infoPagHistorial"></span>
        <div id="paginacionHistorial"></div>
    </div>
</div>

<script>
const API = '<?= URL_BASE ?>api/acuses.php';
const URL_ACUSE_DETALLE = '<?= $urlAcuseDetalle ?>';
const PUEDE_ELIMINAR = <?= tieneAlgunRol([ROL_SUPERADMIN, ROL_JEFA_SERVICIOS]) ? 'true' : 'false' ?>;

let previewData = null;
let paginaHistorial = 1;

document.addEventListener('DOMContentLoaded', () => {
    cargarHistorial();
    inicializarDragDrop();
});

function inicializarDragDrop() {
    const area = document.getElementById('uploadArea');
    area.addEventListener('dragover', e => { e.preventDefault(); area.classList.add('drag-over'); });
    area.addEventListener('dragleave', () => area.classList.remove('drag-over'));
    area.addEventListener('drop', e => {
        e.preventDefault();
        area.classList.remove('drag-over');
        const file = e.dataTransfer.files[0];
        if (file) seleccionarArchivo(file);
    });
    document.getElementById('inputPdf').addEventListener('change', e => {
        if (e.target.files[0]) seleccionarArchivo(e.target.files[0]);
    });
}

function seleccionarArchivo(file) {
    if (file.type !== 'application/pdf') {
        Swal.fire({ icon: 'error', title: 'Formato no válido', text: 'Solo se aceptan archivos PDF.', confirmButtonColor: '#2563EB' });
        return;
    }
    document.getElementById('inputPdf')._file = file;
    document.getElementById('nombreArchivo').textContent = file.name;
    document.getElementById('archivoSeleccionado').style.display = 'flex';
    document.getElementById('btnAnalizar').disabled = false;
}

function limpiarSeleccion() {
    document.getElementById('inputPdf').value = '';
    document.getElementById('inputPdf')._file = null;
    document.getElementById('archivoSeleccionado').style.display = 'none';
    document.getElementById('btnAnalizar').disabled = true;
    document.getElementById('seccionPreview').style.display = 'none';
}

async function analizarPdf() {
    const inputEl = document.getElementById('inputPdf');
    const file = inputEl._file || inputEl.files[0];
    if (!file) return;

    const btn = document.getElementById('btnAnalizar');
    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="spin"></i> Analizando...';
    if (typeof lucide !== 'undefined') lucide.createIcons();

    const formData = new FormData();
    formData.append('pdf', file);

    try {
        const resp = await fetch(API + '?action=subir', { method: 'POST', body: formData });
        const data = await resp.json();
        if (!data.success) {
            Swal.fire({ icon: 'error', title: 'Error', text: data.message, confirmButtonColor: '#2563EB' });
            return;
        }
        mostrarPreview(data.data);
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo conectar con el servidor.', confirmButtonColor: '#2563EB' });
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="scan-search"></i> Analizar PDF';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
}

function mostrarPreview(d) {
    previewData = d;
    document.getElementById('prevLote').value = d.numero_lote || '';
    const tipo = d.tipo_detectado || 'alta';
    document.getElementById('prevTipo').value = tipo;
    document.getElementById('prevTipoDisplay').innerHTML = tipo === 'alta'
        ? '<span class="tipo-alta">&#9650; Alta</span>'
        : '<span class="tipo-baja">&#9660; Baja</span>';
    document.getElementById('prevFecha').value = d.fecha_detectada || '';
    document.getElementById('prevFechaAviso').style.display = d.fecha_detectada ? 'none' : 'flex';

    document.getElementById('statTotal').textContent         = d.total_bruto_pdf ?? d.total_en_pdf ?? 0;
    document.getElementById('statEncontrados').textContent   = d.encontrados_sistema || 0;
    document.getElementById('statNoEncontrados').textContent = d.no_encontrados || 0;

    const dupList = d.duplicados_en_pdf || [];
    const dupNote = document.getElementById('statDupNote');
    if (dupList.length > 0) {
        dupNote.textContent = dupList.length + ' NSS duplicado(s)';
        dupNote.style.display = 'block';
        const listaTxt = dupList.map(x => `<b>${escapeHtml(x.nss)}</b> (${x.veces} veces)`).join(', ');
        document.getElementById('alertaDuplicadosTexto').innerHTML =
            `El PDF contiene NSS duplicados: ${listaTxt}. ` +
            `Se procesará la primera ocurrencia; las adicionales quedarán registradas como <b>duplicado_nss</b> en el detalle del acuse. ` +
            `<b>Recomendación:</b> Verifique con los alumnos afectados cuál tiene el NSS incorrecto y solicite la corrección al IMSS.`;
        document.getElementById('alertaDuplicados').style.display = 'flex';
    } else {
        dupNote.style.display = 'none';
        document.getElementById('alertaDuplicados').style.display = 'none';
    }

    const alerta = document.getElementById('alertaNoDetectado');
    alerta.style.display = (!d.tipo_detectado || !d.fecha_detectada) ? 'flex' : 'none';

    renderListaNss(d.preview || []);
    document.getElementById('seccionPreview').style.display = 'block';
    document.getElementById('seccionPreview').scrollIntoView({ behavior: 'smooth' });
}

let filtroPreview = 'todos';

function filtrarPreview(f) {
    filtroPreview = f;
    document.querySelectorAll('[data-fprev]').forEach(b => b.classList.toggle('active', b.dataset.fprev === f));
    renderListaNss(previewData?.preview || []);
}

function renderListaNss(lista) {
    const filtrada = lista.filter(i => {
        if (filtroPreview === 'ok')   return i.en_sistema;
        if (filtroPreview === 'miss') return !i.en_sistema;
        return true;
    });
    if (filtrada.length === 0) {
        document.getElementById('listaNss').innerHTML = '<div style="padding:16px;text-align:center;color:var(--text-muted);">Sin registros</div>';
        return;
    }
    document.getElementById('listaNss').innerHTML = filtrada.map(i => `
        <div class="nss-item">
            <span class="nss-code">${escapeHtml(i.nss)}</span>
            ${i.duplicado ? '<span class="nss-badge badge-dup" title="Este NSS aparece mas de una vez en el PDF">&#9888; Duplicado</span>' : ''}
            <span class="nss-badge ${i.en_sistema ? 'badge-ok' : 'badge-miss'}">${i.en_sistema ? 'En sistema' : 'No encontrado'}</span>
            <span style="color:var(--text-secondary);">${escapeHtml(i.nombre || '—')}</span>
        </div>
    `).join('');
}

async function confirmarAcuse() {
    const tipo  = document.getElementById('prevTipo').value;
    const fecha = document.getElementById('prevFecha').value;
    if (!fecha) {
        const el = document.getElementById('prevFecha');
        el.style.borderColor = '#DC2626';
        el.focus();
        el.addEventListener('input', () => el.style.borderColor = '', { once: true });
        Swal.fire({ icon: 'warning', title: 'Falta la fecha', text: 'Ingresa la fecha de recepción del acuse.', confirmButtonColor: '#2563EB' });
        return;
    }
    const dupList = previewData.duplicados_en_pdf || [];
    const dupNote = dupList.length > 0
        ? `<br><span style="color:#D97706;font-size:13px;">&#9888; ${dupList.length} NSS duplicado(s) en el PDF — solo se procesará la primera ocurrencia de cada uno.</span>`
        : '';
    const res = await Swal.fire({
        title: '¿Confirmar acuse?',
        html: `Se actualizará el estatus IMSS de los alumnos encontrados.<br><strong>${previewData.encontrados_sistema}</strong> registros serán procesados.${dupNote}`,
        icon: 'question', showCancelButton: true, confirmButtonColor: '#2563EB',
        cancelButtonColor: '#64748B', confirmButtonText: 'Sí, confirmar', cancelButtonText: 'Cancelar',
    });
    if (!res.isConfirmed) return;

    const fd = new FormData();
    fd.append('tipo',  tipo);
    fd.append('fecha', fecha);
    try {
        const resp = await fetch(API + '?action=confirmar', { method: 'POST', body: fd });
        const data = await resp.json();
        if (!data.success) {
            Swal.fire({ icon: 'error', title: 'Error', text: data.message, confirmButtonColor: '#2563EB' });
            return;
        }
        const d = data.data;
        const dupLine = d.duplicados > 0 ? ` · <b>${d.duplicados}</b> NSS duplicados en PDF` : '';
        await Swal.fire({
            icon: 'success', title: 'Acuse confirmado',
            html: `<b>${d.procesados}</b> procesados · <b>${d.no_encontrados}</b> no encontrados · <b>${d.omitidos}</b> omitidos por fecha${dupLine}`,
            confirmButtonColor: '#2563EB',
        });
        limpiarSeleccion();
        previewData = null;
        cargarHistorial();
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo conectar con el servidor.', confirmButtonColor: '#2563EB' });
    }
}

async function cancelarAcuse() {
    await fetch(API + '?action=cancelar', { method: 'POST' });
    limpiarSeleccion();
    previewData = null;
}

async function cargarHistorial(pagina = 1) {
    paginaHistorial = pagina;
    const tipo = document.getElementById('filtroTipo').value;
    const resp = await fetch(`${API}?action=listar&pagina=${pagina}&tipo=${tipo}`);
    const data = await resp.json();
    if (!data.success) return;
    renderHistorial(data.data);
}

function renderHistorial(d) {
    const tbody = document.getElementById('cuerpoHistorial');
    if (!d.acuses || d.acuses.length === 0) {
        tbody.innerHTML = '<tr><td colspan="11" style="text-align:center;padding:40px;color:var(--text-muted);">Sin acuses registrados</td></tr>';
        document.getElementById('infoPagHistorial').textContent = '';
        document.getElementById('paginacionHistorial').innerHTML = '';
        return;
    }
    tbody.innerHTML = d.acuses.map((a, i) => `
        <tr>
            <td>${i + 1 + (paginaHistorial - 1) * 20}</td>
            <td style="font-family:monospace;">${escapeHtml(a.numero_lote)}</td>
            <td><span class="tipo-${a.tipo}">${a.tipo === 'alta' ? '▲ Alta' : '▼ Baja'}</span></td>
            <td>${formatearFecha(a.fecha_recepcion_imss)}</td>
            <td style="text-align:center;">${a.total_registros}</td>
            <td style="text-align:center;color:#16A34A;">${a.procesados}</td>
            <td style="text-align:center;color:#DC2626;">${a.no_encontrados}</td>
            <td style="text-align:center;color:#D97706;">${a.omitidos_por_fecha}</td>
            <td>${escapeHtml(a.subido_por)}</td>
            <td>${formatearFechaHora(a.fecha_subida)}</td>
            <td>
                <button class="btn btn-ghost btn-sm btn-icon" onclick="verDetalle(${a.id_acuse})" title="Ver detalle">
                    <i data-lucide="eye"></i>
                </button>
                ${PUEDE_ELIMINAR ? `<button class="btn btn-ghost btn-sm btn-icon" style="color:#DC2626;" onclick="eliminarAcuse(${a.id_acuse}, '${escapeHtml(a.numero_lote)}')" title="Eliminar"><i data-lucide="trash-2"></i></button>` : ''}
            </td>
        </tr>
    `).join('');
    document.getElementById('infoPagHistorial').textContent =
        `Página ${d.pagina} de ${d.paginas} (${d.total} acuses)`;
    renderPaginacionHistorial(d.paginas);
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function renderPaginacionHistorial(total) {
    const c = document.getElementById('paginacionHistorial');
    if (total <= 1) { c.innerHTML = ''; return; }
    let html = `<button class="paginacion-btn" onclick="cargarHistorial(${paginaHistorial-1})" ${paginaHistorial===1?'disabled':''}><i data-lucide="chevron-left" style="width:16px;height:16px;"></i></button>`;
    for (let p = 1; p <= total; p++) {
        html += `<button class="paginacion-btn ${p===paginaHistorial?'active':''}" onclick="cargarHistorial(${p})">${p}</button>`;
    }
    html += `<button class="paginacion-btn" onclick="cargarHistorial(${paginaHistorial+1})" ${paginaHistorial===total?'disabled':''}><i data-lucide="chevron-right" style="width:16px;height:16px;"></i></button>`;
    c.innerHTML = html;
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function verDetalle(idAcuse) {
    window.location.href = URL_ACUSE_DETALLE + '?id=' + idAcuse;
}

async function eliminarAcuse(idAcuse, lote) {
    const res = await Swal.fire({
        title: '¿Eliminar acuse?',
        html: `Se eliminará el lote <b>${escapeHtml(lote)}</b> y todos sus registros. Esta acción no se puede deshacer.`,
        icon: 'warning', showCancelButton: true, confirmButtonColor: '#DC2626',
        cancelButtonColor: '#64748B', confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar',
    });
    if (!res.isConfirmed) return;
    const fd = new FormData();
    fd.append('id', idAcuse);
    const resp = await fetch(API + '?action=eliminar', { method: 'POST', body: fd });
    const data = await resp.json();
    if (!data.success) {
        Swal.fire({ icon: 'error', title: 'Error', text: data.message, confirmButtonColor: '#2563EB' });
        return;
    }
    cargarHistorial(paginaHistorial);
}

function escapeHtml(t) {
    if (!t) return '';
    const d = document.createElement('div');
    d.textContent = t;
    return d.innerHTML;
}

function formatearFecha(f) {
    if (!f) return '—';
    const p = f.split('-');
    return `${p[2]}/${p[1]}/${p[0]}`;
}

function formatearFechaHora(f) {
    if (!f) return '—';
    const d = new Date(f);
    return d.toLocaleDateString('es-MX', { day:'2-digit', month:'2-digit', year:'numeric' }) +
           ' ' + d.toLocaleTimeString('es-MX', { hour:'2-digit', minute:'2-digit' });
}

<?php if ($mostrarTooltips): ?>
(function () {
    const tt = document.createElement('div');
    tt.style.cssText = 'position:fixed;background:#1E293B;color:#F8FAFC;font-size:11px;line-height:1.5;padding:7px 10px;border-radius:6px;max-width:220px;white-space:normal;pointer-events:none;z-index:9999;box-shadow:0 4px 12px rgba(0,0,0,.2);display:none;';
    document.body.appendChild(tt);
    function place(e) {
        const w = tt.offsetWidth, h = tt.offsetHeight;
        let x = e.clientX - w / 2;
        let y = e.clientY - h - 10;
        x = Math.max(8, Math.min(x, window.innerWidth  - w - 8));
        y = y < 8 ? e.clientY + 16 : y;
        tt.style.left = x + 'px';
        tt.style.top  = y + 'px';
    }
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.tip-icon[data-tip]').forEach(el => {
            el.addEventListener('mouseenter', e => { tt.textContent = el.dataset.tip; tt.style.display = 'block'; place(e); });
            el.addEventListener('mousemove',  place);
            el.addEventListener('mouseleave', () => { tt.style.display = 'none'; });
        });
    });
})();
<?php endif; ?>
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
