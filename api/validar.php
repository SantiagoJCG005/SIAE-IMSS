<?php
/**
 * SI FUNCIONA NO LE MUEVAS!!!!!
 * SIAE-IMSS - API de Validacion
 * Permite a la Jefa validar movimientos creados por empleados
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

// Solo Jefa de Servicios puede validar
if (!tieneRol(ROL_JEFA_SERVICIOS) && !tieneRol(ROL_SUPERADMIN)) {
    respuestaError('Sin permisos para validar', 403);
}

// Conexion a la base de datos
$conexion = obtenerConexion();

// Lee datos JSON enviados
$datosEntrada = json_decode(file_get_contents('php://input'), true);

// Obtiene la accion a realizar
$accion = $datosEntrada['action'] ?? obtenerGet('action', '');

switch ($accion) {
    
    // Listar tablas pendientes de validacion
    case 'listar_pendientes':
        
        try {
            $consulta = $conexion->query("
                SELECT t.*, 
                       s.nombre as subcarpeta_nombre, 
                       c.nombre as carpeta_nombre,
                       u.nombre_completo as creador,
                       (SELECT COUNT(*) FROM tabla_alumnos WHERE id_tabla = t.id_tabla) as total_alumnos,
                       (SELECT COUNT(*) FROM tabla_alumnos WHERE id_tabla = t.id_tabla AND tiene_errores = 1) as alumnos_con_errores
                FROM tablas_movimientos t
                INNER JOIN subcarpetas_imss s ON t.id_subcarpeta = s.id_subcarpeta
                INNER JOIN carpetas_imss c ON s.id_carpeta = c.id_carpeta
                LEFT JOIN usuarios u ON t.id_usuario_creacion = u.id_usuario
                WHERE t.estado = 'borrador' 
                  AND t.id_usuario_creacion != " . obtenerIdUsuarioActual() . "
                ORDER BY t.fecha_creacion DESC
            ");
            
            respuestaExitosa($consulta->fetchAll());
            
        } catch (Exception $e) {
            respuestaError('Error al obtener tablas pendientes: ' . $e->getMessage());
        }
        break;
    
    // Aprobar una tabla
    case 'aprobar':
        
        $idTabla = intval($datosEntrada['id_tabla'] ?? 0);
        
        if ($idTabla <= 0) {
            respuestaError('ID de tabla no valido');
        }
        
        try {
            // Verifica que la tabla exista y este en borrador
            $consulta = $conexion->prepare("SELECT estado, nombre, id_usuario_creacion FROM tablas_movimientos WHERE id_tabla = ?");
            $consulta->execute([$idTabla]);
            $tabla = $consulta->fetch();

            if (!$tabla) {
                respuestaError('Tabla no encontrada');
            }

            if ($tabla['estado'] !== 'borrador') {
                respuestaError('Solo se pueden aprobar tablas en borrador');
            }

            // Verifica que no tenga errores
            $consulta = $conexion->prepare("SELECT COUNT(*) as total FROM tabla_alumnos WHERE id_tabla = ? AND tiene_errores = 1");
            $consulta->execute([$idTabla]);
            $errores = $consulta->fetch()['total'];

            if ($errores > 0) {
                respuestaError("No se puede aprobar: hay $errores registro(s) con errores");
            }

            $idJefa = obtenerIdUsuarioActual();

            // Actualiza estado
            $consulta = $conexion->prepare("
                UPDATE tablas_movimientos
                SET estado = 'validado', id_usuario_validacion = ?, fecha_validacion = NOW()
                WHERE id_tabla = ?
            ");
            $consulta->execute([$idJefa, $idTabla]);

            registrarEnBitacora('VALIDAR_TABLA', "Tabla aprobada: {$tabla['nombre']} (ID: $idTabla)");

            // Notificar al Admin SE creador de la tabla
            if ($tabla['id_usuario_creacion']) {
                $usuarioJefa = obtenerUsuarioActual();
                $nombreJefa = $usuarioJefa['nombre_completo'] ?? 'La Jefa de Servicios';
                $notif = $conexion->prepare("
                    INSERT INTO notificaciones
                    (id_usuario_destino, id_usuario_origen, tipo, titulo, mensaje, referencia_tipo, referencia_id)
                    VALUES (?, ?, 'validacion_aprobada', ?, ?, 'tablas_movimientos', ?)
                ");
                $notif->execute([
                    $tabla['id_usuario_creacion'],
                    $idJefa,
                    '✅ Tabla aprobada: ' . $tabla['nombre'],
                    $nombreJefa . ' ha aprobado la tabla "' . $tabla['nombre'] . '". Los datos fueron validados correctamente.',
                    $idTabla
                ]);
            }

            respuestaExitosa(null, 'Tabla aprobada correctamente');
            
        } catch (Exception $e) {
            respuestaError('Error al aprobar tabla: ' . $e->getMessage());
        }
        break;
    
    // Rechazar una tabla
    case 'rechazar':
        
        $idTabla = intval($datosEntrada['id_tabla'] ?? 0);
        $motivo = trim($datosEntrada['motivo'] ?? '');
        
        if ($idTabla <= 0) {
            respuestaError('ID de tabla no valido');
        }
        
        try {
            // Verifica que la tabla exista
            $consulta = $conexion->prepare("SELECT estado, nombre, id_usuario_creacion FROM tablas_movimientos WHERE id_tabla = ?");
            $consulta->execute([$idTabla]);
            $tabla = $consulta->fetch();
            
            if (!$tabla) {
                respuestaError('Tabla no encontrada');
            }
            
            if ($tabla['estado'] !== 'borrador') {
                respuestaError('Solo se pueden rechazar tablas en borrador');
            }
            
            $idJefa = obtenerIdUsuarioActual();

            // Elimina la tabla
            $consulta = $conexion->prepare("DELETE FROM tablas_movimientos WHERE id_tabla = ?");
            $consulta->execute([$idTabla]);

            registrarEnBitacora('RECHAZAR_TABLA', "Tabla rechazada: {$tabla['nombre']} (ID: $idTabla)" . ($motivo ? " - Motivo: $motivo" : ''));

            // Notificar al Admin SE creador de la tabla
            if ($tabla['id_usuario_creacion']) {
                $usuarioJefa = obtenerUsuarioActual();
                $nombreJefa = $usuarioJefa['nombre_completo'] ?? 'La Jefa de Servicios';
                $mensajeRechazo = $nombreJefa . ' ha rechazado la tabla "' . $tabla['nombre'] . '".';
                if ($motivo) {
                    $mensajeRechazo .= "\n\nMotivo: " . $motivo;
                }
                $notif = $conexion->prepare("
                    INSERT INTO notificaciones
                    (id_usuario_destino, id_usuario_origen, tipo, titulo, mensaje, referencia_tipo, referencia_id)
                    VALUES (?, ?, 'validacion_rechazada', ?, ?, 'tablas_movimientos', ?)
                ");
                $notif->execute([
                    $tabla['id_usuario_creacion'],
                    $idJefa,
                    '❌ Tabla rechazada: ' . $tabla['nombre'],
                    $mensajeRechazo,
                    $idTabla
                ]);
            }

            respuestaExitosa(null, 'Tabla rechazada y eliminada');
            
        } catch (Exception $e) {
            respuestaError('Error al rechazar tabla: ' . $e->getMessage());
        }
        break;
    
    // Obtener estadisticas de validacion
    case 'estadisticas':
        
        try {
            $stats = [];
            
            // Pendientes de validar (de otros usuarios)
            $consulta = $conexion->prepare("
                SELECT COUNT(*) as total 
                FROM tablas_movimientos 
                WHERE estado = 'borrador' AND id_usuario_creacion != ?
            ");
            $consulta->execute([obtenerIdUsuarioActual()]);
            $stats['pendientes'] = $consulta->fetch()['total'];
            
            // Validadas este mes
            $consulta = $conexion->query("
                SELECT COUNT(*) as total 
                FROM tablas_movimientos 
                WHERE estado = 'validado' 
                  AND MONTH(fecha_validacion) = MONTH(CURRENT_DATE())
                  AND YEAR(fecha_validacion) = YEAR(CURRENT_DATE())
            ");
            $stats['validadas_mes'] = $consulta->fetch()['total'];
            
            // Enviadas este mes
            $consulta = $conexion->query("
                SELECT COUNT(*) as total 
                FROM tablas_movimientos 
                WHERE estado = 'enviado' 
                  AND MONTH(fecha_exportacion) = MONTH(CURRENT_DATE())
                  AND YEAR(fecha_exportacion) = YEAR(CURRENT_DATE())
            ");
            $stats['enviadas_mes'] = $consulta->fetch()['total'];
            
            respuestaExitosa($stats);
            
        } catch (Exception $e) {
            respuestaError('Error al obtener estadisticas');
        }
        break;
    
    default:
        respuestaError('Accion no valida', 400);
}
