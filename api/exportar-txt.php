<?php
/**
 * SIAE-IMSS - API de Exportacion TXT
 * Genera archivos TXT en formato IMSS para altas y bajas
 * 
 * ESTRUCTURA CORREGIDA según archivos del cliente:
 * - Línea de datos: 168 caracteres
 * - Línea de totales al final: 168 caracteres con contador y código institución
 * 
 * Configuración editable desde Superadmin > Configuración > IMSS
 */

// Carga archivos necesarios
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Verifica autenticacion
if (!estaLogueado()) {
    header('Content-Type: application/json');
    respuestaError('No autorizado', 401);
}

// Solo Jefa de Servicios o Superadmin puede exportar
if (!tieneRol(ROL_JEFA_SERVICIOS) && !tieneRol(ROL_SUPERADMIN)) {
    header('Content-Type: application/json');
    respuestaError('Sin permisos para exportar', 403);
}

// Conexion a la base de datos
$conexion = obtenerConexion();

// Obtiene la accion
$accion = obtenerGet('action', '');

/**
 * Normaliza caracteres especiales para el TXT
 * Ñ -> #, acentos -> sin acento
 */
function normalizarParaTxt($texto) {
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
    return strtr($texto, $mapa);
}

/**
 * Formatea un texto a longitud fija, rellenando con espacios
 */
function formatearCampo($texto, $longitud) {
    $texto = mb_strtoupper(trim($texto));
    $texto = normalizarParaTxt($texto);
    $texto = preg_replace('/[^A-Z0-9\s#]/', '', $texto);
    if (mb_strlen($texto) > $longitud) {
        return mb_substr($texto, 0, $longitud);
    }
    return str_pad($texto, $longitud, ' ', STR_PAD_RIGHT);
}

/**
 * Formatea un numero a longitud fija, rellenando con ceros a la izquierda
 */
function formatearNumero($numero, $longitud) {
    return str_pad($numero, $longitud, '0', STR_PAD_LEFT);
}

/**
 * Genera una línea del TXT para ALTA (168 caracteres)
 * 
 * Estructura:
 * Pos 1-10:   Registro Patronal (10)
 * Pos 11:     DV Patronal (1)
 * Pos 12-21:  NSS Alumno (10)
 * Pos 22:     DV Alumno (1)
 * Pos 23-49:  Apellido Paterno (27)
 * Pos 50-76:  Apellido Materno (27)
 * Pos 77-103: Nombres (27)
 * Pos 104-118: Prefijo "000000      210" (15)
 * Pos 119-126: Fecha ddmmaaaa (8)
 * Pos 127-129: UMF (3)
 * Pos 130-131: Espacios (2)
 * Pos 132-133: Código Op "08" (2)
 * Pos 134-138: Espacios (5)
 * Pos 139-148: Número Cuenta (10)
 * Pos 149:     Espacio (1)
 * Pos 150-167: CURP (18)
 * Pos 168:     Sufijo "9" (1)
 */
function generarLineaAlta($alumno, $configPatronal, $fechaMov) {
    $linea = '';
    $linea .= formatearCampo($configPatronal['registro_patronal'], 10);  // 1-10
    $linea .= $configPatronal['digito_verificador'];                     // 11
    $linea .= formatearNumero($alumno['numero_afiliacion'], 10);         // 12-21
    $linea .= $alumno['digito_verificador'];                             // 22
    $linea .= formatearCampo($alumno['apellido_paterno'], 27);           // 23-49
    $linea .= formatearCampo($alumno['apellido_materno'] ?? '', 27);     // 50-76
    $linea .= formatearCampo($alumno['nombres'], 27);                    // 77-103
    $linea .= $configPatronal['prefijo_alta'] ?? '000000      210';      // 104-118 (15 chars)
    $linea .= $fechaMov;                                                 // 119-126 (8 chars)
    $linea .= formatearCampo($configPatronal['umf_alta'] ?? '001', 3);   // 127-129
    $linea .= '  ';                                                      // 130-131
    $linea .= $configPatronal['codigo_operacion_alta'] ?? '08';          // 132-133
    $linea .= '     ';                                                   // 134-138
    $linea .= formatearNumero($alumno['numero_cuenta'], 10);             // 139-148
    $linea .= ' ';                                                       // 149
    $linea .= formatearCampo($alumno['curp'] ?? '', 18);                 // 150-167
    $linea .= '9';                                                       // 168
    return $linea;
}

