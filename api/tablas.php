<?php
/**
 * SI FUNCIONA NO LE MUEVAS!!!!!
 * SIAE-IMSS - API de Tablas de Movimientos
 * CRUD para tablas de altas/bajas y sus alumnos
 * Con normalización de caracteres especiales
 */

// Respuesta en formato JSON
header('Content-Type: application/json');

// Carga archivos necesarios
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Verifica autenticacion
if (!estaLogueado()) {
    respuestaError('No autorizado', 401);
}

// Solo Jefa de Servicios y Admin Servicios pueden usar esta API
if (!tieneRol(ROL_JEFA_SERVICIOS) && !tieneRol(ROL_ADMIN_SERVICIOS) && !tieneRol(ROL_SUPERADMIN)) {
    respuestaError('Sin permisos para esta accion', 403);
}

// Conexion a la base de datos
$conexion = obtenerConexion();

// Lee datos JSON enviados
$datosEntrada = json_decode(file_get_contents('php://input'), true);

// Obtiene la accion a realizar
$accion = $datosEntrada['action'] ?? obtenerGet('action', '');

/**
 * Normaliza caracteres especiales a ASCII
 * Ñ -> #, acentos -> sin acento
 * Retorna array [texto_normalizado, fue_normalizado, cambios]
 */
function normalizarTexto($texto) {
    $original = $texto;
    $cambios = [];
    
    // Mapa de caracteres especiales
    $mapa = [
        // Ñ -> # (requisito del cliente)
        'Ñ' => '#', 'ñ' => '#',
        // Vocales con acento
        'Á' => 'A', 'À' => 'A', 'Ä' => 'A', 'Â' => 'A',
        'á' => 'A', 'à' => 'A', 'ä' => 'A', 'â' => 'A',
        'É' => 'E', 'È' => 'E', 'Ë' => 'E', 'Ê' => 'E',
        'é' => 'E', 'è' => 'E', 'ë' => 'E', 'ê' => 'E',
        'Í' => 'I', 'Ì' => 'I', 'Ï' => 'I', 'Î' => 'I',
        'í' => 'I', 'ì' => 'I', 'ï' => 'I', 'î' => 'I',
        'Ó' => 'O', 'Ò' => 'O', 'Ö' => 'O', 'Ô' => 'O',
        'ó' => 'O', 'ò' => 'O', 'ö' => 'O', 'ô' => 'O',
        'Ú' => 'U', 'Ù' => 'U', 'Ü' => 'U', 'Û' => 'U',
        'ú' => 'U', 'ù' => 'U', 'ü' => 'U', 'û' => 'U',
    ];
    
    // Detecta qué caracteres se van a cambiar
    foreach ($mapa as $especial => $reemplazo) {
        if (mb_strpos($texto, $especial) !== false) {
            $cambios[] = "$especial → $reemplazo";
        }
    }
    
    // Aplica el reemplazo
    $textoNormalizado = strtr($texto, $mapa);
    
    return [
        'texto' => mb_strtoupper($textoNormalizado),
        'normalizado' => !empty($cambios),
        'cambios' => $cambios
    ];
}

/**
 * Procesa y normaliza los datos de un alumno
 * Retorna datos normalizados + datos originales si hubo cambios
 */
function procesarDatosAlumno($datos) {
    $datosOriginales = [];
    $fueNormalizado = false;
    $todosLosCambios = [];
    
    // Campos a normalizar
    $camposTexto = ['apellido_paterno', 'apellido_materno', 'nombres'];
    
    foreach ($camposTexto as $campo) {
        $valorOriginal = trim($datos[$campo] ?? '');
        $resultado = normalizarTexto($valorOriginal);
        
        if ($resultado['normalizado']) {
            $datosOriginales[$campo] = $valorOriginal;
            $fueNormalizado = true;
            foreach ($resultado['cambios'] as $cambio) {
                $todosLosCambios[] = "$valorOriginal: $cambio";
            }
        }
        
        $datos[$campo] = $resultado['texto'];
    }
    
    // CURP también se normaliza (mayúsculas)
    $datos['curp'] = strtoupper(trim($datos['curp'] ?? ''));
    
    // NSS y DV se limpian
    $datos['numero_afiliacion'] = trim($datos['numero_afiliacion'] ?? '');
    $datos['digito_verificador'] = trim($datos['digito_verificador'] ?? '');
    
    return [
        'datos' => $datos,
        'fue_normalizado' => $fueNormalizado,
        'datos_originales' => $fueNormalizado ? $datosOriginales : null,
        'cambios' => $todosLosCambios
    ];
}

