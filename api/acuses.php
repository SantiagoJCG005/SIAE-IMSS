<?php
/**
 * SIAE-IMSS - API de Acuses IMSS
 * Gestión de comprobantes PDF del IMSS (altas y bajas)
 *
 * Acciones:
 *  subir      POST  - Sube y parsea un PDF, devuelve preview
 *  confirmar  POST  - Guarda el acuse procesado en BD
 *  cancelar   POST  - Cancela el acuse pendiente en sesión
 *  listar     GET   - Lista de acuses subidos (paginado)
 *  detalle    GET   - Líneas procesadas de un acuse
 *  estatus_nss GET  - Estatus IMSS actual de un NSS
 */

// PHP - cargar autenticacion, funciones y el extractor de texto de PDF
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/PdfTextExtractor.php';

// PHP - indicar que la respuesta es JSON
header('Content-Type: application/json');

// PHP - verificar sesion activa
if (!estaLogueado()) {
    respuestaError('No autorizado', 401);
}

// PHP - solo Superadmin y Jefa de Servicios pueden gestionar acuses
$rolesPermitidos = [ROL_SUPERADMIN, ROL_JEFA_SERVICIOS];
if (!tieneAlgunRol($rolesPermitidos)) {
    respuestaError('Sin permisos para gestionar acuses', 403);
}

$conexion = obtenerConexion();
// PHP - leer accion desde GET o POST
$accion   = obtenerGet('action', obtenerPost('action', ''));

switch ($accion) {
    case 'subir':       accionSubir();      break;
    case 'confirmar':   accionConfirmar();  break;
    case 'cancelar':    accionCancelar();   break;
    case 'listar':      accionListar();     break;
    case 'detalle':     accionDetalle();    break;
    case 'estatus_nss': accionEstatusNss(); break;
    case 'debug_pdf':   accionDebugPdf();   break;
    case 'debug_linea': accionDebugLinea(); break;
    case 'eliminar':    accionEliminar();   break;
    default:            respuestaError('Acción no válida');
}