/**
 * Genera una línea del TXT para BAJA (168 caracteres)
 * 
 * Estructura:
 * Pos 1-10:   Registro Patronal (10)
 * Pos 11:     DV Patronal (1)
 * Pos 12-21:  NSS Alumno (10)
 * Pos 22:     DV Alumno (1)
 * Pos 23-49:  Apellido Paterno (27)
 * Pos 50-76:  Apellido Materno (27)
 * Pos 77-103: Nombres (27)
 * Pos 104-118: Prefijo "000000000000000" (15)
 * Pos 119-126: Fecha ddmmaaaa (8)
 * Pos 127-129: UMF "000" (3)
 * Pos 130-133: Código Op "0002" (4)
 * Pos 134-138: Espacios (5)
 * Pos 139-148: Número Cuenta (10)
 * Pos 149:     Código "6" (1)
 * Pos 150-167: VACÍO - 18 espacios (18)
 * Pos 168:     Sufijo "9" (1)
 */
function generarLineaBaja($alumno, $configPatronal, $fechaMov) {
    $linea = '';
    $linea .= formatearCampo($configPatronal['registro_patronal'], 10);  // 1-10
    $linea .= $configPatronal['digito_verificador'];                     // 11
    $linea .= formatearNumero($alumno['numero_afiliacion'], 10);         // 12-21
    $linea .= $alumno['digito_verificador'];                             // 22
    $linea .= formatearCampo($alumno['apellido_paterno'], 27);           // 23-49
    $linea .= formatearCampo($alumno['apellido_materno'] ?? '', 27);     // 50-76
    $linea .= formatearCampo($alumno['nombres'], 27);                    // 77-103
    $linea .= $configPatronal['prefijo_baja'] ?? '000000000000000';      // 104-118 (15 chars)
    $linea .= $fechaMov;                                                 // 119-126 (8 chars)
    $linea .= formatearCampo($configPatronal['umf_baja'] ?? '000', 3);   // 127-129
    
    // Código operación baja: "00" + "02" = "0002" (4 chars)
    $codigoOpBaja = $configPatronal['codigo_operacion_baja'] ?? '02';
    $linea .= '00' . str_pad($codigoOpBaja, 2, '0', STR_PAD_LEFT);        // 130-133
    
    $linea .= '     ';                                                   // 134-138
    $linea .= formatearNumero($alumno['numero_cuenta'], 10);             // 139-148
    $linea .= '6';                                                       // 149
    $linea .= str_repeat(' ', 18);                                       // 150-167 (CURP vacío en bajas)
    $linea .= '9';                                                       // 168
    return $linea;
}

/**
 * Genera la línea de TOTALES al final del archivo (168 caracteres)
 * 
 * Estructura:
 * Pos 1-13:   Asteriscos "************" (13)
 * Pos 14-56:  Espacios (43)
 * Pos 57-62:  Contador de registros (6)
 * Pos 63-133: Espacios (71)
 * Pos 134-138: Código Institución (5)
 * Pos 139-167: Espacios (29)
 * Pos 168:    Sufijo "9" (1)
 */
function generarLineaTotales($totalRegistros, $codigoInstitucion = '01402') {
    $linea = '';
    $linea .= str_repeat('*', 13);                                       // 1-13: Asteriscos
    $linea .= str_repeat(' ', 43);                                       // 14-56: Espacios
    $linea .= str_pad($totalRegistros, 6, '0', STR_PAD_LEFT);           // 57-62: Contador
    $linea .= str_repeat(' ', 71);                                       // 63-133: Espacios
    $linea .= str_pad($codigoInstitucion, 5, ' ', STR_PAD_RIGHT);       // 134-138: Código Institución
    $linea .= str_repeat(' ', 29);                                       // 139-167: Espacios
    $linea .= '9';                                                       // 168: Sufijo
    return $linea;
}

