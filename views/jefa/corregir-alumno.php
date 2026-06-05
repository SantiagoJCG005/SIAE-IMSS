<?php


$tituloPagina = 'Corregir datos de alumno';

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requerirLogin();
requerirRol([ROL_JEFA_SERVICIOS, ROL_ADMIN_SERVICIOS, ROL_SUPERADMIN]);

$conexion  = obtenerConexion();
$idNotif   = intval($_GET['id'] ?? 0);
$idUsuario = obtenerIdUsuarioActual();

if (!$idNotif) {
    header('Location: ' . URL_BASE . 'views/jefa/notificaciones.php');
    exit;
}


$stmtN = $conexion->prepare("
    SELECT n.*, u.nombre_completo AS nombre_origen, u.username AS nc_origen
    FROM notificaciones n
    LEFT JOIN usuarios u ON u.id_usuario = n.id_usuario_origen
    WHERE n.id_notificacion = ? AND n.id_usuario_destino = ?
    LIMIT 1
");
$stmtN->execute([$idNotif, $idUsuario]);
$notif = $stmtN->fetch();

if (!$notif || $notif['referencia_tipo'] !== 'reporte_alumno') {
    header('Location: ' . URL_BASE . 'views/jefa/notificaciones.php');
    exit;
}

$extra     = json_decode($notif['datos_extra'] ?? '{}', true) ?: [];
$nc        = $extra['nc'] ?? $notif['nc_origen'] ?? '';
// Compatibilidad: formato nuevo (campos[]) y antiguo (campo string)
$camposRep = $extra['campos'] ?? (isset($extra['campo']) && $extra['campo'] ? [$extra['campo']] : []);


$descripcion = '';
if (preg_match('/Descripción:\s*(.+)$/s', $notif['mensaje'], $m)) {
    $descripcion = trim($m[1]);
}


$alumno   = null;
$carreras = [];
$error    = '';

if ($nc) {
    try {
        $stmtA = $conexion->prepare("
            SELECT a.*, c.nombre AS nombre_carrera, di.nss
            FROM alumnos a
            LEFT JOIN carreras  c  ON c.id_carrera  = a.id_carrera
            LEFT JOIN datos_imss di ON di.id_alumno = a.id_alumno
            WHERE a.numero_control = ?
            LIMIT 1
        ");
        $stmtA->execute([$nc]);
        $alumno = $stmtA->fetch();

        $carreras = $conexion->query(
            "SELECT id_carrera, nombre FROM carreras WHERE activo = 1 ORDER BY nombre"
        )->fetchAll();
    } catch (Exception $e) {
        $error = 'Error al cargar datos del alumno.';
    }
} else {
    $error = 'El reporte no incluye un número de control válido.';
}


$mensajeGuardado = '';
$tipoMensaje     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $alumno) {
    try {
        $idAlumno = (int) $alumno['id_alumno'];

        $conexion->prepare("
            UPDATE alumnos SET
                nombre           = ?,
                apellido_paterno = ?,
                apellido_materno = ?,
                curp             = ?,
                id_carrera       = ?,
                email            = ?,
                telefono         = ?
            WHERE id_alumno = ?
        ")->execute([
            trim($_POST['nombre']           ?? ''),
            trim($_POST['apellido_paterno'] ?? ''),
            trim($_POST['apellido_materno'] ?? ''),
            strtoupper(trim($_POST['curp']  ?? '')),
            ($_POST['id_carrera'] ? (int)$_POST['id_carrera'] : null),
            trim($_POST['email']            ?? ''),
            trim($_POST['telefono']         ?? ''),
            $idAlumno,
        ]);

        // NSS
        $nss = trim($_POST['nss'] ?? '');
        if ($nss !== '') {
            $existe = $conexion->prepare("SELECT id_alumno FROM datos_imss WHERE id_alumno = ? LIMIT 1");
            $existe->execute([$idAlumno]);
            if ($existe->fetch()) {
                $conexion->prepare("UPDATE datos_imss SET nss = ? WHERE id_alumno = ?")->execute([$nss, $idAlumno]);
            } else {
                $conexion->prepare("INSERT INTO datos_imss (id_alumno, nss) VALUES (?, ?)")->execute([$idAlumno, $nss]);
            }
        }

        // Marcar notificación como leída
        $conexion->prepare("UPDATE notificaciones SET leida = 1, fecha_lectura = NOW() WHERE id_notificacion = ?")
                 ->execute([$idNotif]);

        registrarEnBitacora('EDITAR_ALUMNO', "Datos corregidos del alumno $nc (reporte #{$idNotif})");

        $mensajeGuardado = 'Datos actualizados correctamente.';
        $tipoMensaje     = 'exito';

        // Recargar datos frescos
        $stmtA->execute([$nc]);
        $alumno = $stmtA->fetch();

    } catch (Exception $e) {
        $mensajeGuardado = 'Error al guardar: ' . $e->getMessage();
        $tipoMensaje     = 'error';
    }
}

