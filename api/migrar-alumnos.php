<?php
/**
 * SIAE-IMSS - API Migración de Alumnos desde Excel del SII
 * Crea cuentas de estudiante a partir de un Excel exportado del SII.
 * Columnas requeridas: No. Control, Nombre. El resto es opcional pero se aprovecha si existe.
 */

// PHP - responder en formato JSON
header('Content-Type: application/json');

// PHP - cargar autenticacion, funciones y modulo de correo
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mail.php';

// PHP - verificar sesion activa
if (!estaLogueado()) {
    respuestaError('No autorizado', 401);
}

// PHP - solo el Superadmin y roles con crud_usuarios pueden ejecutar migraciones masivas
if (!tieneAlgunRol([ROL_SUPERADMIN])) {
    respuestaError('Solo el Superadmin puede migrar alumnos', 403);
}

$conexion = obtenerConexion();
// PHP - la unica accion valida es 'procesar'
$accion   = $_POST['action'] ?? obtenerGet('action', '');

if ($accion !== 'procesar') {
    respuestaError('Accion no valida', 400);
}

// ── Validar archivo ──────────────────────────────────────────────────────────
if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    respuestaError('No se recibió ningún archivo');
}

$archivo   = $_FILES['archivo'];
$extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

if (!in_array($extension, ['xlsx', 'xls'])) {
    respuestaError('Solo se permiten archivos Excel (.xlsx, .xls)');
}

if ($extension !== 'xlsx') {
    respuestaError('Formato .xls no soportado, use .xlsx');
}

// ── Leer Excel ───────────────────────────────────────────────────────────────
try {
    $rutaTemporal = sys_get_temp_dir() . '/' . uniqid('sii_') . '.xlsx';
    move_uploaded_file($archivo['tmp_name'], $rutaTemporal);

    require_once __DIR__ . '/../includes/SimpleXLSX.php';
    $xlsx = SimpleXLSX::parse($rutaTemporal);
    @unlink($rutaTemporal);

    if (!$xlsx) {
        respuestaError('Error al leer el archivo: ' . SimpleXLSX::parseError());
    }

    $filas = $xlsx->rows();

    if (count($filas) < 2) {
        respuestaError('El archivo no contiene datos');
    }
} catch (Exception $e) {
    respuestaError('Error al procesar el archivo: ' . $e->getMessage());
}

// ── Mapeo flexible de columnas ───────────────────────────────────────────────
// Alias conocidos por campo. Se detectan por contención de subcadena en el encabezado.
$aliasMap = [
    'numero_control'   => ['NO. CONTROL', 'NUM CONTROL', 'NUMERO CONTROL', 'N° CONTROL', 'N.CONTROL', 'NOCONTROL', 'NO CONTROL'],
    'nombre'           => ['NOMBRE(S)', 'NOMBRE'],
    'apellido_paterno' => ['APELLIDO PATERNO', 'A. PATERNO', 'AP. PATERNO', 'PATERNO'],
    'apellido_materno' => ['APELLIDO MATERNO', 'A. MATERNO', 'AP. MATERNO', 'MATERNO'],
    'email'            => ['EMAIL', 'CORREO ELECTRONICO', 'CORREO'],
    'telefono'         => ['TELEFONO', 'CELULAR', 'TEL.', 'TEL'],
    'nss'              => ['NSS', 'NO. SEGURO SOCIAL', 'NUMERO AFILIACION', 'N.S.S.', 'AFILIACION'],
    'carrera'          => ['CARRERA', 'PROGRAMA EDUCATIVO', 'PROGRAMA', 'PLAN DE ESTUDIOS'],
    'curp'             => ['CURP', 'C.U.R.P', 'CLAVE CURP', 'CLAVE ÚNICA', 'CLAVE UNICA'],
    'sexo'             => ['SEXO', 'GENERO', 'GÉNERO'],
];

$encabezados = array_map('strtoupper', array_map('trim', $filas[0]));
$colIdx = array_fill_keys(array_keys($aliasMap), -1);

foreach ($encabezados as $idx => $enc) {
    foreach ($aliasMap as $campo => $aliases) {
        if ($colIdx[$campo] >= 0) continue;
        foreach ($aliases as $alias) {
            if (str_contains($enc, $alias)) {
                $colIdx[$campo] = $idx;
                break;
            }
        }
    }
}

