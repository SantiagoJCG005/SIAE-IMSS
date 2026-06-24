<?php
/**
 * SIAE-IMSS - Dashboard Estudiante (ROL 5)
 * Muestra al alumno sus datos personales y su estatus de registro ante el IMSS
 */

// PHP - titulo de pestana del navegador
$tituloPagina = 'Mi Estatus IMSS';

// PHP - cargar configuracion, base de datos, autenticacion y utilidades
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// PHP - solo el rol Estudiante puede acceder a esta vista
requerirRol([ROL_ESTUDIANTE]);

$conexion      = obtenerConexion();
$sessionUser   = obtenerUsuarioActual();
// PHP - el numero de control del alumno es el mismo que su username
$numeroControl = $sessionUser['username'] ?? '';

// PHP - variables inicializadas en null por si el alumno no tiene datos
$alumno      = null;
$datosImss   = null;
$estatusImss = null;

try {
    // PHP - consultar datos del alumno vinculado a esta cuenta por numero de control
    $stmt = $conexion->prepare("
        SELECT a.*, c.nombre AS nombre_carrera
        FROM alumnos a
        LEFT JOIN carreras c ON a.id_carrera = c.id_carrera
        WHERE a.numero_control = ?
        LIMIT 1
    ");
    $stmt->execute([$numeroControl]);
    $alumno = $stmt->fetch();

    if ($alumno) {
        // PHP - consultar NSS del alumno en la tabla de datos IMSS
        $stmt2 = $conexion->prepare("SELECT nss FROM datos_imss WHERE id_alumno = ? LIMIT 1");
        $stmt2->execute([$alumno['id_alumno']]);
        $datosImss = $stmt2->fetch();

        // PHP - consultar el estatus IMSS mas reciente del alumno (alta o baja)
        $stmt3 = $conexion->prepare("
            SELECT e.estatus, e.fecha_movimiento
            FROM estatus_imss_alumnos e
            WHERE e.id_alumno = ?
            ORDER BY e.fecha_movimiento DESC
            LIMIT 1
        ");
        $stmt3->execute([$alumno['id_alumno']]);
        $estatusImss = $stmt3->fetch();
    }
} catch (Exception $e) {
    // sin datos — no mostrar error tecnico al alumno
}

// PHP - valores por defecto cuando no hay estatus registrado: "En proceso"
$estatusLabel   = 'En proceso';
$estatusColor   = '#64748B';
$estatusBg      = '#F1F5F9';
$estatusIcono   = 'clock';
$estatusDesc    = 'Tu tramite de afiliacion al IMSS esta en proceso. Servicios Escolares te notificara cuando tu seguro social quede activo.';
$estatusFechaLabel = '';

// PHP - si no hay alumno vinculado, el estatus IMSS no aplica para esta cuenta
if (!$alumno) {
    $estatusLabel = 'Sin registro';
    $estatusColor = '#94A3B8';
    $estatusBg    = '#F8FAFC';
    $estatusIcono = 'minus-circle';
    $estatusDesc  = 'Esta cuenta no tiene un expediente de alumno vinculado. El estatus IMSS no aplica para este perfil.';
}

// PHP - sobrescribir valores segun el estatus real del alumno
if (!empty($estatusImss)) {
    $fechaFmt = date('d/m/Y', strtotime($estatusImss['fecha_movimiento']));
    switch ($estatusImss['estatus']) {
        case 'alta':
            // PHP - alumno con alta IMSS activa: color verde
            $estatusLabel      = 'Alta IMSS';
            $estatusColor      = '#16A34A';
            $estatusBg         = '#F0FDF4';
            $estatusIcono      = 'shield-check';
            $estatusDesc       = 'Tu seguro social esta activo. Estas dado de alta ante el IMSS por el Tecnologico de Chetumal.';
            $estatusFechaLabel = "Fecha de alta: <strong>$fechaFmt</strong>";
            break;
        case 'baja':
            // PHP - alumno con baja IMSS: color rojo
            $estatusLabel      = 'Baja IMSS';
            $estatusColor      = '#DC2626';
            $estatusBg         = '#FFF5F5';
            $estatusIcono      = 'shield-off';
            $estatusDesc       = 'Tu registro ante el IMSS ha sido dado de baja. Contacta a Servicios Escolares si consideras que es un error.';
            $estatusFechaLabel = "Fecha de baja: <strong>$fechaFmt</strong>";
            break;
    }
}

include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar-estudiante.php';
?>

<style>
    /* ─── banner de bienvenida con gradiente azul ─── */
    .dash-bienvenida {
        background: linear-gradient(135deg, #1E40AF 0%, #3B82F6 100%);
        border-radius: 16px;
        padding: 28px 32px;
        color: white;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
    }
    .dash-bienvenida h1 { font-size: 22px; font-weight: 700; margin-bottom: 4px; }
    .dash-bienvenida p  { font-size: 14px; opacity: 0.85; }
    .dash-bienvenida-icono {
        width: 64px; height: 64px;
        background: rgba(255,255,255,0.18);
        border-radius: 16px;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }

    /* ─── grilla de 2 columnas: datos + estatus ─── */
    .dash-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        align-items: start;
    }
    @media (max-width: 768px) {
        .dash-grid          { grid-template-columns: 1fr; }
        .dash-bienvenida    { flex-direction: column; text-align: center; }
    }

    /* ─── tarjeta de informacion del alumno ─── */
    .info-card {
        background: white;
        border-radius: 14px;
        border: 1px solid #E2E8F0;
        overflow: hidden;
    }
    .info-card-header {
        padding: 16px 20px;
        border-bottom: 1px solid #F1F5F9;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .info-card-header h3 { font-size: 15px; font-weight: 600; color: #1E293B; }
    .info-card-header i  { color: #2563EB; }
    .info-card-body { padding: 20px; }

    /* ─── fila de dato individual: icono + label + valor ─── */
    .dato-fila {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 11px 0;
        border-bottom: 1px solid #F8FAFC;
    }
    .dato-fila:last-child { border-bottom: none; padding-bottom: 0; }

    .dato-icono {
        width: 32px; height: 32px;
        background: #EFF6FF;
        border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
        color: #2563EB;
    }
    .dato-icono i { width: 15px; height: 15px; }

    .dato-texto { flex: 1; min-width: 0; }
    .dato-label {
        font-size: 11px;
        color: #94A3B8;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        line-height: 1.2;
        margin-bottom: 2px;
    }
    .dato-valor {
        font-size: 13px;
        font-weight: 600;
        color: #1E293B;
        word-break: break-word;
    }
    .dato-valor.mono {
        font-family: 'Courier New', Courier, monospace;
        letter-spacing: 0.05em;
        font-size: 12px;
    }
    .dato-vacio {
        color: #CBD5E1;
        font-weight: 400;
        font-style: italic;
        font-size: 12px;
    }

    /* ─── zona centrada de la tarjeta de estatus IMSS ─── */
    .estatus-centro {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        padding: 32px 20px 24px;
        gap: 0;
    }
    .estatus-icono-grande {
        width: 72px; height: 72px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        margin-bottom: 16px;
    }
    .estatus-icono-grande i { width: 36px; height: 36px; }

    .estatus-badge-grande {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 22px;
        border-radius: 50px;
        font-size: 16px;
        font-weight: 700;
        margin-bottom: 14px;
    }
    .estatus-badge-grande i { width: 18px; height: 18px; }

    .estatus-desc {
        font-size: 13px;
        color: #64748B;
        line-height: 1.6;
        margin-bottom: 20px;
        max-width: 300px;
    }

    .estatus-fecha-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #F8FAFC;
        border: 1px solid #E2E8F0;
        border-radius: 8px;
        padding: 8px 16px;
        font-size: 13px;
        color: #475569;
    }
    .estatus-fecha-chip i { width: 14px; height: 14px; color: #94A3B8; }

    /* ─── mensaje cuando el alumno no tiene datos vinculados ─── */
    .sin-datos {
        text-align: center; padding: 40px 20px; color: #94A3B8;
    }
    .sin-datos i { display: block; margin: 0 auto 12px; width: 40px; height: 40px; }
</style>

<!-- Bienvenida -->
<div class="dash-bienvenida">
    <div>
        <h1>¡Hola, <?= htmlspecialchars(explode(' ', $sessionUser['nombre_completo'] ?? 'Alumno')[0]) ?>!</h1>
        <p>
            <?php if ($alumno && $alumno['nombre_carrera']): ?>
                <?= htmlspecialchars($alumno['nombre_carrera']) ?>
            <?php else: ?>
                Instituto Tecnológico de Chetumal
            <?php endif; ?>
        </p>
    </div>
    <div class="dash-bienvenida-icono">
        <i data-lucide="graduation-cap" style="width:32px;height:32px;"></i>
    </div>
</div>

<div class="dash-grid">

    <!-- ── Mis datos ── -->
    <div class="info-card">
        <div class="info-card-header">
            <i data-lucide="user-circle" style="width:18px;height:18px;"></i>
            <h3>Mis datos</h3>
        </div>
        <div class="info-card-body">
            <?php if ($alumno): ?>

                <div class="dato-fila">
                    <div class="dato-icono"><i data-lucide="user"></i></div>
                    <div class="dato-texto">
                        <div class="dato-label">Nombre completo</div>
                        <div class="dato-valor">
                            <?= htmlspecialchars(trim(
                                ($alumno['nombre'] ?? '') . ' ' .
                                ($alumno['apellido_paterno'] ?? '') . ' ' .
                                ($alumno['apellido_materno'] ?? '')
                            )) ?>
                        </div>
                    </div>
                </div>

                <div class="dato-fila">
                    <div class="dato-icono"><i data-lucide="hash"></i></div>
                    <div class="dato-texto">
                        <div class="dato-label">No. Control</div>
                        <div class="dato-valor mono"><?= htmlspecialchars($alumno['numero_control']) ?></div>
                    </div>
                </div>

                <div class="dato-fila">
                    <div class="dato-icono"><i data-lucide="fingerprint"></i></div>
                    <div class="dato-texto">
                        <div class="dato-label">CURP</div>
                        <div class="dato-valor mono">
                            <?php if (!empty($alumno['curp'])): ?>
                                <?= htmlspecialchars($alumno['curp']) ?>
                            <?php else: ?>
                                <span class="dato-vacio">Sin registrar</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="dato-fila">
                    <div class="dato-icono"><i data-lucide="graduation-cap"></i></div>
                    <div class="dato-texto">
                        <div class="dato-label">Carrera</div>
                        <div class="dato-valor"><?= htmlspecialchars($alumno['nombre_carrera'] ?? '—') ?></div>
                    </div>
                </div>

                <div class="dato-fila">
                    <div class="dato-icono"><i data-lucide="mail"></i></div>
                    <div class="dato-texto">
                        <div class="dato-label">Email</div>
                        <div class="dato-valor">
                            <?php if (!empty($alumno['email'])): ?>
                                <?= htmlspecialchars($alumno['email']) ?>
                            <?php else: ?>
                                <span class="dato-vacio">Sin registrar</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="dato-fila">
                    <div class="dato-icono"><i data-lucide="phone"></i></div>
                    <div class="dato-texto">
                        <div class="dato-label">Teléfono</div>
                        <div class="dato-valor">
                            <?php if (!empty($alumno['telefono'])): ?>
                                <?= htmlspecialchars($alumno['telefono']) ?>
                            <?php else: ?>
                                <span class="dato-vacio">Sin registrar</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="dato-fila">
                    <div class="dato-icono"><i data-lucide="shield"></i></div>
                    <div class="dato-texto">
                        <div class="dato-label">NSS</div>
                        <div class="dato-valor mono">
                            <?php if ($datosImss && !empty($datosImss['nss'])): ?>
                                <?= htmlspecialchars($datosImss['nss']) ?>
                            <?php else: ?>
                                <span class="dato-vacio">Sin registrar</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- Sin alumno vinculado: mostrar información de la cuenta de usuario -->
                <div class="dato-fila">
                    <div class="dato-icono"><i data-lucide="user"></i></div>
                    <div class="dato-texto">
                        <div class="dato-label">Nombre completo</div>
                        <div class="dato-valor"><?= htmlspecialchars($sessionUser['nombre_completo'] ?? '—') ?></div>
                    </div>
                </div>
                <div class="dato-fila">
                    <div class="dato-icono"><i data-lucide="at-sign"></i></div>
                    <div class="dato-texto">
                        <div class="dato-label">Usuario</div>
                        <div class="dato-valor mono"><?= htmlspecialchars($sessionUser['username'] ?? '—') ?></div>
                    </div>
                </div>
                <div class="dato-fila">
                    <div class="dato-icono"><i data-lucide="mail"></i></div>
                    <div class="dato-texto">
                        <div class="dato-label">Correo electrónico</div>
                        <div class="dato-valor"><?= htmlspecialchars($sessionUser['email'] ?? '—') ?></div>
                    </div>
                </div>
                <div class="dato-fila">
                    <div class="dato-icono"><i data-lucide="shield"></i></div>
                    <div class="dato-texto">
                        <div class="dato-label">Rol</div>
                        <div class="dato-valor"><?= htmlspecialchars($sessionUser['rol_nombre'] ?? '—') ?></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Estatus IMSS ── -->
    <div class="info-card" style="border-top: 3px solid <?= $estatusColor ?>;">
        <div class="info-card-header">
            <i data-lucide="shield-check" style="width:18px;height:18px;"></i>
            <h3>Estatus IMSS</h3>
        </div>
        <div class="estatus-centro">

            <!-- Ícono grande -->
            <div class="estatus-icono-grande" style="background:<?= $estatusBg ?>; color:<?= $estatusColor ?>;">
                <i data-lucide="<?= $estatusIcono ?>"></i>
            </div>

            <!-- Badge -->
            <div class="estatus-badge-grande" style="background:<?= $estatusBg ?>; color:<?= $estatusColor ?>;">
                <i data-lucide="<?= $estatusIcono ?>"></i>
                <?= $estatusLabel ?>
            </div>

            <!-- Descripción -->
            <p class="estatus-desc"><?= htmlspecialchars($estatusDesc) ?></p>

            <!-- Fecha (solo si hay estatus real) -->
            <?php if (!empty($estatusFechaLabel)): ?>
                <div class="estatus-fecha-chip">
                    <i data-lucide="calendar"></i>
                    <?= $estatusFechaLabel ?>
                </div>
            <?php endif; ?>

        </div>
    </div>

</div>

<!--  rp -->
<div style="margin-top:20px;text-align:right;">
    <button class="btn btn-outline" onclick="abrirModalReporte()"
            style="border-color:#EF4444;color:#EF4444;gap:8px;">
        <i data-lucide="flag" style="width:15px;height:15px;"></i>
        Reportar datos incorrectos
    </button>
</div>

<!-- Modal reporte -->
<div class="modal-overlay" id="modalReporte">
    <div class="modal" style="max-width:480px;">
        <div class="modal-header">
            <h3 class="modal-title">Reportar datos incorrectos</h3>
            <button class="modal-close" onclick="cerrarModalReporte()">
                <i data-lucide="x"></i>
            </button>
        </div>
        <div class="modal-body">
            <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px;">
                Indica qué dato está incorrecto y descríbelo. Se notificará al área de Servicios Escolares para corregirlo.
            </p>
            <div class="form-group">
                <label class="form-label">¿Qué datos están incorrectos? <span style="color:var(--text-muted);font-weight:400;">(puedes marcar varios)</span></label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;background:var(--bg-secondary,#F8FAFC);border:1px solid var(--border-color,#E2E8F0);border-radius:8px;padding:12px;">
                    <?php foreach ([
                        'Nombre'       => 'user',
                        'CURP'         => 'fingerprint',
                        'NSS'          => 'shield',
                        'Carrera'      => 'graduation-cap',
                        'Email'        => 'mail',
                        'Teléfono'     => 'phone',
                        'Estatus IMSS' => 'shield-check',
                        'Otro'         => 'more-horizontal',
                    ] as $campo => $icono): ?>
                    <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;padding:4px 0;">
                        <input type="checkbox" class="reporte-campo-check" value="<?= $campo ?>"
                               style="width:15px;height:15px;cursor:pointer;accent-color:#EF4444;">
                        <i data-lucide="<?= $icono ?>" style="width:14px;height:14px;color:var(--text-muted,#94A3B8);flex-shrink:0;"></i>
                        <?= $campo ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Descripción del problema *</label>
                <textarea id="reporteDescripcion" class="form-control"
                          rows="4" maxlength="500"
                          placeholder="Explica qué está mal y cuál es el valor correcto..."></textarea>
                <small class="form-text" id="reporteContador" style="text-align:right;display:block;">0 / 500</small>
            </div>
            <div id="reporteMensaje" style="display:none;border-radius:8px;padding:12px 14px;font-size:13px;margin-top:4px;"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="cerrarModalReporte()">Cancelar</button>
            <button type="button" class="btn btn-primary" id="reporteBtnEnviar"
                    onclick="enviarReporte()"
                    style="background:#EF4444;border-color:#EF4444;">
                <i data-lucide="send"></i>
                Enviar reporte
            </button>
        </div>
    </div>
</div>

<script>
const API_NOTIF_REPORTE = '<?= URL_BASE ?>api/notificaciones.php?action=reportar_datos';

function abrirModalReporte() {
    document.querySelectorAll('.reporte-campo-check').forEach(cb => cb.checked = false);
    document.getElementById('reporteDescripcion').value = '';
    document.getElementById('reporteContador').textContent = '0 / 500';
    document.getElementById('reporteMensaje').style.display = 'none';
    document.getElementById('reporteBtnEnviar').disabled = false;
    document.getElementById('reporteBtnEnviar').style.display = '';
    document.getElementById('reporteBtnEnviar').innerHTML = '<i data-lucide="send"></i> Enviar reporte';
    document.getElementById('modalReporte').classList.add('active');
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function cerrarModalReporte() {
    document.getElementById('modalReporte').classList.remove('active');
}

document.getElementById('reporteDescripcion')?.addEventListener('input', function() {
    document.getElementById('reporteContador').textContent = this.value.length + ' / 500';
});

async function enviarReporte() {
    const campos      = [...document.querySelectorAll('.reporte-campo-check:checked')].map(cb => cb.value);
    const descripcion = document.getElementById('reporteDescripcion').value.trim();
    const msgEl       = document.getElementById('reporteMensaje');
    const btnEnviar   = document.getElementById('reporteBtnEnviar');

    msgEl.style.display = 'none';

    if (!descripcion) {
        msgEl.style.cssText = 'display:block;background:#FEE2E2;color:#991B1B;border-radius:8px;padding:12px 14px;font-size:13px;margin-top:4px;';
        msgEl.textContent = 'Por favor describe el problema antes de enviar.';
        return;
    }

    btnEnviar.disabled = true;
    btnEnviar.innerHTML = '<i data-lucide="loader" style="width:15px;height:15px;animation:spin 1s linear infinite;"></i> Enviando...';
    if (typeof lucide !== 'undefined') lucide.createIcons();

    try {
        const resp = await fetch(API_NOTIF_REPORTE, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ campos, descripcion })
        });
        const data = await resp.json();

        if (data.success) {
            msgEl.style.cssText = 'display:block;background:#DCFCE7;color:#166534;border-radius:8px;padding:12px 14px;font-size:13px;margin-top:4px;';
            msgEl.textContent = '✓ Reporte enviado. Servicios Escolares fue notificado y revisará tu caso.';
            btnEnviar.style.display = 'none';
            setTimeout(cerrarModalReporte, 2500);
        } else {
            msgEl.style.cssText = 'display:block;background:#FEE2E2;color:#991B1B;border-radius:8px;padding:12px 14px;font-size:13px;margin-top:4px;';
            msgEl.textContent = data.message || 'Error al enviar el reporte.';
            btnEnviar.disabled = false;
            btnEnviar.innerHTML = '<i data-lucide="send"></i> Enviar reporte';
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    } catch (e) {
        msgEl.style.cssText = 'display:block;background:#FEE2E2;color:#991B1B;border-radius:8px;padding:12px 14px;font-size:13px;margin-top:4px;';
        msgEl.textContent = 'Error de conexión. Intenta de nuevo.';
        btnEnviar.disabled = false;
        btnEnviar.innerHTML = '<i data-lucide="send"></i> Enviar reporte';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
}

document.getElementById('modalReporte').addEventListener('click', e => {
    if (e.target === e.currentTarget) cerrarModalReporte();
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') cerrarModalReporte();
});
</script>

<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
