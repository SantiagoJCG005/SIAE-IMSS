<?php
/**
 * SIAE-IMSS - Dashboard Estudiante (ROL 5)
 */

$tituloPagina = 'Mi Estatus IMSS';

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requerirRol([ROL_ESTUDIANTE]);

$conexion      = obtenerConexion();
$sessionUser   = obtenerUsuarioActual();
$numeroControl = $sessionUser['username'] ?? '';

$alumno      = null;
$datosImss   = null;
$estatusImss = null;

try {
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
        $stmt2 = $conexion->prepare("SELECT nss FROM datos_imss WHERE id_alumno = ? LIMIT 1");
        $stmt2->execute([$alumno['id_alumno']]);
        $datosImss = $stmt2->fetch();

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
    // sin datos
}

// Config de estatus
$estatusLabel   = 'En proceso';
$estatusColor   = '#64748B';
$estatusBg      = '#F1F5F9';
$estatusIcono   = 'clock';
$estatusDesc    = 'Tu trámite de afiliación al IMSS está en proceso. Servicios Escolares te notificará cuando tu seguro social quede activo.';
$estatusFechaLabel = '';

if (!empty($estatusImss)) {
    $fechaFmt = date('d/m/Y', strtotime($estatusImss['fecha_movimiento']));
    switch ($estatusImss['estatus']) {
        case 'alta':
            $estatusLabel      = 'Alta IMSS';
            $estatusColor      = '#16A34A';
            $estatusBg         = '#F0FDF4';
            $estatusIcono      = 'shield-check';
            $estatusDesc       = 'Tu seguro social está activo. Estás dado de alta ante el IMSS por el Tecnológico de Chetumal.';
            $estatusFechaLabel = "Fecha de alta: <strong>$fechaFmt</strong>";
            break;
        case 'baja':
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
    /* ── Bienvenida ── */
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

    /* ── Grid ── */
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

    /* ── Tarjetas ── */
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

    /* ── Filas de datos ── */
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

    /* ── Tarjeta Estatus — centrado ── */
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

    /* sin datos */
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
                <div class="sin-datos">
                    <i data-lucide="user-x"></i>
                    <p style="font-size:14px;">No se encontraron datos vinculados a tu cuenta.</p>
                    <p style="font-size:12px;margin-top:4px;">Contacta al área de Servicios Escolares.</p>
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

<?php include __DIR__ . '/../layouts/footer.php'; ?>