/**
 * Valida los datos de un alumno y retorna errores encontrados
 */
function validarDatosAlumno($datos) {
    $errores = [];
    
    // Validar NSS (10 digitos numericos)
    $nss = $datos['numero_afiliacion'] ?? '';
    if (empty($nss)) {
        $errores[] = 'NSS vacío';
    } elseif (!preg_match('/^\d{10}$/', $nss)) {
        $errores[] = 'NSS debe ser 10 dígitos';
    }
    
    // Validar digito verificador (1 digito)
    $dv = $datos['digito_verificador'] ?? '';
    if (empty($dv)) {
        $errores[] = 'Dígito verificador vacío';
    } elseif (!preg_match('/^\d$/', $dv)) {
        $errores[] = 'Dígito verificador inválido';
    }
    
    // Validar apellido paterno (obligatorio)
    $apPaterno = trim($datos['apellido_paterno'] ?? '');
    if (empty($apPaterno)) {
        $errores[] = 'Apellido paterno vacío';
    }
    
    // Validar nombres (obligatorio)
    $nombres = trim($datos['nombres'] ?? '');
    if (empty($nombres)) {
        $errores[] = 'Nombre vacío';
    }
    
    // Validar CURP (18 caracteres alfanumericos) - opcional pero si existe debe ser valido
    $curp = strtoupper(trim($datos['curp'] ?? ''));
    if (!empty($curp) && strlen($curp) != 18) {
        $errores[] = 'CURP debe tener 18 caracteres';
    }
    
    return $errores;
}

/**
 * Recalcula el conteo de registros y errores de una tabla
 */
function actualizarConteoTabla($conexion, $idTabla) {
    $consulta = $conexion->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN tiene_errores = 1 THEN 1 ELSE 0 END) as errores
        FROM tabla_alumnos 
        WHERE id_tabla = ?
    ");
    $consulta->execute([$idTabla]);
    $conteo = $consulta->fetch();
    
    $consulta = $conexion->prepare("
        UPDATE tablas_movimientos 
        SET total_registros = ?, registros_con_errores = ?
        WHERE id_tabla = ?
    ");
    $consulta->execute([$conteo['total'], $conteo['errores'], $idTabla]);
}

/**
 * Renumera los numeros de cuenta de una tabla
 */
function renumerarCuentas($conexion, $idTabla) {
    $consulta = $conexion->prepare("SELECT id_registro FROM tabla_alumnos WHERE id_tabla = ? ORDER BY id_registro");
    $consulta->execute([$idTabla]);
    $registros = $consulta->fetchAll();
    
    $numero = 1;
    foreach ($registros as $registro) {
        $consulta = $conexion->prepare("UPDATE tabla_alumnos SET numero_cuenta = ? WHERE id_registro = ?");
        $consulta->execute([$numero, $registro['id_registro']]);
        $numero++;
    }
}

/**
 * Genera la línea RESULTADO TXT para un alumno
 */