include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar-jefa.php';
?>

<div class="page-header" style="display:flex;align-items:center;gap:12px;">
    <a href="<?= URL_BASE ?>views/jefa/notificaciones.php" class="btn btn-ghost btn-icon" title="Volver">
        <i data-lucide="arrow-left"></i>
    </a>
    <div>
        <h1 class="page-title">Corregir datos del alumno</h1>
        <p class="page-subtitle">Reporte de datos incorrectos — No. Control: <strong><?= htmlspecialchars($nc) ?></strong></p>
    </div>
</div>

<?php if ($mensajeGuardado): ?>
<div style="background:<?= $tipoMensaje === 'exito' ? '#DCFCE7' : '#FEE2E2' ?>;
            color:<?= $tipoMensaje === 'exito' ? '#166534' : '#991B1B' ?>;
            border-radius:10px;padding:14px 18px;margin-bottom:20px;font-size:14px;display:flex;align-items:center;gap:10px;">
    <i data-lucide="<?= $tipoMensaje === 'exito' ? 'check-circle' : 'alert-circle' ?>" style="width:18px;height:18px;flex-shrink:0;"></i>
    <?= htmlspecialchars($mensajeGuardado) ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:20px;align-items:start;">

    
    <div class="card" style="border-top:3px solid #F59E0B;">
        <div class="card-header">
            <h3 class="card-title" style="display:flex;align-items:center;gap:8px;">
                <i data-lucide="flag" style="width:16px;height:16px;color:#B45309;"></i>
                Reporte del alumno
            </h3>
        </div>
        <div class="card-body" style="font-size:13px;">

            <div style="margin-bottom:14px;">
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px;">Alumno</div>
                <div style="font-weight:600;"><?= htmlspecialchars($notif['nombre_origen'] ?? '—') ?></div>
                <div style="color:var(--text-muted);">No. Control: <?= htmlspecialchars($nc) ?></div>
            </div>

            
            <div style="margin-bottom:14px;">
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:8px;">Campos reportados como incorrectos</div>

                <?php if (!empty($camposRep) && $alumno):
                    // Mapa campo → valor actual del alumno
                    $valorActual = [
                        'Nombre'       => trim(($alumno['nombre'] ?? '') . ' ' . ($alumno['apellido_paterno'] ?? '') . ' ' . ($alumno['apellido_materno'] ?? '')),
                        'CURP'         => $alumno['curp']          ?? '—',
                        'NSS'          => $alumno['nss']           ?? '—',
                        'Carrera'      => $alumno['nombre_carrera'] ?? '—',
                        'Email'        => $alumno['email']         ?? '—',
                        'Teléfono'     => $alumno['telefono']      ?? '—',
                        'Estatus IMSS' => 'Ver tabla de alumnos',
                        'Otro'         => '—',
                    ];
                ?>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <?php foreach ($camposRep as $campo): ?>
                    <div style="background:#FEF3C7;border:1px solid #FCD34D;border-radius:8px;padding:8px 12px;">
                        <div style="display:flex;align-items:center;gap:6px;margin-bottom:3px;">
                            <i data-lucide="alert-triangle" style="width:13px;height:13px;color:#B45309;flex-shrink:0;"></i>
                            <span style="font-weight:700;font-size:12px;color:#92400E;"><?= htmlspecialchars($campo) ?></span>
                        </div>
                        <div style="font-size:11px;color:var(--text-muted);margin-bottom:1px;">Valor actual en el sistema:</div>
                        <div style="font-size:12px;color:#78350F;font-family:monospace;word-break:break-all;">
                            <?= htmlspecialchars($valorActual[$campo] ?? '—') ?: '<em style="color:var(--text-muted);">Sin registrar</em>' ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php elseif (empty($camposRep)): ?>
                <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:10px 12px;font-size:12px;color:var(--text-muted);font-style:italic;">
                    El alumno no especificó campos concretos — revisar la nota.
                </div>
                <?php else: ?>
                <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:10px 12px;font-size:12px;color:var(--text-muted);font-style:italic;">
                    Cargando valores actuales...
                </div>
                <?php endif; ?>
            </div>

            <?php if ($descripcion): ?>
            <div>
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px;">Nota del alumno</div>
                <div style="background:#FFFBEB;border:1px solid #FDE68A;border-radius:8px;padding:10px 12px;color:#78350F;line-height:1.6;">
                    <?= nl2br(htmlspecialchars($descripcion)) ?>
                </div>
            </div>
            <?php endif; ?>

            <div style="margin-top:14px;font-size:11px;color:var(--text-muted);">
                Reportado: <?= date('d/m/Y H:i', strtotime($notif['fecha_creacion'])) ?>
            </div>
        </div>
    </div>

    
    <div class="card">
        <div class="card-header">
            <h3 class="card-title" style="display:flex;align-items:center;gap:8px;">
                <i data-lucide="user-pen" style="width:16px;height:16px;"></i>
                Datos del alumno
            </h3>
        </div>
        <div class="card-body">

            <?php if ($error): ?>
            <div style="background:#FEE2E2;color:#991B1B;border-radius:8px;padding:12px 14px;font-size:13px;">
                <i data-lucide="alert-circle" style="width:15px;height:15px;vertical-align:middle;margin-right:6px;"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php elseif ($alumno): ?>

            <form method="POST">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">

                    <div class="form-group">
                        <label class="form-label <?= in_array('Nombre', $camposRep) ? 'text-danger' : '' ?>">
                            Nombre(s) * <?= in_array('Nombre', $camposRep) ? '<span style="color:#EF4444;">⚠ reportado</span>' : '' ?>
                        </label>
                        <input type="text" name="nombre" class="form-control <?= in_array('Nombre', $camposRep) ? 'border-danger' : '' ?>"
                               value="<?= htmlspecialchars($_POST['nombre'] ?? $alumno['nombre'] ?? '') ?>"
                               maxlength="100" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="color:var(--text-muted);">No. Control</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($nc) ?>"
                               disabled style="background:#F8FAFC;color:var(--text-muted);">
                    </div>

                    <div class="form-group">
                        <label class="form-label <?= in_array('Nombre', $camposRep) ? 'text-danger' : '' ?>">
                            Apellido Paterno * <?= in_array('Nombre', $camposRep) ? '<span style="color:#EF4444;">⚠ reportado</span>' : '' ?>
                        </label>
                        <input type="text" name="apellido_paterno" class="form-control <?= in_array('Nombre', $camposRep) ? 'border-danger' : '' ?>"
                               value="<?= htmlspecialchars($_POST['apellido_paterno'] ?? $alumno['apellido_paterno'] ?? '') ?>"
                               maxlength="80" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Apellido Materno</label>
                        <input type="text" name="apellido_materno" class="form-control"
                               value="<?= htmlspecialchars($_POST['apellido_materno'] ?? $alumno['apellido_materno'] ?? '') ?>"
                               maxlength="80">
                    </div>

                    <div class="form-group">
                        <label class="form-label <?= in_array('CURP', $camposRep) ? 'text-danger' : '' ?>">
                            CURP <?= in_array('CURP', $camposRep) ? '<span style="color:#EF4444;">⚠ reportado</span>' : '' ?>
                        </label>
                        <input type="text" name="curp" class="form-control <?= in_array('CURP', $camposRep) ? 'border-danger' : '' ?>"
                               value="<?= htmlspecialchars($_POST['curp'] ?? $alumno['curp'] ?? '') ?>"
                               maxlength="18" style="text-transform:uppercase;" placeholder="18 caracteres">
                    </div>

                    <div class="form-group">
                        <label class="form-label <?= in_array('NSS', $camposRep) ? 'text-danger' : '' ?>">
                            NSS <?= in_array('NSS', $camposRep) ? '<span style="color:#EF4444;">⚠ reportado</span>' : '' ?>
                        </label>
                        <input type="text" name="nss" class="form-control <?= in_array('NSS', $camposRep) ? 'border-danger' : '' ?>"
                               value="<?= htmlspecialchars($_POST['nss'] ?? $alumno['nss'] ?? '') ?>"
                               maxlength="11" placeholder="11 dígitos">
                    </div>

                    <div class="form-group" style="grid-column:1/-1;">
                        <label class="form-label <?= in_array('Carrera', $camposRep) ? 'text-danger' : '' ?>">
                            Carrera <?= in_array('Carrera', $camposRep) ? '<span style="color:#EF4444;">⚠ reportado</span>' : '' ?>
                        </label>
                        <select name="id_carrera" class="form-control form-select <?= in_array('Carrera', $camposRep) ? 'border-danger' : '' ?>">
                            <option value="">— Sin asignar —</option>
                            <?php foreach ($carreras as $c): ?>
                            <option value="<?= $c['id_carrera'] ?>"
                                <?= (($_POST['id_carrera'] ?? $alumno['id_carrera']) == $c['id_carrera']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label <?= in_array('Email', $camposRep) ? 'text-danger' : '' ?>">
                            Email <?= in_array('Email', $camposRep) ? '<span style="color:#EF4444;">⚠ reportado</span>' : '' ?>
                        </label>
                        <input type="email" name="email" class="form-control <?= in_array('Email', $camposRep) ? 'border-danger' : '' ?>"
                               value="<?= htmlspecialchars($_POST['email'] ?? $alumno['email'] ?? '') ?>"
                               maxlength="150">
                    </div>

                    <div class="form-group">
                        <label class="form-label <?= in_array('Teléfono', $camposRep) ? 'text-danger' : '' ?>">
                            Teléfono <?= in_array('Teléfono', $camposRep) ? '<span style="color:#EF4444;">⚠ reportado</span>' : '' ?>
                        </label>
                        <input type="text" name="telefono" class="form-control <?= in_array('Teléfono', $camposRep) ? 'border-danger' : '' ?>"
                               value="<?= htmlspecialchars($_POST['telefono'] ?? $alumno['telefono'] ?? '') ?>"
                               maxlength="20">
                    </div>

                </div>

                <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:20px;padding-top:16px;border-top:1px solid var(--border-color);">
                    <a href="<?= URL_BASE ?>views/jefa/notificaciones.php" class="btn btn-ghost">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="save"></i>
                        Guardar cambios
                    </button>
                </div>
            </form>

            <?php else: ?>
            <div style="text-align:center;padding:40px;color:var(--text-muted);">
                <i data-lucide="user-x" style="width:40px;height:40px;display:block;margin:0 auto 12px;"></i>
                No se encontró el expediente del alumno.
            </div>
            <?php endif; ?>

        </div>
    </div>

</div>

<style>
.border-danger { border-color: #EF4444 !important; }
.text-danger   { color: #EF4444 !important; }
</style>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