// ─────────────────────────────────────────────
//  SUBIR PDF – parsear y devolver preview
// ─────────────────────────────────────────────
function accionSubir() {
    global $conexion;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respuestaError('Método no permitido', 405);
    }

    if (empty($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
        $error = $_FILES['pdf']['error'] ?? 'sin archivo';
        respuestaError('Error al recibir el archivo. Código: ' . $error);
    }

    $archivo = $_FILES['pdf'];

    // Validar tipo MIME
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($archivo['tmp_name']);
    if ($mimeType !== 'application/pdf') {
        respuestaError('El archivo debe ser un PDF válido.');
    }

    // Validar tamaño (máx 20 MB)
    if ($archivo['size'] > 20 * 1024 * 1024) {
        respuestaError('El archivo no debe superar 20 MB.');
    }

    $nombreOriginal = $archivo['name'];
    $numeroLote     = PdfTextExtractor::extraerNumeroLote($nombreOriginal);

    // Verificar duplicado
    $stmt = $conexion->prepare("SELECT id_acuse FROM acuses_imss WHERE numero_lote = ?");
    $stmt->execute([$numeroLote]);
    if ($stmt->fetch()) {
        respuestaError("El lote «{$numeroLote}» ya fue subido anteriormente. No se permiten duplicados.");
    }

    // Guardar archivo
    $dirDestino = UPLOAD_PATH . 'acuses/';
    if (!is_dir($dirDestino)) {
        mkdir($dirDestino, 0755, true);
    }

    // basename() primero para eliminar cualquier componente de ruta antes de sanitizar
    $nombreGuardado = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($nombreOriginal));
    $rutaFinal      = $dirDestino . $nombreGuardado;

    if (!move_uploaded_file($archivo['tmp_name'], $rutaFinal)) {
        respuestaError('No se pudo guardar el archivo en el servidor.');
    }

    // Obtener registro patronal para ayudar a localizar NSS en el PDF
    $cfgPatronal  = $conexion->query("SELECT registro_patronal, digito_verificador FROM configuracion_patronal WHERE activo = 1 LIMIT 1")->fetch();
    $patronalConDV = ($cfgPatronal['registro_patronal'] ?? REGISTRO_PATRONAL) .
                     ($cfgPatronal['digito_verificador'] ?? DIGITO_VERIFICADOR);

    // Parsear PDF
    $texto       = PdfTextExtractor::extraer($rutaFinal);
    $tipoDetect  = PdfTextExtractor::detectarTipo($texto);
    $fechaDetect = PdfTextExtractor::extraerFecha($texto);
    $nssDetalle  = PdfTextExtractor::extraerNSSDetallado($texto, $patronalConDV);
    $nssList        = $nssDetalle['nss'];
    $totalBruto     = $nssDetalle['total_bruto'];
    $duplicadosNss  = $nssDetalle['duplicados'];
    $datosDetallados = PdfTextExtractor::extraerLineasDetalladas($texto, $patronalConDV);

    // Consultar cuáles alumnos existen en el sistema
    // Alta → buscar por CURP (el alumno puede no tener NSS aún)
    // Baja → buscar por NSS (ya tiene NSS registrado)
    $encontrados    = 0;
    $noEncontrados  = 0;
    $detallePreview = [];

    if (!empty($nssList)) {
        $nssConDuplicado = array_column($duplicadosNss, 'veces', 'nss');

        if ($tipoDetect === 'alta') {
            // Construir mapa CURP → NSS desde los datos detallados del PDF
            $curpToNss = [];
            foreach ($nssList as $nss) {
                $curp = $datosDetallados[$nss][0]['curp'] ?? null;
                if ($curp) $curpToNss[$curp] = $nss;
            }

            $alumnosEncontrados = []; // keyed by NSS
            if (!empty($curpToNss)) {
                $ph = implode(',', array_fill(0, count($curpToNss), '?'));
                $stmt = $conexion->prepare("
                    SELECT a.curp, a.nombre, a.apellido_paterno, a.apellido_materno
                    FROM alumnos a
                    WHERE a.curp IN ($ph)
                ");
                $stmt->execute(array_keys($curpToNss));
                while ($row = $stmt->fetch()) {
                    $nssDelCurp = $curpToNss[$row['curp']] ?? null;
                    if ($nssDelCurp) {
                        $alumnosEncontrados[$nssDelCurp] =
                            $row['apellido_paterno'] . ' ' . $row['apellido_materno'] . ' ' . $row['nombre'];
                    }
                }
            }
        } else {
            // Baja: buscar por NSS en datos_imss
            $ph = implode(',', array_fill(0, count($nssList), '?'));
            $stmt = $conexion->prepare("
                SELECT d.nss, a.nombre, a.apellido_paterno, a.apellido_materno
                FROM datos_imss d
                INNER JOIN alumnos a ON a.id_alumno = d.id_alumno
                WHERE d.nss IN ($ph)
            ");
            $stmt->execute($nssList);
            $alumnosEncontrados = [];
            while ($row = $stmt->fetch()) {
                $alumnosEncontrados[$row['nss']] =
                    $row['apellido_paterno'] . ' ' . $row['apellido_materno'] . ' ' . $row['nombre'];
            }
        }

        foreach ($nssList as $nss) {
            if (isset($alumnosEncontrados[$nss])) {
                $encontrados++;
                $detallePreview[] = [
                    'nss'        => $nss,
                    'nombre'     => $alumnosEncontrados[$nss],
                    'en_sistema' => true,
                    'duplicado'  => isset($nssConDuplicado[$nss]),
                ];
            } else {
                $noEncontrados++;
                $dd = $datosDetallados[$nss][0] ?? [];
                $nombrePdf = trim(($dd['apellidos'] ?? '') . ' ' . ($dd['nombre'] ?? '')) ?: null;
                $detallePreview[] = [
                    'nss'        => $nss,
                    'nombre'     => $nombrePdf,
                    'en_sistema' => false,
                    'duplicado'  => isset($nssConDuplicado[$nss]),
                ];
            }
        }
    }

    // Guardar en sesión para confirmar después
    $idUsuario = obtenerIdUsuarioActual();
    $_SESSION['acuse_pendiente_' . $idUsuario] = [
        'numero_lote'          => $numeroLote,
        'tipo'                 => $tipoDetect,
        'archivo_nombre'       => $nombreOriginal,
        'archivo_ruta'         => $rutaFinal,
        'fecha_recepcion_imss' => $fechaDetect,
        'total_bruto'          => $totalBruto,
        'duplicados_nss'       => $duplicadosNss,
        'nss_list'             => $nssList,
        'datos_detallados'     => $datosDetallados,
        'ts'                   => time(),
    ];

    respuestaExitosa([
        'numero_lote'          => $numeroLote,
        'tipo_detectado'       => $tipoDetect,
        'fecha_detectada'      => $fechaDetect,
        'fecha_legible'        => $fechaDetect ? date('d/m/Y', strtotime($fechaDetect)) : null,
        'total_en_pdf'         => count($nssList),
        'total_bruto_pdf'      => $totalBruto,
        'duplicados_en_pdf'    => $duplicadosNss,
        'encontrados_sistema'  => $encontrados,
        'no_encontrados'       => $noEncontrados,
        'preview'              => $detallePreview,
    ], 'PDF analizado correctamente');
}

// ─────────────────────────────────────────────
//  CONFIRMAR – guardar en BD
// ─────────────────────────────────────────────
function accionConfirmar() {
    global $conexion;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respuestaError('Método no permitido', 405);
    }

    $idUsuario  = obtenerIdUsuarioActual();
    $sesionKey  = 'acuse_pendiente_' . $idUsuario;

    if (empty($_SESSION[$sesionKey])) {
        respuestaError('No hay acuse pendiente de confirmar. Sube el PDF primero.');
    }

    $pendiente = $_SESSION[$sesionKey];

    // El usuario puede corregir el tipo y la fecha si la detección falló
    $tipo  = obtenerPost('tipo',  $pendiente['tipo'] ?? '');
    $fecha = obtenerPost('fecha', $pendiente['fecha_recepcion_imss'] ?? date('Y-m-d'));

    if (!in_array($tipo, ['alta', 'baja'])) {
        respuestaError('Tipo de movimiento no válido. Indica "alta" o "baja".');
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        respuestaError('Formato de fecha no válido. Usa YYYY-MM-DD.');
    }

    $nssList         = $pendiente['nss_list'];
    $totalBruto      = $pendiente['total_bruto'] ?? count($nssList);
    $duplicadosNss   = $pendiente['duplicados_nss'] ?? [];
    $datosDetallados = $pendiente['datos_detallados'] ?? [];
    $numeroLote      = $pendiente['numero_lote'];
    $fechaDt      = new DateTime($fecha);

    try {
        $conexion->beginTransaction();

        // Insertar acuse principal (sin conteos aún)
        // total_registros = conteo bruto del PDF (incluye filas con NSS duplicados)
        $stmt = $conexion->prepare("
            INSERT INTO acuses_imss
                (numero_lote, tipo, archivo_nombre, archivo_ruta, fecha_recepcion_imss,
                 total_registros, procesados, no_encontrados, omitidos_por_fecha, id_usuario)
            VALUES (?, ?, ?, ?, ?, ?, 0, 0, 0, ?)
        ");
        $stmt->execute([
            $numeroLote,
            $tipo,
            $pendiente['archivo_nombre'],
            $pendiente['archivo_ruta'],
            $fecha,
            $totalBruto,
            $idUsuario,
        ]);
        $idAcuse = $conexion->lastInsertId();

        $contProcesados    = 0;
        $contNoEncontrados = 0;
        $contOmitidos      = 0;
        $contDuplicados    = 0;

        $debugBaja = null; // DEBUG TEMPORAL

        if (!empty($nssList)) {
            $mapaAlumnos = []; // nss => id_alumno

            if ($tipo === 'alta') {
                // Alta: vincular por CURP extraída del PDF
                // El alumno puede no tener NSS registrado aún → se crea en datos_imss
                $curpToNss = [];
                foreach ($nssList as $nss) {
                    $curp = $datosDetallados[$nss][0]['curp'] ?? null;
                    if ($curp) $curpToNss[$curp] = $nss;
                }

                if (!empty($curpToNss)) {
                    $ph = implode(',', array_fill(0, count($curpToNss), '?'));
                    $stmt = $conexion->prepare("
                        SELECT a.id_alumno, a.curp
                        FROM alumnos a
                        WHERE a.curp IN ($ph)
                    ");
                    $stmt->execute(array_keys($curpToNss));
                    while ($row = $stmt->fetch()) {
                        $nssDelCurp = $curpToNss[$row['curp']] ?? null;
                        if ($nssDelCurp) {
                            $mapaAlumnos[$nssDelCurp] = $row['id_alumno'];
                            $stmtCheck = $conexion->prepare(
                                "SELECT id_datos_imss FROM datos_imss WHERE id_alumno = ? LIMIT 1"
                            );
                            $stmtCheck->execute([$row['id_alumno']]);
                            if (!$stmtCheck->fetch()) {
                                $conexion->prepare("
                                    INSERT INTO datos_imss (id_alumno, nss, estado_imss)
                                    VALUES (?, ?, 'activo')
                                ")->execute([$row['id_alumno'], $nssDelCurp]);
                            } else {
                                $conexion->prepare("
                                    UPDATE datos_imss SET nss = ?, estado_imss = 'activo'
                                    WHERE id_alumno = ?
                                ")->execute([$nssDelCurp, $row['id_alumno']]);
                            }
                        }
                    }
                }

                // Fallback: buscar por NSS en datos_imss para los que no matchearon por CURP
                // Cubre el caso donde alumnos.curp está vacío pero el Excel tenía NSS
                $nssNoEncontrados = array_diff($nssList, array_keys($mapaAlumnos));
                if (!empty($nssNoEncontrados)) {
                    $condNss  = [];
                    $paramNss = [];
                    foreach ($nssNoEncontrados as $nssItem) {
                        $condNss[]  = '(d.nss = ? OR d.nss = ?)';
                        $paramNss[] = $nssItem;
                        $paramNss[] = substr($nssItem, 0, 10);
                    }
                    $stmtNss = $conexion->prepare("
                        SELECT d.nss, d.id_alumno FROM datos_imss d
                        WHERE " . implode(' OR ', $condNss)
                    );
                    $stmtNss->execute($paramNss);
                    while ($row = $stmtNss->fetch()) {
                        foreach ($nssNoEncontrados as $nssItem) {
                            if ($row['nss'] === $nssItem || $row['nss'] === substr($nssItem, 0, 10)) {
                                $mapaAlumnos[$nssItem] = (int) $row['id_alumno'];
                                // Actualizar NSS completo y estado en datos_imss
                                $conexion->prepare("
                                    UPDATE datos_imss SET nss = ?, estado_imss = 'activo'
                                    WHERE id_alumno = ?
                                ")->execute([$nssItem, $row['id_alumno']]);
                            }
                        }
                    }
                }
            } else {
                // Baja: vincular por NSS en datos_imss
                // JOIN flexible: busca por NSS completo (11) o base (10 dígitos)
                $condiciones = [];
                $paramsBaja  = [];
                foreach ($nssList as $nssItem) {
                    $condiciones[] = 'd.nss = ? OR d.nss = ?';
                    $paramsBaja[]  = $nssItem;                    // 11 dígitos
                    $paramsBaja[]  = substr($nssItem, 0, 10);     // 10 dígitos
                }
                $whereBaja = implode(' OR ', array_map(fn($c) => "($c)", $condiciones));
                $stmt = $conexion->prepare("
                    SELECT d.nss, d.id_alumno, d.estado_imss
                    FROM datos_imss d
                    WHERE $whereBaja
                ");
                $stmt->execute($paramsBaja);
                $debugBajaRows = [];
                while ($row = $stmt->fetch()) {
                    $debugBajaRows[] = $row;
                    // Vincular al NSS del PDF (11 dígitos) para que el resto del flujo sea consistente
                    foreach ($nssList as $nssOrig) {
                        if ($row['nss'] === $nssOrig || $row['nss'] === substr($nssOrig, 0, 10)) {
                            $mapaAlumnos[$nssOrig] = $row['id_alumno'];
                            break;
                        }
                    }
                }
                // DEBUG TEMPORAL — eliminar una vez resuelto el bug
                $debugBaja = [
                    'tipo_recibido'    => $tipo,
                    'nss_list'         => $nssList,
                    'datos_imss_rows'  => $debugBajaRows,
                    'mapa_alumnos'     => $mapaAlumnos,
                ];
                error_log('[SIAE-DEBUG] Baja confirm: ' . json_encode($debugBaja));
            }

            // Cargar estatus actuales de estos NSS
            $phNss = implode(',', array_fill(0, count($nssList), '?'));
            $stmt = $conexion->prepare("
                SELECT nss, estatus, fecha_movimiento, id_acuse_origen
                FROM estatus_imss_alumnos
                WHERE nss IN ($phNss)
            ");
            $stmt->execute($nssList);
            $mapaEstatus = [];
            while ($row = $stmt->fetch()) {
                $mapaEstatus[$row['nss']] = $row;
            }

            // Procesar cada NSS
            $stmtDetalle = $conexion->prepare("
                INSERT INTO acuse_detalle
                    (id_acuse, nss, id_alumno, resultado, fecha_pdf, fecha_existente,
                     nombre_pdf, apellido_paterno_pdf, apellido_materno_pdf, curp_pdf)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmtInsertEstatus = $conexion->prepare("
                INSERT INTO estatus_imss_alumnos
                    (nss, id_alumno, estatus, fecha_movimiento, id_acuse_origen)
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmtUpdateEstatus = $conexion->prepare("
                UPDATE estatus_imss_alumnos
                SET estatus = ?, fecha_movimiento = ?, id_alumno = ?, id_acuse_origen = ?
                WHERE nss = ?
            ");

            // Vincula registros huérfanos (sin lote de origen) al acuse actual
            $stmtVincular = $conexion->prepare("
                UPDATE estatus_imss_alumnos
                SET id_acuse_origen = ?
                WHERE nss = ? AND id_acuse_origen IS NULL
            ");

            // PHP - procesar cada NSS: determinar resultado y actualizar estatus
            foreach ($nssList as $nss) {
                $idAlumno   = $mapaAlumnos[$nss] ?? null;
                $estatusAct = $mapaEstatus[$nss] ?? null;
                $fechaExist = $estatusAct ? $estatusAct['fecha_movimiento'] : null;
                $resultado  = '';

                // PHP - datos del PDF para guardar en acuse_detalle
                $dd = $datosDetallados[$nss][0] ?? [];
                $nombrePdf   = $dd['nombre']    ?? null;
                $apPat       = $dd['apellidos'] ?? null;
                $apMat       = null;
                $curpPdf     = $dd['curp']      ?? null;

                if ($idAlumno === null) {
                    // PHP - NSS no encontrado en el sistema
                    $resultado = 'no_encontrado';
                    $contNoEncontrados++;
                } else {
                    $debeActualizar = true;
                    if ($estatusAct !== null) {
                        // PHP - omitir si la fecha del acuse no es mas reciente que la registrada
                        $fechaExistDt = new DateTime($estatusAct['fecha_movimiento']);
                        if ($fechaDt <= $fechaExistDt) {
                            $debeActualizar = false;
                        }
                    }

                    if ($debeActualizar) {
                        // PHP - actualizar o insertar estatus IMSS del alumno
                        $resultado = 'procesado';
                        $contProcesados++;

                        if ($estatusAct === null) {
                            // primera vez: insertar registro de estatus
                            $stmtInsertEstatus->execute([$nss, $idAlumno, $tipo, $fecha, $idAcuse]);
                        } else {
                            // ya existe: actualizar fecha y tipo
                            $stmtUpdateEstatus->execute([$tipo, $fecha, $idAlumno, $idAcuse, $nss]);
                        }
                    } else {
                        // PHP - NSS omitido porque la fecha del acuse no supera la existente
                        $resultado = 'omitido_por_fecha';
                        $contOmitidos++;
                        // Si el registro existente no tiene lote asignado, vincularlo al acuse actual
                        if (($estatusAct['id_acuse_origen'] ?? null) === null) {
                            $stmtVincular->execute([$idAcuse, $nss]);
                        }
                    }
                }

                // PHP - guardar linea de detalle del acuse con resultado individual
                $stmtDetalle->execute([
                    $idAcuse, $nss, $idAlumno, $resultado, $fecha, $fechaExist,
                    $nombrePdf, $apPat, $apMat, $curpPdf
                ]);
            }

            // PHP - registrar las ocurrencias extra de NSS duplicados en el PDF
            foreach ($duplicadosNss as $dup) {
                $nss      = $dup['nss'];
                $veces    = $dup['veces'];
                $idAlumno = $mapaAlumnos[$nss] ?? null;
                for ($i = 1; $i < $veces; $i++) {
                    $dd = $datosDetallados[$nss][$i] ?? $datosDetallados[$nss][0] ?? [];
                    $stmtDetalle->execute([
                        $idAcuse, $nss, $idAlumno, 'duplicado_nss', $fecha, null,
                        $dd['nombre'] ?? null, $dd['apellidos'] ?? null, null, $dd['curp'] ?? null,
                    ]);
                    $contDuplicados++;
                }
            }
        }

        // PHP - actualizar conteos finales en el encabezado del acuse
        $stmt = $conexion->prepare("
            UPDATE acuses_imss
            SET procesados = ?, no_encontrados = ?, omitidos_por_fecha = ?
            WHERE id_acuse = ?
        ");
        $stmt->execute([$contProcesados, $contNoEncontrados, $contOmitidos, $idAcuse]);

        $conexion->commit();

    } catch (Exception $e) {
        $conexion->rollBack();
        respuestaError('Error al guardar el acuse: ' . $e->getMessage());
    }

    // PHP - limpiar acuse pendiente de la sesion del usuario
    unset($_SESSION[$sesionKey]);

    respuestaExitosa([
        'id_acuse'        => $idAcuse,
        'tipo'            => $tipo,
        'fecha'           => $fecha,
        'total'           => $totalBruto,
        'procesados'      => $contProcesados,
        'no_encontrados'  => $contNoEncontrados,
        'omitidos'        => $contOmitidos,
        'duplicados'      => $contDuplicados,
        'debug_baja'      => $debugBaja,
    ], 'Acuse confirmado y registros actualizados correctamente.');
}

// ─────────────────────────────────────────────
//  CANCELAR – descartar acuse en sesión
// ─────────────────────────────────────────────
function accionCancelar() {
    $idUsuario = obtenerIdUsuarioActual();
    $sesionKey = 'acuse_pendiente_' . $idUsuario;

    if (isset($_SESSION[$sesionKey])) {
        $ruta = $_SESSION[$sesionKey]['archivo_ruta'] ?? null;
        if ($ruta && file_exists($ruta)) {
            // Si falla el borrado no bloqueamos la cancelacion
            @unlink($ruta);
        }
        unset($_SESSION[$sesionKey]);
    }

    respuestaExitosa(null, 'Acuse cancelado.');
}

// ─────────────────────────────────────────────
//  LISTAR – historial de acuses con paginacion
// ─────────────────────────────────────────────
function accionListar() {
    global $conexion;

    // PHP - leer pagina actual y filtro de tipo desde la URL
    $pagina  = max(1, intval(obtenerGet('pagina', 1)));
    $limite  = 20;
    $offset  = ($pagina - 1) * $limite;
    $tipo    = obtenerGet('tipo', '');

    $where  = '1=1';
    $params = [];

    // PHP - agregar filtro de tipo si se especifico
    if (in_array($tipo, ['alta', 'baja'])) {
        $where   .= ' AND a.tipo = ?';
        $params[] = $tipo;
    }

    $total = $conexion->prepare("SELECT COUNT(*) FROM acuses_imss a WHERE $where");
    $total->execute($params);
    $totalRegistros = $total->fetchColumn();

    $params[] = $limite;
    $params[] = $offset;

    $stmt = $conexion->prepare("
        SELECT a.*, u.nombre_completo AS subido_por
        FROM acuses_imss a
        INNER JOIN usuarios u ON u.id_usuario = a.id_usuario
        WHERE $where
        ORDER BY a.fecha_subida DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute($params);
    $acuses = $stmt->fetchAll();

    respuestaExitosa([
        'acuses'   => $acuses,
        'total'    => $totalRegistros,
        'pagina'   => $pagina,
        'paginas'  => ceil($totalRegistros / $limite),
    ]);
}

 
// ─────────────────────────────────────────────
//  DETALLE – líneas de un acuse
// ─────────────────────────────────────────────
function accionDetalle() {
    global $conexion;

    $idAcuse = intval(obtenerGet('id', 0));
    if ($idAcuse <= 0) respuestaError('ID de acuse no válido.');

    $stmt = $conexion->prepare("SELECT * FROM acuses_imss WHERE id_acuse = ?");
    $stmt->execute([$idAcuse]);
    $acuse = $stmt->fetch();
    if (!$acuse) respuestaError('Acuse no encontrado.');

    $stmt = $conexion->prepare("
        SELECT d.*,
               CONCAT(a.apellido_paterno, ' ', a.apellido_materno, ' ', a.nombre) AS nombre_alumno,
               a.numero_control,
               CONCAT(a2.apellido_paterno, ' ', a2.apellido_materno, ' ', a2.nombre) AS nombre_actual,
               a2.numero_control AS nc_actual
        FROM acuse_detalle d
        LEFT JOIN alumnos a  ON a.id_alumno  = d.id_alumno
        LEFT JOIN datos_imss di ON di.nss = d.nss
        LEFT JOIN alumnos a2 ON a2.id_alumno  = di.id_alumno
        WHERE d.id_acuse = ?
        ORDER BY d.resultado, d.nss
    ");
    $stmt->execute([$idAcuse]);
    $detalle = $stmt->fetchAll();

    respuestaExitosa(['acuse' => $acuse, 'detalle' => $detalle]);
}

// ─────────────────────────────────────────────
//  ESTATUS NSS – badge en tabla.php
// ─────────────────────────────────────────────
function accionEstatusNss() {
    global $conexion;

    $nss = trim(obtenerGet('nss', ''));
    if (!preg_match('/^\d{11}$/', $nss)) {
        respuestaError('NSS no válido.');
    }

    $stmt = $conexion->prepare("
        SELECT e.estatus, e.fecha_movimiento, a.archivo_nombre, a.numero_lote
        FROM estatus_imss_alumnos e
        INNER JOIN acuses_imss a ON a.id_acuse = e.id_acuse_origen
        WHERE e.nss = ?
    ");
    $stmt->execute([$nss]);
    $estatus = $stmt->fetch();

    if (!$estatus) {
        respuestaExitosa(['estatus' => null]);
    }

    respuestaExitosa(['estatus' => $estatus]);
}

// ─────────────────────────────────────────────
//  DEBUG LINEA – muestra el segmento extraído para un NSS concreto
//  GET ?action=debug_linea&nss=XXXXXXXXXXX
//  POST campo pdf (mismo PDF que se subió)
// ─────────────────────────────────────────────
function accionDebugLinea() {
    if (!tieneRol(ROL_SUPERADMIN) && !tieneRol(ROL_JEFA_SERVICIOS)) {
        respuestaError('Sin permisos', 403);
    }

    if (empty($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
        respuestaError('No se recibió el PDF.');
    }

    $nssQuery = trim(obtenerGet('nss', ''));

    $cfgPatronal  = obtenerConexion()->query("SELECT registro_patronal, digito_verificador FROM configuracion_patronal WHERE activo = 1 LIMIT 1")->fetch();
    $patronalConDV = ($cfgPatronal['registro_patronal'] ?? REGISTRO_PATRONAL) .
                     ($cfgPatronal['digito_verificador'] ?? DIGITO_VERIFICADOR);

    $texto    = PdfTextExtractor::extraer($_FILES['pdf']['tmp_name']);
    $detalles = PdfTextExtractor::extraerLineasDetalladas($texto, $patronalConDV);

    $resultado = $nssQuery && isset($detalles[$nssQuery])
        ? [$nssQuery => $detalles[$nssQuery]]
        : array_slice($detalles, 0, 5, true);

    // También mostrar el segmento de texto crudo para diagnóstico
    $segmentos = [];
    $pat = preg_quote($patronalConDV, '/');
    if (preg_match_all('/' . $pat . '(\d{11})/', $texto, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[1] as $idx => $nssMatch) {
            if ($nssQuery && $nssMatch[0] !== $nssQuery) continue;
            $endPos    = $nssMatch[1] + 11;
            $nextStart = isset($m[0][$idx + 1]) ? $m[0][$idx + 1][1] : strlen($texto);
            $segmentos[$nssMatch[0]] = [
                'raw_200'    => substr($texto, $endPos, 200),
                'collapsed'  => preg_replace('/(?<=[A-Z0-9])\s+(?=[A-Z0-9])/', '', substr($texto, $endPos, 200)),
            ];
            if ($nssQuery) break;
        }
    }

    respuestaExitosa(['datos' => $resultado, 'segmentos' => $segmentos]);
}

// ─────────────────────────────────────────────
//  DEBUG PDF – muestra el texto extraído y NSS encontrados
//  Solo Superadmin, útil para diagnosticar PDFs nuevos
// ─────────────────────────────────────────────
function accionDebugPdf() {
    if (!tieneRol(ROL_SUPERADMIN) && !tieneRol(ROL_JEFA_SERVICIOS)) {
        respuestaError('Sin permisos', 403);
    }

    if (empty($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
        respuestaError('No se recibió el PDF.');
    }

    $tmpRuta = $_FILES['pdf']['tmp_name'];
    $nombre  = $_FILES['pdf']['name'];

    $texto = PdfTextExtractor::extraer($tmpRuta);
    $nss   = PdfTextExtractor::extraerNSS($texto);
    $tipo  = PdfTextExtractor::detectarTipo($texto);
    $fecha = PdfTextExtractor::extraerFecha($texto);
    $lote  = PdfTextExtractor::extraerNumeroLote($nombre);

    respuestaExitosa([
        'lote'         => $lote,
        'tipo'         => $tipo,
        'fecha'        => $fecha,
        'nss_count'    => count($nss),
        'nss_muestra'  => array_slice($nss, 0, 10),
        'texto_bytes'  => strlen($texto),
        'texto_inicio' => mb_substr($texto, 0, 500),
    ]);
}

// ─────────────────────────────────────────────
//  ELIMINAR – borrar un acuse y sus detalles
// ─────────────────────────────────────────────
function accionEliminar() {
    global $conexion;

    if (!tieneRol(ROL_SUPERADMIN) && !tieneRol(ROL_JEFA_SERVICIOS)) {
        respuestaError('Sin permisos para eliminar', 403);
    }

    $idAcuse = intval(obtenerPost('id', 0));
    if ($idAcuse <= 0) respuestaError('ID no válido.');

    $stmt = $conexion->prepare("SELECT * FROM acuses_imss WHERE id_acuse = ?");
    $stmt->execute([$idAcuse]);
    $acuse = $stmt->fetch();
    if (!$acuse) respuestaError('Acuse no encontrado.');

    // Eliminar archivo fisico — si falla no bloqueamos el borrado del registro
    if ($acuse['archivo_ruta'] && file_exists($acuse['archivo_ruta'])) {
        @unlink($acuse['archivo_ruta']);
    }

    // Revertir estatus: eliminar registros vinculados al acuse o huérfanos con NSS en este acuse
    $conexion->prepare("
        DELETE e FROM estatus_imss_alumnos e
        WHERE e.id_acuse_origen = ?
           OR (e.id_acuse_origen IS NULL AND e.nss IN (
               SELECT d.nss FROM acuse_detalle d WHERE d.id_acuse = ?
           ))
    ")->execute([$idAcuse, $idAcuse]);

    // La FK con ON DELETE CASCADE borra acuse_detalle automáticamente
    $conexion->prepare("DELETE FROM acuses_imss WHERE id_acuse = ?")
             ->execute([$idAcuse]);

    respuestaExitosa(null, 'Acuse eliminado.');
}
