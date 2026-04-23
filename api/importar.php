<?php
/**
 * SI FUNCIONA NO LE MUEVAS!!!!!
 * SIAE-IMSS - API de Importacion Excel
 * Procesa archivos Excel y crea tablas de movimientos
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

// Solo Jefa de Servicios y Admin Servicios pueden importar
if (!tieneRol(ROL_JEFA_SERVICIOS) && !tieneRol(ROL_ADMIN_SERVICIOS) && !tieneRol(ROL_SUPERADMIN)) {
    respuestaError('Sin permisos para importar', 403);
}

// Conexion a la base de datos
$conexion = obtenerConexion();

// Obtiene la accion
$accion = $_POST['action'] ?? obtenerGet('action', '');

/**
 * Normaliza caracteres especiales a ASCII
 * Ñ -> #, acentos -> sin acento
 */
function normalizarTextoImport($texto) {
    $original = $texto;
    $cambios = [];
    
    $mapa = [
        'Ñ' => '#', 'ñ' => '#',
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
    
    foreach ($mapa as $especial => $reemplazo) {
        if (mb_strpos($texto, $especial) !== false) {
            $cambios[] = "$especial → $reemplazo";
        }
    }
    
    $textoNormalizado = strtr($texto, $mapa);
    
    return [
        'texto' => mb_strtoupper($textoNormalizado),
        'normalizado' => !empty($cambios),
        'cambios' => $cambios
    ];
}

/**
 * Valida los datos de un alumno y retorna errores encontrados
 */
function validarDatosAlumnoImport($datos) {
    $errores = [];
    
    $nss = trim($datos['nss'] ?? '');
    if (empty($nss)) {
        $errores[] = 'NSS vacío';
    } elseif (!preg_match('/^\d{10}$/', $nss)) {
        $errores[] = 'NSS debe ser 10 dígitos';
    }
    
    $dv = trim($datos['digito_verificador'] ?? '');
    if (empty($dv)) {
        $errores[] = 'DV vacío';
    } elseif (!preg_match('/^\d$/', $dv)) {
        $errores[] = 'DV inválido';
    }
    
    if (empty(trim($datos['apellido_paterno'] ?? ''))) {
        $errores[] = 'Sin apellido paterno';
    }
    
    if (empty(trim($datos['nombres'] ?? ''))) {
        $errores[] = 'Sin nombre';
    }
    
    $curp = strtoupper(trim($datos['curp'] ?? ''));
    if (!empty($curp) && strlen($curp) != 18) {
        $errores[] = 'CURP inválida';
    }
    
    return $errores;
}

switch ($accion) {
    
    case 'procesar':
        
        if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            respuestaError('No se recibio ningun archivo');
        }
        
        $archivo = $_FILES['archivo'];
        $idSubcarpeta = intval($_POST['id_subcarpeta'] ?? 0);
        $tipo = $_POST['tipo'] ?? '';
        $fechaMovimiento = $_POST['fecha_movimiento'] ?? date('Y-m-d');
        
        if ($idSubcarpeta <= 0) {
            respuestaError('Debe seleccionar una subcarpeta');
        }
        
        if (!in_array($tipo, ['alta', 'baja'])) {
            respuestaError('Tipo de movimiento no valido');
        }
        
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'xls'])) {
            respuestaError('Solo se permiten archivos Excel (.xlsx, .xls)');
        }
        
        $consulta = $conexion->prepare("SELECT id_subcarpeta FROM subcarpetas_imss WHERE id_subcarpeta = ? AND activo = 1");
        $consulta->execute([$idSubcarpeta]);
        if (!$consulta->fetch()) {
            respuestaError('La subcarpeta seleccionada no existe');
        }
        
        try {
            $rutaTemporal = sys_get_temp_dir() . '/' . uniqid('excel_') . '.' . $extension;
            move_uploaded_file($archivo['tmp_name'], $rutaTemporal);
            
            require_once __DIR__ . '/../includes/SimpleXLSX.php';
            
            if ($extension === 'xlsx') {
                $xlsx = SimpleXLSX::parse($rutaTemporal);
            } else {
                unlink($rutaTemporal);
                respuestaError('Formato .xls no soportado, use .xlsx');
            }
            
            if (!$xlsx) {
                unlink($rutaTemporal);
                respuestaError('Error al leer el archivo: ' . SimpleXLSX::parseError());
            }
            
            $filas = $xlsx->rows();
            unlink($rutaTemporal);
            
            if (count($filas) < 2) {
                respuestaError('El archivo no contiene datos');
            }
            
            $encabezados = array_map('strtoupper', array_map('trim', $filas[0]));
            
            $colMap = [
                'nss' => -1,
                'digito_verificador' => -1,
                'apellido_paterno' => -1,
                'apellido_materno' => -1,
                'nombres' => -1,
                'curp' => -1
            ];
            
            foreach ($encabezados as $idx => $encabezado) {
                if (strpos($encabezado, 'AFILIACION') !== false || $encabezado === 'NSS' || strpos($encabezado, 'NUMERO AFILIACION') !== false) {
                    $colMap['nss'] = $idx;
                } elseif ($encabezado === 'DIGITO VERIFICADOR' || $encabezado === 'DV' || strpos($encabezado, 'DIG VERIFICADOR') !== false) {
                    $colMap['digito_verificador'] = $idx;
                } elseif (strpos($encabezado, 'PATERNO') !== false) {
                    $colMap['apellido_paterno'] = $idx;
                } elseif (strpos($encabezado, 'MATERNO') !== false) {
                    $colMap['apellido_materno'] = $idx;
                } elseif (strpos($encabezado, 'NOMBRE') !== false && strpos($encabezado, 'APELLIDO') === false) {
                    $colMap['nombres'] = $idx;
                } elseif ($encabezado === 'CURP') {
                    $colMap['curp'] = $idx;
                }
            }
            
            $registros = [];
            $totalValidos = 0;
            $totalErrores = 0;
            $totalNormalizados = 0;
            
            for ($i = 1; $i < count($filas); $i++) {
                $fila = $filas[$i];
                
                $filaVacia = true;
                foreach ($fila as $celda) {
                    if (!empty(trim($celda))) {
                        $filaVacia = false;
                        break;
                    }
                }
                if ($filaVacia) continue;
                
                $datosCrudos = [
                    'nss' => $colMap['nss'] >= 0 ? trim($fila[$colMap['nss']] ?? '') : '',
                    'digito_verificador' => $colMap['digito_verificador'] >= 0 ? trim($fila[$colMap['digito_verificador']] ?? '') : '',
                    'apellido_paterno' => $colMap['apellido_paterno'] >= 0 ? trim($fila[$colMap['apellido_paterno']] ?? '') : '',
                    'apellido_materno' => $colMap['apellido_materno'] >= 0 ? trim($fila[$colMap['apellido_materno']] ?? '') : '',
                    'nombres' => $colMap['nombres'] >= 0 ? trim($fila[$colMap['nombres']] ?? '') : '',
                    'curp' => $colMap['curp'] >= 0 ? trim($fila[$colMap['curp']] ?? '') : ''
                ];
                
                $datosOriginales = [];
                $fueNormalizado = false;
                $cambiosNormalizacion = [];
                
                $camposTexto = ['apellido_paterno', 'apellido_materno', 'nombres'];
                foreach ($camposTexto as $campo) {
                    $resultado = normalizarTextoImport($datosCrudos[$campo]);
                    if ($resultado['normalizado']) {
                        $datosOriginales[$campo] = $datosCrudos[$campo];
                        $fueNormalizado = true;
                        $cambiosNormalizacion = array_merge($cambiosNormalizacion, $resultado['cambios']);
                    }
                    $datosCrudos[$campo] = $resultado['texto'];
                }
                
                $datosCrudos['curp'] = strtoupper($datosCrudos['curp']);
                
                $errores = validarDatosAlumnoImport($datosCrudos);
                $tieneErrores = !empty($errores);
                
                if ($tieneErrores) {
                    $totalErrores++;
                } else {
                    $totalValidos++;
                }
                
                if ($fueNormalizado) {
                    $totalNormalizados++;
                }
                
                $registros[] = [
                    'fila' => $i + 1,
                    'datos' => $datosCrudos,
                    'errores' => $errores,
                    'tiene_errores' => $tieneErrores,
                    'fue_normalizado' => $fueNormalizado,
                    'datos_originales' => $fueNormalizado ? $datosOriginales : null,
                    'cambios_normalizacion' => $cambiosNormalizacion
                ];
            }
            
            if (empty($registros)) {
                respuestaError('No se encontraron registros validos');
            }
            
            $tipoTexto = $tipo === 'alta' ? 'Altas' : 'Bajas';
            $fechaFormateada = date('d-M-Y', strtotime($fechaMovimiento));
            $nombreTabla = "$tipoTexto $fechaFormateada (Importado)";
            
            $conexion->beginTransaction();
            
            $consulta = $conexion->prepare("
                INSERT INTO tablas_movimientos 
                (id_subcarpeta, tipo, nombre, fecha_movimiento, archivo_origen, total_registros, registros_con_errores, id_usuario_creacion)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $consulta->execute([
                $idSubcarpeta,
                $tipo,
                $nombreTabla,
                $fechaMovimiento,
                $archivo['name'],
                count($registros),
                $totalErrores,
                obtenerIdUsuarioActual()
            ]);
            
            $idTabla = $conexion->lastInsertId();
            
            $numeroCuenta = 1;
            foreach ($registros as $registro) {
                $datos = $registro['datos'];
                $erroresDetalle = implode(', ', $registro['errores']);
                $datosOriginalesJson = $registro['datos_originales'] ? json_encode($registro['datos_originales']) : null;
                
                $consulta = $conexion->prepare("
                    INSERT INTO tabla_alumnos 
                    (id_tabla, numero_afiliacion, digito_verificador, apellido_paterno, apellido_materno, nombres, curp, numero_cuenta, tiene_errores, errores_detalle, datos_originales)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $consulta->execute([
                    $idTabla,
                    $datos['nss'],
                    $datos['digito_verificador'],
                    $datos['apellido_paterno'],
                    $datos['apellido_materno'],
                    $datos['nombres'],
                    $datos['curp'],
                    $numeroCuenta,
                    $registro['tiene_errores'] ? 1 : 0,
                    $erroresDetalle,
                    $datosOriginalesJson
                ]);
                
                $numeroCuenta++;
            }
            
            $conexion->commit();
            
            registrarEnBitacora('IMPORTAR_EXCEL', "Excel importado: {$archivo['name']} - {$nombreTabla} (ID: $idTabla, $totalValidos válidos, $totalErrores errores, $totalNormalizados normalizados)");
            
            respuestaExitosa([
                'id_tabla' => $idTabla,
                'nombre_tabla' => $nombreTabla,
                'total_registros' => count($registros),
                'validos' => $totalValidos,
                'con_errores' => $totalErrores,
                'normalizados' => $totalNormalizados
            ], 'Excel importado correctamente');
            
        } catch (Exception $e) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }
            respuestaError('Error al procesar Excel: ' . $e->getMessage());
        }
        break;
    
    default:
        respuestaError('Accion no valida', 400);
}