// Columnas encontradas / no encontradas (para el reporte)
$columnasEncontradas   = array_keys(array_filter($colIdx, fn($v) => $v >= 0));
$columnasNoEncontradas = array_keys(array_filter($colIdx, fn($v) => $v < 0));

if ($colIdx['numero_control'] < 0) {
    respuestaError(
        'No se encontró la columna "No. Control" en el archivo. ' .
        'Encabezados detectados: ' . implode(', ', $filas[0])
    );
}

// ── Cargar carreras para lookup por nombre ────────────────────────────────────
$carrerasStmt = $conexion->query("SELECT id_carrera, nombre FROM carreras WHERE activo = 1");
$carrerasMap  = [];
foreach ($carrerasStmt->fetchAll() as $c) {
    $carrerasMap[strtoupper(trim($c['nombre']))] = $c['id_carrera'];
}

// ── Procesar filas ───────────────────────────────────────────────────────────
function obtenerCelda(array $fila, int $idx): string {
    return $idx >= 0 ? trim($fila[$idx] ?? '') : '';
}

// Verificar SMTP antes de empezar (para advertir en el resultado si no está listo)
$smtpDisponible = true;
try {
    $cfgSmtp = obtenerConexion()->query(
        "SELECT COUNT(*) FROM configuracion WHERE tipo='smtp' AND clave='smtp_servidor' AND valor != ''"
    )->fetchColumn();
    if (!$cfgSmtp) $smtpDisponible = false;
} catch (Exception $e) {
    $smtpDisponible = false;
}

// PHP - contadores del resumen que se enviaran al cliente al terminar
$resumen = [
    'nuevos'              => 0,  // alumnos insertados por primera vez
    'actualizados'        => 0,  // alumnos que ya existian y se actualizaron
    'cuentas_creadas'     => 0,  // cuentas de usuario nuevas creadas
    'ya_existian'         => 0,  // cuentas de usuario que ya existian
    'errores'             => 0,  // filas con error de procesamiento
    'sin_curp'            => 0,  // alumnos importados sin CURP en el Excel
    'correos_enviados'    => 0,  // correos de bienvenida enviados correctamente
    'correos_fallidos'    => 0,  // correos que no se pudieron enviar
    'smtp_disponible'     => $smtpDisponible,
    'detalle_correos'     => [],
    'detalle_errores'     => [],
];

$urlLogin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
          . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
          . URL_BASE . 'views/auth/login.php';