function generarLineaResultado($alumno, $configPatronal, $esAlta, $fechaMovimiento) {
    // Función interna para formatear campo con espacios
    $formatearCampo = function($texto, $longitud) {
        $texto = mb_strtoupper(trim($texto));
        $texto = preg_replace('/[^A-Z0-9\s#]/', '', $texto);
        if (mb_strlen($texto) > $longitud) {
            return mb_substr($texto, 0, $longitud);
        }
        return str_pad($texto, $longitud, ' ', STR_PAD_RIGHT);
    };
    
    // Función interna para formatear número con ceros
    $formatearNumero = function($numero, $longitud) {
        return str_pad($numero, $longitud, '0', STR_PAD_LEFT);
    };
    
    // Fecha formateada DDMMAAAA
    $fechaMov = date('dmY', strtotime($fechaMovimiento));
    
    $linea = '';
    
    if ($esAlta) {
        // ESTRUCTURA ALTAS
        $linea .= $formatearCampo($configPatronal['registro_patronal'], 10);
        $linea .= $configPatronal['digito_verificador'];
        $linea .= $formatearNumero($alumno['numero_afiliacion'], 10);
        $linea .= $alumno['digito_verificador'];
        $linea .= $formatearCampo($alumno['apellido_paterno'], 27);
        $linea .= $formatearCampo($alumno['apellido_materno'] ?? '', 27);
        $linea .= $formatearCampo($alumno['nombres'], 27);
        $linea .= '000000      210';
        $linea .= $fechaMov;
        $linea .= $formatearCampo($configPatronal['umf_alta'] ?? '001', 3);
        $linea .= '  08     ';
        $linea .= $formatearNumero($alumno['numero_cuenta'], 10);
        $linea .= ' ';
        $linea .= $formatearCampo($alumno['curp'] ?? '', 18);
        $linea .= '9';
    } else {
        // ESTRUCTURA BAJAS
        $linea .= $formatearCampo($configPatronal['registro_patronal'], 10);
        $linea .= $configPatronal['digito_verificador'];
        $linea .= $formatearNumero($alumno['numero_afiliacion'], 10);
        $linea .= $alumno['digito_verificador'];
        $linea .= $formatearCampo($alumno['apellido_paterno'], 27);
        $linea .= $formatearCampo($alumno['apellido_materno'] ?? '', 27);
        $linea .= $formatearCampo($alumno['nombres'], 27);
        $linea .= '000000000000000';
        $linea .= $fechaMov;
        $linea .= '00';
        $linea .= $formatearCampo($configPatronal['umf_baja'] ?? '000', 3);
        $linea .= '02     ';
        $linea .= $formatearNumero($alumno['numero_cuenta'], 10);
        $linea .= '6';
        $linea .= $formatearCampo($alumno['curp'] ?? '', 18);
        $linea .= '9';
    }
    
    return $linea;
}

