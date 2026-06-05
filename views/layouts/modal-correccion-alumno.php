<?php
/**
 * SIAE-IMSS - Modal de corrección de datos de alumno
 * Incluido en footer.php — disponible en todas las páginas de Jefa, Admin SE y roles personalizados con atender_incidencias
 * Se activa mediante abrirCorreccionAlumno(nc, mensaje, campos)
 */

// Solo mostrar para roles que pueden corregir datos
if (!tieneAlgunRol([ROL_JEFA_SERVICIOS, ROL_ADMIN_SERVICIOS, ROL_SUPERADMIN, ROL_ADMIN_IMSS])) return;
?>
<!-- Modal corrección de datos de alumno -->
<div class="modal-overlay" id="modalCorreccion" style="z-index:1100;">
    <div class="modal" style="max-width:620px;">
        <div class="modal-header">
            <h3 class="modal-title">
                <i data-lucide="user-pen" style="width:18px;height:18px;margin-right:6px;vertical-align:middle;"></i>
                Corregir datos del alumno
            </h3>
            <button class="modal-close" onclick="cerrarCorreccion()">
                <i data-lucide="x"></i>
            </button>
        </div>
        <div class="modal-body">

            <!-- Resumen del reporte -->
            <div id="correccionResumenReporte"
                 style="background:#FEF3C7;border:1px solid #FCD34D;border-radius:8px;
                        padding:12px 14px;margin-bottom:18px;font-size:13px;display:none;">
                <div style="font-weight:600;color:#92400E;margin-bottom:4px;">
                    <i data-lucide="alert-triangle" style="width:13px;height:13px;vertical-align:middle;margin-right:4px;"></i>
                    El alumno reportó datos incorrectos
                </div>
                <div id="correccionCamposReporte" style="color:#78350F;"></div>
                <div id="correccionDescReporte"   style="color:#92400E;margin-top:4px;"></div>
            </div>

            <!-- Spinner de carga -->
            <div id="correccionCargando" style="text-align:center;padding:32px;">
                <i data-lucide="loader" style="width:32px;height:32px;animation:spin 1s linear infinite;color:var(--secondary);"></i>
                <p style="margin-top:10px;color:var(--text-muted);font-size:13px;">Cargando datos del alumno...</p>
            </div>

            <!-- Formulario de edición -->
            <div id="correccionForm" style="display:none;">
                <input type="hidden" id="correccionNC">

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div class="form-group">
                        <label class="form-label">Nombre(s) *</label>
                        <input type="text" id="corNombre" class="form-control" maxlength="100">
                    </div>
                    <div class="form-group">
                        <label class="form-label">No. de Control</label>
                        <input type="text" id="corNC_display" class="form-control" disabled
                               style="background:#F8FAFC;color:var(--text-muted);">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Apellido Paterno *</label>
                        <input type="text" id="corApellidoP" class="form-control" maxlength="80">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Apellido Materno</label>
                        <input type="text" id="corApellidoM" class="form-control" maxlength="80">
                    </div>
                    <div class="form-group">
                        <label class="form-label">CURP</label>
                        <input type="text" id="corCurp" class="form-control" maxlength="18"
                               style="text-transform:uppercase;" placeholder="18 caracteres">
                    </div>
                    <div class="form-group">
                        <label class="form-label">NSS</label>
                        <input type="text" id="corNss" class="form-control" maxlength="11"
                               placeholder="11 dígitos">
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label class="form-label">Carrera</label>
                        <select id="corCarrera" class="form-control form-select">
                            <option value="">— Sin asignar —</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" id="corEmail" class="form-control" maxlength="150">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Teléfono</label>
                        <input type="text" id="corTelefono" class="form-control" maxlength="20">
                    </div>
                </div>
            </div>

            <!-- Mensaje resultado -->
            <div id="correccionMensaje" style="display:none;border-radius:8px;padding:12px 14px;font-size:13px;margin-top:8px;"></div>
        </div>
        <div class="modal-footer" id="correccionFooter">
            <button type="button" class="btn btn-ghost" onclick="cerrarCorreccion()">Cancelar</button>
            <button type="button" class="btn btn-primary" id="correccionBtnGuardar"
                    onclick="guardarCorreccion()" style="display:none;">
                <i data-lucide="save"></i> Guardar cambios
            </button>
        </div>
    </div>
</div>

<script>
const API_ALUMNOS = '<?= URL_BASE ?>api/alumnos.php';

// Wrapper que lee del cache global y abre el modal
function abrirCorreccionFromCache(id) {
    const data = (window._notifCache || {})[id] || {};
    abrirCorreccionAlumno(data.nc || '', data.mensaje || '', data.campos || []);
}