switch ($accion) {
    
    case 'generar':
        
        $idTabla = intval(obtenerGet('id_tabla', 0));
        
        if ($idTabla <= 0) {
            header('Content-Type: application/json');
            respuestaError('ID de tabla no valido');
        }
        
        try {
            // Obtener datos de la tabla
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
                header('Content-Type: application/json');
                respuestaError('Tabla no encontrada');
            }
            
            // Verificar que no hay errores
            $consulta = $conexion->prepare("SELECT COUNT(*) as total FROM tabla_alumnos WHERE id_tabla = ? AND tiene_errores = 1");
            $consulta->execute([$idTabla]);
            $errores = $consulta->fetch()['total'];
            
            if ($errores > 0) {
                header('Content-Type: application/json');
                respuestaError("No se puede generar: hay $errores registro(s) con errores");
            }
            
            // Contar alumnos
            $consulta = $conexion->prepare("SELECT COUNT(*) as total FROM tabla_alumnos WHERE id_tabla = ?");
            $consulta->execute([$idTabla]);
            $totalAlumnos = $consulta->fetch()['total'];
            
            if ($totalAlumnos == 0) {
                header('Content-Type: application/json');
                respuestaError('La tabla no tiene alumnos');
            }
            
            // Obtener configuración patronal
            $configPatronal = $conexion->query("SELECT * FROM configuracion_patronal WHERE activo = 1 LIMIT 1")->fetch();
            
            if (!$configPatronal) {
                header('Content-Type: application/json');
                respuestaError('No hay configuracion patronal activa');
            }
            
            // Obtener alumnos
            $consulta = $conexion->prepare("SELECT * FROM tabla_alumnos WHERE id_tabla = ? ORDER BY numero_cuenta");
            $consulta->execute([$idTabla]);
            $alumnos = $consulta->fetchAll();
            
            $esAlta = $tabla['tipo'] === 'alta';
            $fechaMov = date('dmY', strtotime($tabla['fecha_movimiento']));
            
            // Generar líneas de datos
            $lineas = [];
            
            foreach ($alumnos as $alumno) {
                if ($esAlta) {
                    $lineas[] = generarLineaAlta($alumno, $configPatronal, $fechaMov);
                } else {
                    $lineas[] = generarLineaBaja($alumno, $configPatronal, $fechaMov);
                }
            }
            
            // Agregar línea de TOTALES al final
            $codigoInstitucion = $configPatronal['codigo_institucion'] ?? '01402';
            $lineas[] = generarLineaTotales($totalAlumnos, $codigoInstitucion);
            
            // Nombre del archivo
            $tipoTexto = $esAlta ? 'ALTA' : 'BAJA';
            $fechaArchivo = date('dmY', strtotime($tabla['fecha_movimiento']));
            $nombreArchivo = "IMSS_{$fechaArchivo}_{$tipoTexto}.txt";
            
            // Actualizar estado de la tabla
            $consulta = $conexion->prepare("
                UPDATE tablas_movimientos 
                SET estado = 'enviado', fecha_envio = NOW(), archivo_txt_generado = ?
                WHERE id_tabla = ?
            ");
            $consulta->execute([$nombreArchivo, $idTabla]);
            
            // Registrar en bitácora
            registrarEnBitacora('EXPORTAR_TXT', "TXT generado: $nombreArchivo (Tabla ID: $idTabla, $totalAlumnos registros)");
            
            // Generar contenido con saltos de línea Windows (CRLF)
            $contenido = implode("\r\n", $lineas);
            
            // Enviar archivo
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
            header('Content-Length: ' . strlen($contenido));
            header('Cache-Control: no-cache, must-revalidate');
            
            echo $contenido;
            exit;
            
        } catch (Exception $e) {
            header('Content-Type: application/json');
            respuestaError('Error al generar TXT: ' . $e->getMessage());
        }
        break;
    
    case 'preview':
        
        header('Content-Type: application/json');
        
        $idTabla = intval(obtenerGet('id_tabla', 0));
        
        if ($idTabla <= 0) {
            respuestaError('ID de tabla no valido');
        }
        
        try {
            $consulta = $conexion->prepare("SELECT tipo, fecha_movimiento FROM tablas_movimientos WHERE id_tabla = ?");
            $consulta->execute([$idTabla]);
            $tabla = $consulta->fetch();
            
            if (!$tabla) {
                respuestaError('Tabla no encontrada');
            }
            
            $consulta = $conexion->prepare("SELECT * FROM tabla_alumnos WHERE id_tabla = ? ORDER BY numero_cuenta LIMIT 5");
            $consulta->execute([$idTabla]);
            $alumnos = $consulta->fetchAll();
            
            $configPatronal = $conexion->query("SELECT * FROM configuracion_patronal WHERE activo = 1 LIMIT 1")->fetch();
            
            $preview = [];
            $esAlta = $tabla['tipo'] === 'alta';
            
            foreach ($alumnos as $alumno) {
                $preview[] = [
                    'nss' => $alumno['numero_afiliacion'] . '-' . $alumno['digito_verificador'],
                    'nombre' => $alumno['apellido_paterno'] . ' ' . $alumno['apellido_materno'] . ' ' . $alumno['nombres'],
                    'cuenta' => $alumno['numero_cuenta']
                ];
            }
            
            respuestaExitosa([
                'tipo' => $tabla['tipo'],
                'fecha' => $tabla['fecha_movimiento'],
                'registro_patronal' => $configPatronal['registro_patronal'],
                'codigo_institucion' => $configPatronal['codigo_institucion'] ?? '01402',
                'preview' => $preview
            ]);
            
        } catch (Exception $e) {
            respuestaError('Error al generar preview');
        }
        break;
    
    default:
        header('Content-Type: application/json');
        respuestaError('Accion no valida', 400);
}