// Ejecuta segun la accion
switch ($accion) {
    
   
    // TABLAS DE MOVIMIENTOS

    
    // Crear nueva tabla de movimientos
    case 'crear_tabla':
        
        $idSubcarpeta = intval($datosEntrada['id_subcarpeta'] ?? 0);
        $tipo = $datosEntrada['tipo'] ?? '';
        $fechaMovimiento = $datosEntrada['fecha_movimiento'] ?? '';
        $nombre = trim($datosEntrada['nombre'] ?? '');
        
        // Validaciones
        if ($idSubcarpeta <= 0) {
            respuestaError('Debe seleccionar una subcarpeta');
        }
        
        if (!in_array($tipo, ['alta', 'baja'])) {
            respuestaError('Tipo de movimiento no valido');
        }
        
        if (empty($fechaMovimiento)) {
            respuestaError('La fecha de movimiento es obligatoria');
        }
        
        // Verifica que la subcarpeta exista
        $consulta = $conexion->prepare("SELECT id_subcarpeta FROM subcarpetas_imss WHERE id_subcarpeta = ? AND activo = 1");
        $consulta->execute([$idSubcarpeta]);
        if (!$consulta->fetch()) {
            respuestaError('La subcarpeta seleccionada no existe');
        }
        
        // Si no tiene nombre, genera uno automatico
        if (empty($nombre)) {
            $tipoTexto = $tipo === 'alta' ? 'Altas' : 'Bajas';
            $fechaFormateada = date('d-M-Y', strtotime($fechaMovimiento));
            $nombre = "$tipoTexto $fechaFormateada";
        }
        
        try {
            $consulta = $conexion->prepare("
                INSERT INTO tablas_movimientos (id_subcarpeta, tipo, nombre, fecha_movimiento, id_usuario_creacion)
                VALUES (?, ?, ?, ?, ?)
            ");
            $consulta->execute([$idSubcarpeta, $tipo, $nombre, $fechaMovimiento, obtenerIdUsuarioActual()]);
            
            $idTabla = $conexion->lastInsertId();
            
            registrarEnBitacora('CREAR_TABLA_MOV', "Tabla creada: $nombre (ID: $idTabla)");
            
            respuestaExitosa(['id_tabla' => $idTabla], 'Tabla creada correctamente');
            
        } catch (Exception $e) {
            respuestaError('Error al crear tabla: ' . $e->getMessage());
        }
        break;
    
    // Obtener tabla con sus alumnos
    case 'obtener':
        
        $idTabla = intval($datosEntrada['id_tabla'] ?? obtenerGet('id_tabla', 0));
        
        if ($idTabla <= 0) {
            respuestaError('ID de tabla no valido');
        }
        
        try {
            // Obtiene datos de la tabla
            $consulta = $conexion->prepare("
                SELECT t.*, s.nombre as subcarpeta_nombre, c.nombre as carpeta_nombre,
                       u.nombre_completo as creador, uv.nombre_completo as validador
                FROM tablas_movimientos t
                INNER JOIN subcarpetas_imss s ON t.id_subcarpeta = s.id_subcarpeta
                INNER JOIN carpetas_imss c ON s.id_carpeta = c.id_carpeta
                LEFT JOIN usuarios u ON t.id_usuario_creacion = u.id_usuario
                LEFT JOIN usuarios uv ON t.id_usuario_validacion = uv.id_usuario
                WHERE t.id_tabla = ?
            ");
            $consulta->execute([$idTabla]);
            $tabla = $consulta->fetch();
            
            if (!$tabla) {
                respuestaError('Tabla no encontrada');
            }
            
            
            // Si el usuario es Admin SE, validamos que él sea el creador de esta tabla
            if (tieneRol(ROL_ADMIN_SERVICIOS) && !tieneRol(ROL_JEFA_SERVICIOS) && !tieneRol(ROL_SUPERADMIN)) {
                if ($tabla['id_usuario_creacion'] != obtenerIdUsuarioActual()) {
                    respuestaError('Acceso denegado: Esta tabla pertenece a otro usuario.', 403);
                }
            }
            // ------------------------------------
            
            // Obtiene alumnos de la tabla
            $consulta = $conexion->prepare("
                SELECT * FROM tabla_alumnos 
                WHERE id_tabla = ? 
                ORDER BY numero_cuenta, id_registro
            ");
            $consulta->execute([$idTabla]);
            $tabla['alumnos'] = $consulta->fetchAll();
            
            respuestaExitosa($tabla);
            
        } catch (Exception $e) {
            respuestaError('Error al obtener tabla: ' . $e->getMessage());
        }
        break;
    
    // Obtener alumnos con línea RESULTADO para vista de resultados
    case 'obtener_resultados':
        
        $idTabla = intval($datosEntrada['id_tabla'] ?? obtenerGet('id_tabla', 0));
        
        if ($idTabla <= 0) {
            respuestaError('ID de tabla no valido');
        }
        
        try {
            // Obtiene datos de la tabla
            $consulta = $conexion->prepare("
                SELECT t.*, s.nombre as subcarpeta_nombre, c.nombre as carpeta_nombre
                FROM tablas_movimientos t
                INNER JOIN subcarpetas_imss s ON t.id_subcarpeta = s.id_subcarpeta
                INNER JOIN carpetas_imss c ON s.id_carpeta = c.id_carpeta
                WHERE t.id_tabla = ?
            ");
            $consulta->execute([$idTabla]);
            $tabla = $consulta->fetch();
            
            if (!$tabla) {
                respuestaError('Tabla no encontrada');
            }
            
            
            if (tieneRol(ROL_ADMIN_SERVICIOS) && !tieneRol(ROL_JEFA_SERVICIOS) && !tieneRol(ROL_SUPERADMIN)) {
                if ($tabla['id_usuario_creacion'] != obtenerIdUsuarioActual()) {
                    respuestaError('Acceso denegado: No puedes ver resultados de una tabla de otro usuario.', 403);
                }
            }
            // ------------------------------------
            
            // Obtiene configuración patronal
            $configPatronal = $conexion->query("SELECT * FROM configuracion_patronal WHERE activo = 1 LIMIT 1")->fetch();
            
            if (!$configPatronal) {
                respuestaError('No hay configuración patronal activa');
            }
            
            // Obtiene alumnos
            $consulta = $conexion->prepare("
                SELECT * FROM tabla_alumnos 
                WHERE id_tabla = ? 
                ORDER BY numero_cuenta, id_registro
            ");
            $consulta->execute([$idTabla]);
            $alumnos = $consulta->fetchAll();
            
            $esAlta = $tabla['tipo'] === 'alta';
            
            // Genera línea RESULTADO para cada alumno
            $resultados = [];
            foreach ($alumnos as $alumno) {
                $lineaResultado = generarLineaResultado($alumno, $configPatronal, $esAlta, $tabla['fecha_movimiento']);
                
                $resultados[] = [
                    'id_registro' => $alumno['id_registro'],
                    'numero_cuenta' => $alumno['numero_cuenta'],
                    'numero_afiliacion' => $alumno['numero_afiliacion'],
                    'digito_verificador' => $alumno['digito_verificador'],
                    'apellido_paterno' => $alumno['apellido_paterno'],
                    'apellido_materno' => $alumno['apellido_materno'],
                    'nombres' => $alumno['nombres'],
                    'curp' => $alumno['curp'],
                    'tiene_errores' => $alumno['tiene_errores'],
                    'errores_detalle' => $alumno['errores_detalle'],
                    'datos_originales' => $alumno['datos_originales'] ? json_decode($alumno['datos_originales'], true) : null,
                    'linea_resultado' => $lineaResultado
                ];
            }
            
            respuestaExitosa([
                'tabla' => $tabla,
                'config_patronal' => [
                    'registro_patronal' => $configPatronal['registro_patronal'],
                    'digito_verificador' => $configPatronal['digito_verificador'],
                    'umf' => $esAlta ? $configPatronal['umf_alta'] : $configPatronal['umf_baja'],
                    'codigo_operacion' => $esAlta ? '08' : '02'
                ],
                'resultados' => $resultados
            ]);
            
        } catch (Exception $e) {
            respuestaError('Error al obtener resultados: ' . $e->getMessage());
        }
        break;
    
    // Editar tabla
    case 'editar_tabla':
        
        $idTabla = intval($datosEntrada['id_tabla'] ?? 0);
        $nombre = trim($datosEntrada['nombre'] ?? '');
        
        if ($idTabla <= 0) {
            respuestaError('ID de tabla no valido');
        }
        
        if (empty($nombre)) {
            respuestaError('El nombre es obligatorio');
        }
        
        try {
            
            $condicionDueno = "";
            $parametros = [$nombre, $idTabla];
            
            if (tieneRol(ROL_ADMIN_SERVICIOS) && !tieneRol(ROL_JEFA_SERVICIOS) && !tieneRol(ROL_SUPERADMIN)) {
                $condicionDueno = " AND id_usuario_creacion = ? ";
                $parametros[] = obtenerIdUsuarioActual();
            }
            
            $consulta = $conexion->prepare("UPDATE tablas_movimientos SET nombre = ? WHERE id_tabla = ? AND estado != 'enviado' $condicionDueno");
            $consulta->execute($parametros);
            // ------------------------------------
            
            if ($consulta->rowCount() == 0) {
                respuestaError('No se pudo actualizar la tabla');
            }
            
            registrarEnBitacora('EDITAR_TABLA_MOV', "Tabla editada: $nombre (ID: $idTabla)");
            
            respuestaExitosa(null, 'Tabla actualizada correctamente');
            
        } catch (Exception $e) {
            respuestaError('Error al actualizar tabla: ' . $e->getMessage());
        }
        break;
    
    // Eliminar tabla
    case 'eliminar_tabla':
        
        $idTabla = intval($datosEntrada['id_tabla'] ?? 0);
        
        if ($idTabla <= 0) {
            respuestaError('ID de tabla no valido');
        }
        
        // Verifica que la tabla no este enviada
        $consulta = $conexion->prepare("SELECT nombre, estado, id_usuario_creacion FROM tablas_movimientos WHERE id_tabla = ?");
        $consulta->execute([$idTabla]);
        $tabla = $consulta->fetch();
        
        if (!$tabla) {
            respuestaError('Tabla no encontrada');
        }
        
       
        if (tieneRol(ROL_ADMIN_SERVICIOS) && !tieneRol(ROL_JEFA_SERVICIOS) && !tieneRol(ROL_SUPERADMIN)) {
            if ($tabla['id_usuario_creacion'] != obtenerIdUsuarioActual()) {
                respuestaError('Acceso denegado: No puedes eliminar una tabla de otro usuario.', 403);
            }
        }
        // ------------------------------------
        
        if ($tabla['estado'] === 'enviado') {
            respuestaError('No se puede eliminar una tabla ya enviada');
        }
        
        try {
            // Elimina la tabla (los alumnos se eliminan por CASCADE)
            $consulta = $conexion->prepare("DELETE FROM tablas_movimientos WHERE id_tabla = ?");
            $consulta->execute([$idTabla]);
            
            registrarEnBitacora('ELIMINAR_TABLA_MOV', "Tabla eliminada: {$tabla['nombre']} (ID: $idTabla)");
            
            respuestaExitosa(null, 'Tabla eliminada correctamente');
            
        } catch (Exception $e) {
            respuestaError('Error al eliminar tabla: ' . $e->getMessage());
        }
        break;
    
    
    // ALUMNOS

    
    // Agregar alumno a una tabla
    case 'agregar_alumno':
        
        $idTabla = intval($datosEntrada['id_tabla'] ?? 0);
        
        if ($idTabla <= 0) {
            respuestaError('ID de tabla no valido');
        }
        
        // Verifica que la tabla exista y no este enviada
        $consulta = $conexion->prepare("SELECT estado, id_usuario_creacion FROM tablas_movimientos WHERE id_tabla = ?");
        $consulta->execute([$idTabla]);
        $tabla = $consulta->fetch();
        
        if (!$tabla) {
            respuestaError('Tabla no encontrada');
        }
        
        
        if (tieneRol(ROL_ADMIN_SERVICIOS) && !tieneRol(ROL_JEFA_SERVICIOS) && !tieneRol(ROL_SUPERADMIN)) {
            if ($tabla['id_usuario_creacion'] != obtenerIdUsuarioActual()) {
                respuestaError('Acceso denegado: No puedes agregar alumnos a una tabla de otro usuario.', 403);
            }
        }
       
        
        if ($tabla['estado'] === 'enviado') {
            respuestaError('No se puede agregar alumnos a una tabla ya enviada');
        }
        
        // Procesa y normaliza los datos
        $procesoNormalizacion = procesarDatosAlumno($datosEntrada);
        $datosAlumno = $procesoNormalizacion['datos'];
        $fueNormalizado = $procesoNormalizacion['fue_normalizado'];
        $datosOriginales = $procesoNormalizacion['datos_originales'];
        $cambiosNormalizacion = $procesoNormalizacion['cambios'];
        
        // Valida datos
        $errores = validarDatosAlumno($datosAlumno);
        $tieneErrores = !empty($errores);
        $erroresDetalle = implode(', ', $errores);
        
        try {
            // Obtiene el siguiente numero de cuenta
            $consulta = $conexion->prepare("SELECT COALESCE(MAX(numero_cuenta), 0) + 1 as siguiente FROM tabla_alumnos WHERE id_tabla = ?");
            $consulta->execute([$idTabla]);
            $numeroCuenta = $consulta->fetch()['siguiente'];
            
            $consulta = $conexion->prepare("
                INSERT INTO tabla_alumnos 
                (id_tabla, numero_afiliacion, digito_verificador, apellido_paterno, apellido_materno, nombres, curp, numero_cuenta, tiene_errores, errores_detalle, datos_originales)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $consulta->execute([
                $idTabla,
                $datosAlumno['numero_afiliacion'],
                $datosAlumno['digito_verificador'],
                $datosAlumno['apellido_paterno'],
                $datosAlumno['apellido_materno'],
                $datosAlumno['nombres'],
                $datosAlumno['curp'],
                $numeroCuenta,
                $tieneErrores ? 1 : 0,
                $erroresDetalle,
                $datosOriginales ? json_encode($datosOriginales) : null
            ]);
            
            $idRegistro = $conexion->lastInsertId();
            
            // Actualiza conteo de la tabla
            actualizarConteoTabla($conexion, $idTabla);
            
            // Prepara mensaje
            $mensaje = 'Alumno agregado correctamente';
            if ($tieneErrores) {
                $mensaje = 'Alumno agregado con errores: ' . $erroresDetalle;
            } elseif ($fueNormalizado) {
                $mensaje = 'Alumno agregado. Caracteres normalizados: ' . implode(', ', $cambiosNormalizacion);
            }
            
            respuestaExitosa([
                'id_registro' => $idRegistro,
                'numero_cuenta' => $numeroCuenta,
                'tiene_errores' => $tieneErrores,
                'errores' => $errores,
                'fue_normalizado' => $fueNormalizado,
                'cambios_normalizacion' => $cambiosNormalizacion
            ], $mensaje);
            
        } catch (Exception $e) {
            respuestaError('Error al agregar alumno: ' . $e->getMessage());
        }
        break;
    
    // Editar alumno
    case 'editar_alumno':
        
        $idRegistro = intval($datosEntrada['id_registro'] ?? 0);
        
        if ($idRegistro <= 0) {
            respuestaError('ID de registro no valido');
        }
        
        // Verifica que el alumno exista y la tabla no este enviada
        $consulta = $conexion->prepare("
            SELECT a.id_tabla, t.estado, t.id_usuario_creacion 
            FROM tabla_alumnos a
            INNER JOIN tablas_movimientos t ON a.id_tabla = t.id_tabla
            WHERE a.id_registro = ?
        ");
        $consulta->execute([$idRegistro]);
        $registro = $consulta->fetch();
        
        if (!$registro) {
            respuestaError('Registro no encontrado');
        }
        
        
        if (tieneRol(ROL_ADMIN_SERVICIOS) && !tieneRol(ROL_JEFA_SERVICIOS) && !tieneRol(ROL_SUPERADMIN)) {
            if ($registro['id_usuario_creacion'] != obtenerIdUsuarioActual()) {
                respuestaError('Acceso denegado: No puedes editar alumnos en una tabla de otro usuario.', 403);
            }
        }
        
        
        if ($registro['estado'] === 'enviado') {
            respuestaError('No se puede editar alumnos de una tabla ya enviada');
        }
        
        // Procesa y normaliza los datos
        $procesoNormalizacion = procesarDatosAlumno($datosEntrada);
        $datosAlumno = $procesoNormalizacion['datos'];
        $fueNormalizado = $procesoNormalizacion['fue_normalizado'];
        $datosOriginales = $procesoNormalizacion['datos_originales'];
        $cambiosNormalizacion = $procesoNormalizacion['cambios'];
        
        // Valida datos
        $errores = validarDatosAlumno($datosAlumno);
        $tieneErrores = !empty($errores);
        $erroresDetalle = implode(', ', $errores);
        
        try {
            $consulta = $conexion->prepare("
                UPDATE tabla_alumnos SET 
                    numero_afiliacion = ?, digito_verificador = ?, 
                    apellido_paterno = ?, apellido_materno = ?, 
                    nombres = ?, curp = ?,
                    tiene_errores = ?, errores_detalle = ?,
                    datos_originales = ?
                WHERE id_registro = ?
            ");
            $consulta->execute([
                $datosAlumno['numero_afiliacion'],
                $datosAlumno['digito_verificador'],
                $datosAlumno['apellido_paterno'],
                $datosAlumno['apellido_materno'],
                $datosAlumno['nombres'],
                $datosAlumno['curp'],
                $tieneErrores ? 1 : 0,
                $erroresDetalle,
                $datosOriginales ? json_encode($datosOriginales) : null,
                $idRegistro
            ]);
            
            // Actualiza conteo de la tabla
            actualizarConteoTabla($conexion, $registro['id_tabla']);
            
            // Prepara mensaje
            $mensaje = 'Alumno actualizado correctamente';
            if ($tieneErrores) {
                $mensaje = 'Alumno actualizado con errores';
            } elseif ($fueNormalizado) {
                $mensaje = 'Alumno actualizado. Caracteres normalizados: ' . implode(', ', $cambiosNormalizacion);
            }
            
            respuestaExitosa([
                'tiene_errores' => $tieneErrores,
                'errores' => $errores,
                'fue_normalizado' => $fueNormalizado,
                'cambios_normalizacion' => $cambiosNormalizacion
            ], $mensaje);
            
        } catch (Exception $e) {
            respuestaError('Error al actualizar alumno: ' . $e->getMessage());
        }
        break;
    
    // Eliminar alumno
    case 'eliminar_alumno':
        
        $idRegistro = intval($datosEntrada['id_registro'] ?? 0);
        
        if ($idRegistro <= 0) {
            respuestaError('ID de registro no valido');
        }
        
        // Verifica que el alumno exista y la tabla no este enviada
        $consulta = $conexion->prepare("
            SELECT a.id_tabla, t.estado, t.id_usuario_creacion 
            FROM tabla_alumnos a
            INNER JOIN tablas_movimientos t ON a.id_tabla = t.id_tabla
            WHERE a.id_registro = ?
        ");
        $consulta->execute([$idRegistro]);
        $registro = $consulta->fetch();
        
        if (!$registro) {
            respuestaError('Registro no encontrado');
        }
        
      
        if (tieneRol(ROL_ADMIN_SERVICIOS) && !tieneRol(ROL_JEFA_SERVICIOS) && !tieneRol(ROL_SUPERADMIN)) {
            if ($registro['id_usuario_creacion'] != obtenerIdUsuarioActual()) {
                respuestaError('Acceso denegado: No puedes eliminar alumnos en una tabla de otro usuario.', 403);
            }
        }
        
        
        if ($registro['estado'] === 'enviado') {
            respuestaError('No se puede eliminar alumnos de una tabla ya enviada');
        }
        
        try {
            $consulta = $conexion->prepare("DELETE FROM tabla_alumnos WHERE id_registro = ?");
            $consulta->execute([$idRegistro]);
            
            // Renumera los numeros de cuenta
            renumerarCuentas($conexion, $registro['id_tabla']);
            
            // Actualiza conteo de la tabla
            actualizarConteoTabla($conexion, $registro['id_tabla']);
            
            respuestaExitosa(null, 'Alumno eliminado correctamente');
            
        } catch (Exception $e) {
            respuestaError('Error al eliminar alumno: ' . $e->getMessage());
        }
        break;
    
    // Listar tablas pendientes de validacion (para Admin SE)
    case 'listar_pendientes':
        
        try {
            $consulta = $conexion->prepare("
                SELECT t.*, s.nombre as subcarpeta_nombre, c.nombre as carpeta_nombre,
                       u.nombre_completo as creador,
                       (SELECT COUNT(*) FROM tabla_alumnos WHERE id_tabla = t.id_tabla) as total_alumnos
                FROM tablas_movimientos t
                INNER JOIN subcarpetas_imss s ON t.id_subcarpeta = s.id_subcarpeta
                INNER JOIN carpetas_imss c ON s.id_carpeta = c.id_carpeta
                LEFT JOIN usuarios u ON t.id_usuario_creacion = u.id_usuario
                WHERE t.estado = 'borrador' AND t.id_usuario_creacion != ?
                ORDER BY t.fecha_creacion DESC
            ");
            $consulta->execute([obtenerIdUsuarioActual()]);
            
            respuestaExitosa($consulta->fetchAll());
            
        } catch (Exception $e) {
            respuestaError('Error al obtener tablas pendientes');
        }
        break;
    
    default:
        respuestaError('Accion no valida', 400);
}