// Abre el modal cargando los datos actuales del alumno por su número de control
async function abrirCorreccionAlumno(nc, notifMensaje, camposReportados) {
    // Resetear estado
    document.getElementById('correccionCargando').style.display = 'block';
    document.getElementById('correccionForm').style.display     = 'none';
    document.getElementById('correccionMensaje').style.display  = 'none';
    document.getElementById('correccionBtnGuardar').style.display = 'none';

    // Mostrar resumen del reporte si hay datos
    const resumen = document.getElementById('correccionResumenReporte');
    if (camposReportados && camposReportados.length > 0) {
        document.getElementById('correccionCamposReporte').textContent =
            'Campos reportados: ' + camposReportados.join(', ');
        resumen.style.display = 'block';
    } else {
        resumen.style.display = 'none';
    }
    if (notifMensaje) {
        const match = notifMensaje.match(/Descripción:\s*(.+)$/);
        document.getElementById('correccionDescReporte').textContent =
            match ? match[1] : '';
    }

    document.getElementById('modalCorreccion').classList.add('active');
    lucide.createIcons();

    try {
        const resp = await fetch(`${API_ALUMNOS}?action=buscar&nc=${encodeURIComponent(nc)}`);
        const data = await resp.json();

        if (!data.success) {
            mostrarMensajeCorreccion(data.message || 'No se pudieron cargar los datos', 'error');
            return;
        }

        const a = data.data.alumno;

        // Poblar formulario
        document.getElementById('correccionNC').value      = a.numero_control;
        document.getElementById('corNC_display').value     = a.numero_control;
        document.getElementById('corNombre').value         = a.nombre         || '';
        document.getElementById('corApellidoP').value      = a.apellido_paterno || '';
        document.getElementById('corApellidoM').value      = a.apellido_materno || '';
        document.getElementById('corCurp').value           = a.curp           || '';
        document.getElementById('corNss').value            = a.nss            || '';
        document.getElementById('corEmail').value          = a.email          || '';
        document.getElementById('corTelefono').value       = a.telefono       || '';

        // Llenar dropdown de carreras
        const sel = document.getElementById('corCarrera');
        sel.innerHTML = '<option value="">— Sin asignar —</option>';
        data.data.carreras.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.id_carrera;
            opt.textContent = c.nombre;
            if (c.id_carrera == a.id_carrera) opt.selected = true;
            sel.appendChild(opt);
        });

        document.getElementById('correccionCargando').style.display   = 'none';
        document.getElementById('correccionForm').style.display        = 'block';
        document.getElementById('correccionBtnGuardar').style.display  = 'flex';
        lucide.createIcons();

    } catch (e) {
        mostrarMensajeCorreccion('Error de conexión al cargar los datos', 'error');
    }
}

// Guarda los datos corregidos del alumno
async function guardarCorreccion() {
    const btn = document.getElementById('correccionBtnGuardar');
    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader" style="width:15px;height:15px;animation:spin 1s linear infinite;"></i> Guardando...';
    lucide.createIcons();

    const payload = {
        action:           'actualizar',
        numero_control:   document.getElementById('correccionNC').value,
        nombre:           document.getElementById('corNombre').value,
        apellido_paterno: document.getElementById('corApellidoP').value,
        apellido_materno: document.getElementById('corApellidoM').value,
        curp:             document.getElementById('corCurp').value,
        nss:              document.getElementById('corNss').value,
        id_carrera:       document.getElementById('corCarrera').value,
        email:            document.getElementById('corEmail').value,
        telefono:         document.getElementById('corTelefono').value,
    };

    try {
        const resp = await fetch(API_ALUMNOS, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await resp.json();

        if (data.success) {
            mostrarMensajeCorreccion('✓ Datos actualizados correctamente', 'exito');
            btn.style.display = 'none';
            setTimeout(cerrarCorreccion, 2000);
        } else {
            mostrarMensajeCorreccion(data.message || 'Error al guardar', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="save"></i> Guardar cambios';
            lucide.createIcons();
        }
    } catch (e) {
        mostrarMensajeCorreccion('Error de conexión', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="save"></i> Guardar cambios';
        lucide.createIcons();
    }
}

function mostrarMensajeCorreccion(texto, tipo) {
    const el = document.getElementById('correccionMensaje');
    el.style.cssText = tipo === 'exito'
        ? 'display:block;background:#DCFCE7;color:#166534;border-radius:8px;padding:12px 14px;font-size:13px;margin-top:8px;'
        : 'display:block;background:#FEE2E2;color:#991B1B;border-radius:8px;padding:12px 14px;font-size:13px;margin-top:8px;';
    el.textContent = texto;
    document.getElementById('correccionCargando').style.display = 'none';
}

function cerrarCorreccion() {
    document.getElementById('modalCorreccion').classList.remove('active');
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') cerrarCorreccion();
});

document.getElementById('modalCorreccion')?.addEventListener('click', e => {
    if (e.target === e.currentTarget) cerrarCorreccion();
});
</script>