// PHP - procesar cada fila del Excel (la fila 0 es el encabezado, se omite)
for ($i = 1; $i < count($filas); $i++) {
    $fila = $filas[$i];

    // PHP - omitir filas completamente vacias
    $vacia = true;
    foreach ($fila as $celda) {
        if (trim($celda) !== '') { $vacia = false; break; }
    }
    if ($vacia) continue;

    $numeroControl = obtenerCelda($fila, $colIdx['numero_control']);
    if (empty($numeroControl)) continue;

    $nombre          = obtenerCelda($fila, $colIdx['nombre']);
    $apellidoPaterno = obtenerCelda($fila, $colIdx['apellido_paterno']);
    $apellidoMaterno = obtenerCelda($fila, $colIdx['apellido_materno']);
    $email           = obtenerCelda($fila, $colIdx['email']);
    $telefono        = obtenerCelda($fila, $colIdx['telefono']);
    $nss             = obtenerCelda($fila, $colIdx['nss']);
    $carreraNombre   = obtenerCelda($fila, $colIdx['carrera']);
    $curp            = strtoupper(obtenerCelda($fila, $colIdx['curp']));
    $sexoRaw         = strtoupper(obtenerCelda($fila, $colIdx['sexo']));

    // Construir nombre completo
    if ($apellidoPaterno || $apellidoMaterno) {
        $nombreCompleto = trim("$nombre $apellidoPaterno $apellidoMaterno");
    } else {
        $nombreCompleto = $nombre;
    }

    if (empty($nombreCompleto)) {
        $resumen['errores']++;
        $resumen['detalle_errores'][] = "Fila " . $i + 1 . ": sin nombre";
        continue;
    }

    // Normalizar sexo
    $sexo = null;
    if (str_contains($sexoRaw, 'H') || str_contains($sexoRaw, 'M') || str_contains($sexoRaw, 'HOMBRE') || str_contains($sexoRaw, 'MASCULINO')) {
        $sexo = 'H';
    } elseif (str_contains($sexoRaw, 'F') || str_contains($sexoRaw, 'MUJER') || str_contains($sexoRaw, 'FEMENINO')) {
        $sexo = 'M';
    }

    // Lookup carrera
    $idCarrera = null;
    if ($carreraNombre) {
        $idCarrera = $carrerasMap[strtoupper(trim($carreraNombre))] ?? null;
    }

    try {
        $conexion->beginTransaction();

        // ── 1. Crear/actualizar cuenta en usuarios ────────────────────────────
        $stmtUser = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE username = ? LIMIT 1");
        $stmtUser->execute([$numeroControl]);
        $usuarioExistente = $stmtUser->fetch();

        if (!$usuarioExistente) {
            $passwordHash = password_hash($numeroControl, PASSWORD_DEFAULT);
            $emailUsuario = !empty($email) ? $email : ($numeroControl . '@itchetumal.edu.mx');

            $conexion->prepare("
                INSERT INTO usuarios
                    (username, email, password_hash, nombre_completo, id_rol, activo, numero_control, debe_cambiar_password)
                VALUES (?, ?, ?, ?, ?, 1, ?, 0)
            ")->execute([
                $numeroControl,
                $emailUsuario,
                $passwordHash,
                $nombreCompleto,
                ROL_ESTUDIANTE,
                $numeroControl,
            ]);

            $resumen['cuentas_creadas']++;

            // Enviar correo de bienvenida (fuera de la transacción para no bloquearla si falla)
            $conexion->commit();
            if ($smtpDisponible) {
                $html   = plantillaCorreoBienvenidaAlumno($nombreCompleto, $numeroControl, $urlLogin);
                $result = enviarCorreo($emailUsuario, 'Bienvenido al portal SIAE-IMSS — ' . $numeroControl, $html);
                if ($result['success']) {
                    $resumen['correos_enviados']++;
                } else {
                    $resumen['correos_fallidos']++;
                    $resumen['detalle_correos'][] = "$emailUsuario: " . $result['message'];
                }
            }
            $conexion->beginTransaction();
        } else {
            $resumen['ya_existian']++;
        }

        // ── 2. Insertar/actualizar en alumnos ─────────────────────────────────
        $stmtAl = $conexion->prepare("SELECT id_alumno FROM alumnos WHERE numero_control = ? LIMIT 1");
        $stmtAl->execute([$numeroControl]);
        $alumnoExistente = $stmtAl->fetch();

        // CURP requerida por la BD; si no viene, usamos placeholder
        $curpGuardar = !empty($curp) ? $curp : '';
        if (empty($curp)) $resumen['sin_curp']++;

        // sexo requerido por la BD; si no viene, 'H' como fallback
        $sexoGuardar = $sexo ?? 'H';

        // Nombre de carrera tal como viene del Excel (para reconciliar después si no hay match)
        $carreraExcel = $carreraNombre ? trim($carreraNombre) : null;

        if (!$alumnoExistente) {
            $conexion->prepare("
                INSERT INTO alumnos
                    (numero_control, curp, nombre, apellido_paterno, apellido_materno,
                     sexo, email, telefono, id_carrera, carrera_nombre_excel)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $numeroControl, $curpGuardar, $nombre,
                $apellidoPaterno, $apellidoMaterno,
                $sexoGuardar, $email, $telefono, $idCarrera, $carreraExcel,
            ]);
            $idAlumno = (int) $conexion->lastInsertId();
            $resumen['nuevos']++;
        } else {
            $idAlumno = (int) $alumnoExistente['id_alumno'];
            $conexion->prepare("
                UPDATE alumnos SET
                    nombre = ?, apellido_paterno = ?, apellido_materno = ?,
                    email = ?, telefono = ?, id_carrera = COALESCE(?, id_carrera),
                    carrera_nombre_excel = COALESCE(carrera_nombre_excel, ?)
                WHERE id_alumno = ?
            ")->execute([
                $nombre, $apellidoPaterno, $apellidoMaterno,
                $email, $telefono, $idCarrera, $carreraExcel, $idAlumno,
            ]);
            // Actualizar CURP solo si llegó en el Excel
            if (!empty($curp)) {
                $conexion->prepare("UPDATE alumnos SET curp = ? WHERE id_alumno = ?")
                         ->execute([$curp, $idAlumno]);
            }
            $resumen['actualizados']++;
        }

        // ── 3. Insertar/actualizar datos_imss si hay NSS ──────────────────────
        if (!empty($nss)) {
            // NSS: guardar siempre como 11 dígitos (base + DV) para match con acuses PDF
            $nssLimpio = preg_replace('/\D/', '', $nss);
            $nssFull   = substr($nssLimpio, 0, 11); // 11 dígitos completos
            $dv        = strlen($nssLimpio) >= 11
                ? substr($nssLimpio, 10, 1)
                : null;

            $stmtImss = $conexion->prepare("SELECT id_datos_imss, nss FROM datos_imss WHERE id_alumno = ? LIMIT 1");
            $stmtImss->execute([$idAlumno]);
            $existeDI = $stmtImss->fetch();
            if (!$existeDI) {
                $conexion->prepare("
                    INSERT INTO datos_imss (id_alumno, nss, digito_verificador, estado_imss)
                    VALUES (?, ?, ?, 'pendiente')
                ")->execute([$idAlumno, $nssFull, $dv]);
            } elseif (strlen($existeDI['nss']) < strlen($nssFull)) {
                // Actualizar si el NSS existente tiene menos dígitos (10→11)
                $conexion->prepare("
                    UPDATE datos_imss SET nss = ?, digito_verificador = COALESCE(?, digito_verificador)
                    WHERE id_alumno = ?
                ")->execute([$nssFull, $dv, $idAlumno]);
            }
        }

        $conexion->commit();

    } catch (Exception $e) {
        if ($conexion->inTransaction()) $conexion->rollBack();
        $resumen['errores']++;
        $resumen['detalle_errores'][] = "Fila " . $i + 1 . " ($numeroControl): " . $e->getMessage();
    }
}

