<?php
/**
 * SIAE-IMSS - API de Notificaciones
 * CRUD para el sistema de notificaciones internas
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar autenticación
if (!estaLogueado()) {
    respuestaError('No autenticado', 401);
}

$conexion = obtenerConexion();
$accion = $_GET['action'] ?? '';
$idUsuario = obtenerIdUsuarioActual();

switch ($accion) {
    
    /**
     * Obtener notificaciones del usuario actual
     */
    case 'listar':
        try {
            $soloNoLeidas = ($_GET['no_leidas'] ?? '0') === '1';
            $limite = intval($_GET['limite'] ?? 20);
            
            $sql = "
                SELECT 
                    n.*,
                    u.nombre_completo as nombre_origen,
                    u.username as username_origen
                FROM notificaciones n
                LEFT JOIN usuarios u ON n.id_usuario_origen = u.id_usuario
                WHERE n.id_usuario_destino = ?
            ";
            
            if ($soloNoLeidas) {
                $sql .= " AND n.leida = 0";
            }
            
            $sql .= " ORDER BY n.fecha_creacion DESC LIMIT ?";
            
            $consulta = $conexion->prepare($sql);
            $consulta->execute([$idUsuario, $limite]);
            $notificaciones = $consulta->fetchAll();
            
            respuestaExitosa($notificaciones);
            
        } catch (Exception $e) {
            respuestaError('Error al obtener notificaciones: ' . $e->getMessage());
        }
        break;
    
    /**
     * Contar notificaciones no leídas
     */
    case 'contar':
        try {
            $consulta = $conexion->prepare("
                SELECT COUNT(*) as total 
                FROM notificaciones 
                WHERE id_usuario_destino = ? AND leida = 0
            ");
            $consulta->execute([$idUsuario]);
            $resultado = $consulta->fetch();
            
            respuestaExitosa(['total' => intval($resultado['total'])]);
            
        } catch (Exception $e) {
            respuestaError('Error al contar notificaciones');
        }
        break;
    
    /**
     * Marcar notificación como leída
     */
    case 'marcar_leida':
        try {
            $idNotificacion = intval($_GET['id'] ?? 0);
            
            if ($idNotificacion <= 0) {
                respuestaError('ID de notificación no válido');
            }
            
            // Verificar que la notificación pertenece al usuario
            $consulta = $conexion->prepare("
                UPDATE notificaciones 
                SET leida = 1, fecha_lectura = NOW(), estado = 'vista'
                WHERE id_notificacion = ? AND id_usuario_destino = ?
            ");
            $consulta->execute([$idNotificacion, $idUsuario]);
            
            if ($consulta->rowCount() > 0) {
                respuestaExitosa(['mensaje' => 'Notificación marcada como leída']);
            } else {
                respuestaError('Notificación no encontrada');
            }
            
        } catch (Exception $e) {
            respuestaError('Error al marcar notificación');
        }
        break;
    
    /**
     * Marcar todas como leídas
     */
    case 'marcar_todas_leidas':
        try {
            $consulta = $conexion->prepare("
                UPDATE notificaciones 
                SET leida = 1, fecha_lectura = NOW(), estado = 'vista'
                WHERE id_usuario_destino = ? AND leida = 0
            ");
            $consulta->execute([$idUsuario]);
            
            respuestaExitosa([
                'mensaje' => 'Todas las notificaciones marcadas como leídas',
                'actualizadas' => $consulta->rowCount()
            ]);
            
        } catch (Exception $e) {
            respuestaError('Error al marcar notificaciones');
        }
        break;
    
    /**
     * Cambiar estado de notificación (revisada/problema)
     */
    case 'cambiar_estado':
        try {
            $idNotificacion = intval($_GET['id'] ?? 0);
            $nuevoEstado = $_GET['estado'] ?? '';
            
            $estadosValidos = ['vista', 'revisada', 'problema'];
            
            if ($idNotificacion <= 0) {
                respuestaError('ID de notificación no válido');
            }
            
            if (!in_array($nuevoEstado, $estadosValidos)) {
                respuestaError('Estado no válido. Usar: vista, revisada, problema');
            }
            
            $consulta = $conexion->prepare("
                UPDATE notificaciones 
                SET estado = ?, leida = 1, fecha_lectura = COALESCE(fecha_lectura, NOW())
                WHERE id_notificacion = ? AND id_usuario_destino = ?
            ");
            $consulta->execute([$nuevoEstado, $idNotificacion, $idUsuario]);
            
            if ($consulta->rowCount() > 0) {
                // Si se marcó como problema, notificar al usuario origen
                if ($nuevoEstado === 'problema') {
                    notificarProblema($conexion, $idNotificacion, $idUsuario);
                }
                
                respuestaExitosa(['mensaje' => "Estado actualizado a: {$nuevoEstado}"]);
            } else {
                respuestaError('Notificación no encontrada');
            }
            
        } catch (Exception $e) {
            respuestaError('Error al cambiar estado: ' . $e->getMessage());
        }
        break;
    
    /**
     * Obtener detalle de una notificación
     */
    case 'detalle':
        try {
            $idNotificacion = intval($_GET['id'] ?? 0);
            
            if ($idNotificacion <= 0) {
                respuestaError('ID de notificación no válido');
            }
            
            $consulta = $conexion->prepare("
                SELECT 
                    n.*,
                    u.nombre_completo as nombre_origen,
                    u.username as username_origen
                FROM notificaciones n
                LEFT JOIN usuarios u ON n.id_usuario_origen = u.id_usuario
                WHERE n.id_notificacion = ? AND n.id_usuario_destino = ?
            ");
            $consulta->execute([$idNotificacion, $idUsuario]);
            $notificacion = $consulta->fetch();
            
            if ($notificacion) {
                // Marcar como leída al ver detalle
                $update = $conexion->prepare("
                    UPDATE notificaciones 
                    SET leida = 1, fecha_lectura = COALESCE(fecha_lectura, NOW())
                    WHERE id_notificacion = ?
                ");
                $update->execute([$idNotificacion]);
                
                respuestaExitosa($notificacion);
            } else {
                respuestaError('Notificación no encontrada');
            }
            
        } catch (Exception $e) {
            respuestaError('Error al obtener detalle');
        }
        break;
    
    /**
     * Eliminar una notificación
     */
    case 'eliminar':
        try {
            $idNotificacion = intval($_GET['id'] ?? 0);
            
            if ($idNotificacion <= 0) {
                respuestaError('ID de notificación no válido');
            }
            
            $consulta = $conexion->prepare("
                DELETE FROM notificaciones 
                WHERE id_notificacion = ? AND id_usuario_destino = ?
            ");
            $consulta->execute([$idNotificacion, $idUsuario]);
            
            if ($consulta->rowCount() > 0) {
                respuestaExitosa(['mensaje' => 'Notificación eliminada']);
            } else {
                respuestaError('Notificación no encontrada');
            }
            
        } catch (Exception $e) {
            respuestaError('Error al eliminar notificación');
        }
        break;
    
    /**
     * Eliminar todas las notificaciones leídas
     */
    case 'eliminar_leidas':
        try {
            $consulta = $conexion->prepare("
                DELETE FROM notificaciones 
                WHERE id_usuario_destino = ? AND leida = 1
            ");
            $consulta->execute([$idUsuario]);
            
            respuestaExitosa([
                'mensaje' => 'Notificaciones leídas eliminadas',
                'eliminadas' => $consulta->rowCount()
            ]);
            
        } catch (Exception $e) {
            respuestaError('Error al eliminar notificaciones');
        }
        break;
    
    /**
     * Alumno reporta que sus datos son incorrectos
     * Crea notificación para todos los Jefa SE y Admin SE activos
     */
    case 'reportar_datos':
        try {
            $entrada     = json_decode(file_get_contents('php://input'), true) ?? [];
            $campos      = array_filter(array_map('trim', (array)($entrada['campos'] ?? [])));
            $descripcion = trim($entrada['descripcion'] ?? '');

            if (empty($descripcion)) {
                respuestaError('Debes describir el problema');
            }

            $usuario = obtenerUsuarioActual();
            $nombre  = $usuario['nombre_completo'] ?? 'Alumno';
            $nc      = $usuario['username'] ?? '';

            $titulo   = "Reporte de datos — {$nombre}";
            $campoTxt = !empty($campos)
                ? 'Campos incorrectos: ' . implode(', ', $campos) . '. '
                : '';
            $mensaje  = "El alumno {$nombre}" . ($nc ? " (No. Control: {$nc})" : '') .
                        " reporta datos incorrectos. {$campoTxt}Descripción: {$descripcion}";

            // Notificar a Jefa SE, Admin SE y roles personalizados con permisos equivalentes
            $destinatarios = $conexion->prepare("
                SELECT u.id_usuario
                FROM usuarios u
                LEFT JOIN roles r ON r.id_rol = u.id_rol
                WHERE u.activo = 1
                  AND u.id_usuario != ?
                  AND (
                      u.id_rol IN (?, ?)
                      OR (
                          u.id_rol > 5
                          AND (
                              r.permisos LIKE '%atender_incidencias%'
                              OR r.permisos LIKE '%validar_movimientos%'
                              OR r.permisos LIKE '%ver_alumnos%'
                          )
                      )
                  )
            ");
            $destinatarios->execute([$idUsuario, ROL_JEFA_SERVICIOS, ROL_ADMIN_SERVICIOS]);
            $users = $destinatarios->fetchAll();

            if (empty($users)) {
                respuestaError('No hay destinatarios disponibles para el reporte');
            }

            $enviadas = 0;
            foreach ($users as $dest) {
                $resultado = crearNotificacion(
                    $conexion,
                    $dest['id_usuario'],
                    $idUsuario,
                    'alerta_problema',
                    $titulo,
                    $mensaje,
                    'reporte_alumno',
                    null,
                    ['campos' => array_values($campos), 'nc' => $nc]
                );
                if ($resultado) $enviadas++;
            }

            $camposLog = !empty($campos) ? implode(', ', $campos) : 'no especificados';
            registrarEnBitacora('REPORTE_DATOS', "Alumno reportó datos incorrectos. Campos: {$camposLog}");
            respuestaExitosa(['enviadas' => $enviadas], 'Reporte enviado correctamente');

        } catch (Exception $e) {
            respuestaError('Error al enviar el reporte: ' . $e->getMessage());
        }
        break;

    default:
        respuestaError('Acción no válida', 400);
}

/**
 * Notifica al usuario origen que la Jefa marcó un problema
 */
function notificarProblema(\PDO $conexion, int $idNotificacionOriginal, int $idUsuarioJefa): void {
    try {
        // Obtener datos de la notificación original
        $consulta = $conexion->prepare("SELECT * FROM notificaciones WHERE id_notificacion = ?");
        $consulta->execute([$idNotificacionOriginal]);
        $notifOriginal = $consulta->fetch();
        
        if (!$notifOriginal || !$notifOriginal['id_usuario_origen']) {
            return;
        }
        
        // Obtener nombre de la Jefa
        $consulta = $conexion->prepare("SELECT nombre_completo FROM usuarios WHERE id_usuario = ?");
        $consulta->execute([$idUsuarioJefa]);
        $jefa = $consulta->fetch();
        
        // Crear notificación para el usuario origen
        $insert = $conexion->prepare("
            INSERT INTO notificaciones 
            (id_usuario_destino, id_usuario_origen, tipo, titulo, mensaje, referencia_tipo, referencia_id)
            VALUES (?, ?, 'alerta_problema', ?, ?, ?, ?)
        ");
        
        $titulo = '⚠️ Revisión requerida: ' . $notifOriginal['titulo'];
        $mensaje = ($jefa['nombre_completo'] ?? 'La Jefa de Servicios') . 
                   ' ha marcado esta exportación para revisión. Por favor verifica los datos.';
        
        $insert->execute([
            $notifOriginal['id_usuario_origen'],
            $idUsuarioJefa,
            $titulo,
            $mensaje,
            $notifOriginal['referencia_tipo'],
            $notifOriginal['referencia_id']
        ]);
        
    } catch (Exception $e) {
        // Silenciar error, es una notificación secundaria
        error_log('Error al notificar problema: ' . $e->getMessage());
    }
}

/**
 * Función helper para crear notificaciones (usada por otros módulos)
 */
function crearNotificacion(\PDO $conexion, int $idDestino, ?int $idOrigen, string $tipo, string $titulo, string $mensaje, ?string $refTipo = null, ?int $refId = null, ?array $datosExtra = null): int|false {
    try {
        $sql = "
            INSERT INTO notificaciones 
            (id_usuario_destino, id_usuario_origen, tipo, titulo, mensaje, referencia_tipo, referencia_id, datos_extra)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $consulta = $conexion->prepare($sql);
        $consulta->execute([
            $idDestino,
            $idOrigen,
            $tipo,
            $titulo,
            $mensaje,
            $refTipo,
            $refId,
            $datosExtra ? json_encode($datosExtra) : null
        ]);
        
        return $conexion->lastInsertId();
        
    } catch (Exception $e) {
        error_log('Error al crear notificación: ' . $e->getMessage());
        return false;
    }
}