// ── Reconciliar acuses pendientes con los alumnos recién importados ──────────
// Busca filas 'no_encontrado' en acuse_detalle y las vincula si ahora hay coincidencia.
// Esto permite subir PDFs y Excels en cualquier orden.
$reconcResult = reconciliarAcusesPendientes($conexion);
$resumen['acuses_reconciliados']       = $reconcResult['reconciliados'];
$resumen['errores_reconciliacion']     = $reconcResult['errores'];

registrarEnBitacora(
    'MIGRAR_ALUMNOS',
    "Excel SII: {$archivo['name']} — {$resumen['cuentas_creadas']} cuentas nuevas, " .
    "{$resumen['nuevos']} alumnos nuevos, {$resumen['actualizados']} actualizados, " .
    "{$resumen['errores']} errores, {$reconcResult['reconciliados']} registros IMSS reconciliados"
);

respuestaExitosa([
    'resumen'                 => $resumen,
    'columnas_encontradas'    => $columnasEncontradas,
    'columnas_no_encontradas' => $columnasNoEncontradas,
], 'Migración completada');

// ── Reconciliación de acuses ─────────────────────────────────────────────────
// Revisa TODOS los acuse_detalle con resultado='no_encontrado' y los vincula
// con alumnos que ahora existen en BD (por CURP para altas, por NSS para bajas).
function reconciliarAcusesPendientes(PDO $db): array {
    $reconciliados = 0;
    $errores       = [];

    // Prepared statements reutilizables
    $stmtUpdDet  = $db->prepare("UPDATE acuse_detalle SET id_alumno = ?, resultado = 'procesado' WHERE id_detalle = ?");
    $stmtChkEst  = $db->prepare("SELECT id_estatus, fecha_movimiento FROM estatus_imss_alumnos WHERE nss = ?");
    $stmtInsEst  = $db->prepare("INSERT INTO estatus_imss_alumnos (nss, id_alumno, estatus, fecha_movimiento, id_acuse_origen) VALUES (?, ?, ?, ?, ?)");
    $stmtUpdEst  = $db->prepare("UPDATE estatus_imss_alumnos SET estatus = ?, fecha_movimiento = ?, id_alumno = ?, id_acuse_origen = ? WHERE nss = ?");
    $stmtChkDI   = $db->prepare("SELECT id_datos_imss FROM datos_imss WHERE id_alumno = ? LIMIT 1");
    $stmtInsDI   = $db->prepare("INSERT INTO datos_imss (id_alumno, nss, estado_imss) VALUES (?, ?, 'activo')");
    $stmtUpdDI   = $db->prepare("UPDATE datos_imss SET nss = ?, estado_imss = 'activo' WHERE id_alumno = ?");
    $stmtUpdAcuse = $db->prepare("UPDATE acuses_imss SET procesados = procesados + 1, no_encontrados = GREATEST(0, no_encontrados - 1) WHERE id_acuse = ?");

    // ── 1. Altas: match CURP del PDF contra alumnos.curp ─────────────────────
    $pendAlta = $db->query("
        SELECT ad.id_detalle, ad.nss AS nss_pdf, ad.curp_pdf,
               ai.id_acuse, ai.fecha_recepcion_imss,
               a.id_alumno
        FROM   acuse_detalle ad
        INNER JOIN acuses_imss ai ON ai.id_acuse = ad.id_acuse AND ai.tipo = 'alta'
        INNER JOIN alumnos a      ON a.curp = ad.curp_pdf AND a.curp != ''
        WHERE  ad.resultado = 'no_encontrado'
          AND  ad.curp_pdf IS NOT NULL AND ad.curp_pdf != ''
        ORDER BY ai.fecha_recepcion_imss ASC
    ");
    $filasPendAlta = $pendAlta->fetchAll();
    $pendAlta->closeCursor();

    foreach ($filasPendAlta as $row) {
        try {
            $idAlumno = (int) $row['id_alumno'];
            $nss      = $row['nss_pdf'];
            $fecha    = $row['fecha_recepcion_imss'];

            $stmtUpdDet->execute([$idAlumno, $row['id_detalle']]);

            // Registrar NSS en datos_imss (viene del PDF de alta)
            if (!empty($nss)) {
                $stmtChkDI->execute([$idAlumno]);
                $existeDI = $stmtChkDI->fetch();
                $stmtChkDI->closeCursor();

                if (!$existeDI) {
                    $stmtInsDI->execute([$idAlumno, $nss]);
                } else {
                    $stmtUpdDI->execute([$nss, $idAlumno]);
                }
            }

            // Actualizar estatus_imss_alumnos respetando precedencia de fecha
            actualizarEstatus($stmtChkEst, $stmtInsEst, $stmtUpdEst, $nss, $idAlumno, 'alta', $fecha, $row['id_acuse']);

            $stmtUpdAcuse->execute([$row['id_acuse']]);
            $reconciliados++;
        } catch (Exception $e) {
            $msg = "[Alta-CURP] id_detalle={$row['id_detalle']} NSS={$row['nss_pdf']}: " . $e->getMessage();
            error_log("[SIAE] Reconciliar $msg");
            $errores[] = $msg;
        }
    }

    // ── 1b. Altas: fallback por NSS cuando CURP no coincidió ────────────────────
    // Cubre el caso: Excel importado con NSS pero sin CURP (o CURP distinta a la del PDF)
    // JOIN flexible: compara NSS completo O los primeros 10 dígitos (por si datos_imss tiene 10)
    $pendAltaNss = $db->query("
        SELECT ad.id_detalle, ad.nss AS nss_pdf, ad.curp_pdf,
               ai.id_acuse, ai.fecha_recepcion_imss,
               di.id_alumno
        FROM   acuse_detalle ad
        INNER JOIN acuses_imss ai ON ai.id_acuse = ad.id_acuse AND ai.tipo = 'alta'
        INNER JOIN datos_imss di  ON (di.nss = ad.nss OR di.nss = LEFT(ad.nss, 10))
        WHERE  ad.resultado = 'no_encontrado'
        ORDER BY ai.fecha_recepcion_imss ASC
    ");
    $filasAltaNss = $pendAltaNss->fetchAll();
    $pendAltaNss->closeCursor();

    foreach ($filasAltaNss as $row) {
        try {
            $idAlumno = (int) $row['id_alumno'];
            $nss      = $row['nss_pdf'];
            $fecha    = $row['fecha_recepcion_imss'];

            $stmtUpdDet->execute([$idAlumno, $row['id_detalle']]);

            // Actualizar NSS a 'activo' (ya estaba en datos_imss por el Excel)
            $stmtUpdDI->execute([$nss, $idAlumno]);

            // Actualizar CURP en alumnos si el PDF la tiene y el alumno no la tiene
            if (!empty($row['curp_pdf'])) {
                $db->prepare("UPDATE alumnos SET curp = ? WHERE id_alumno = ? AND (curp IS NULL OR curp = '')")
                   ->execute([$row['curp_pdf'], $idAlumno]);
            }

            actualizarEstatus($stmtChkEst, $stmtInsEst, $stmtUpdEst, $nss, $idAlumno, 'alta', $fecha, $row['id_acuse']);
            $stmtUpdAcuse->execute([$row['id_acuse']]);
            $reconciliados++;
        } catch (Exception $e) {
            error_log("[SIAE] Reconciliar alta NSS id_detalle={$row['id_detalle']}: " . $e->getMessage());
        }
    }

    // ── 2. Bajas: match NSS del PDF contra datos_imss.nss ────────────────────
    // JOIN flexible: compara NSS completo O los primeros 10 dígitos
    $pendBaja = $db->query("
        SELECT ad.id_detalle, ad.nss,
               ai.id_acuse, ai.fecha_recepcion_imss,
               di.id_alumno
        FROM   acuse_detalle ad
        INNER JOIN acuses_imss ai ON ai.id_acuse = ad.id_acuse AND ai.tipo = 'baja'
        INNER JOIN datos_imss di  ON (di.nss = ad.nss OR di.nss = LEFT(ad.nss, 10))
        WHERE  ad.resultado = 'no_encontrado'
        ORDER BY ai.fecha_recepcion_imss ASC
    ");
    $filasBaja = $pendBaja->fetchAll();
    $pendBaja->closeCursor();

    foreach ($filasBaja as $row) {
        try {
            $idAlumno = (int) $row['id_alumno'];
            $nss      = $row['nss'];
            $fecha    = $row['fecha_recepcion_imss'];

            $stmtUpdDet->execute([$idAlumno, $row['id_detalle']]);
            actualizarEstatus($stmtChkEst, $stmtInsEst, $stmtUpdEst, $nss, $idAlumno, 'baja', $fecha, $row['id_acuse']);

            $stmtUpdAcuse->execute([$row['id_acuse']]);
            $reconciliados++;
        } catch (Exception $e) {
            $msg = "[Baja] id_detalle={$row['id_detalle']} NSS={$row['nss']}: " . $e->getMessage();
            error_log("[SIAE] Reconciliar $msg");
            $errores[] = $msg;
        }
    }

    // ── 3. REPARACIÓN: procesados huérfanos sin estatus_imss_alumnos ─────────
    // Cubre el caso donde acuse_detalle tiene resultado='procesado' + id_alumno,
    // pero NO existe el registro correspondiente en estatus_imss_alumnos.
    $huerfanos = $db->query("
        SELECT ad.nss, ad.id_alumno, ai.tipo, ai.fecha_recepcion_imss, ai.id_acuse
        FROM acuse_detalle ad
        INNER JOIN acuses_imss ai ON ai.id_acuse = ad.id_acuse
        LEFT JOIN estatus_imss_alumnos e ON e.id_alumno = ad.id_alumno
        WHERE ad.resultado = 'procesado'
          AND ad.id_alumno IS NOT NULL
          AND e.id_alumno IS NULL
        ORDER BY ai.fecha_recepcion_imss ASC
    ");
    $filasHuerfanos = $huerfanos->fetchAll();
    $huerfanos->closeCursor();

    foreach ($filasHuerfanos as $row) {
        try {
            actualizarEstatus(
                $stmtChkEst, $stmtInsEst, $stmtUpdEst,
                $row['nss'], (int) $row['id_alumno'], $row['tipo'],
                $row['fecha_recepcion_imss'], (int) $row['id_acuse']
            );
            $reconciliados++;
        } catch (Exception $e) {
            $msg = "[Huérfano] nss={$row['nss']}: " . $e->getMessage();
            error_log("[SIAE] Reparar $msg");
            $errores[] = $msg;
        }
    }

    return ['reconciliados' => $reconciliados, 'errores' => $errores];
}

// Inserta o actualiza estatus_imss_alumnos solo si la fecha es más reciente.
function actualizarEstatus(PDOStatement $chk, PDOStatement $ins, PDOStatement $upd,
                           string $nss, int $idAlumno, string $tipo, string $fecha, int $idAcuse): void {
    $chk->execute([$nss]);
    $existing = $chk->fetch();
    $chk->closeCursor();

    if (!$existing) {
        $ins->execute([$nss, $idAlumno, $tipo, $fecha, $idAcuse]);
    } elseif (new DateTime($fecha) > new DateTime($existing['fecha_movimiento'])) {
        $upd->execute([$tipo, $fecha, $idAlumno, $idAcuse, $nss]);
    }
}

