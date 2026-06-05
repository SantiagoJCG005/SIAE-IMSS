<?php
/**
 * SIAE-IMSS - API de Alumnos
 * Permite a Jefa SE y Admin SE consultar y corregir datos de alumnos por número de control
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!estaLogueado()) {
    respuestaError('No autorizado', 401);
}

if (!tieneAlgunRol([ROL_JEFA_SERVICIOS, ROL_ADMIN_SERVICIOS, ROL_SUPERADMIN, ROL_ADMIN_IMSS])) {
    respuestaError('Sin permisos', 403);
}

$conexion = obtenerConexion();
$entrada  = json_decode(file_get_contents('php://input'), true) ?? [];
$accion   = $_GET['action'] ?? ($entrada['action'] ?? '');

switch ($accion) {

    // Buscar alumno por número de control
    case 'buscar':
        $nc = trim($_GET['nc'] ?? '');
        if (!$nc) respuestaError('Número de control requerido');

        try {
            $stmt = $conexion->prepare("
                SELECT a.*,
                       c.nombre  AS nombre_carrera,
                       di.nss    AS nss
                FROM alumnos a
                LEFT JOIN carreras  c  ON c.id_carrera = a.id_carrera
                LEFT JOIN datos_imss di ON di.id_alumno = a.id_alumno
                WHERE a.numero_control = ?
                LIMIT 1
            ");
            $stmt->execute([$nc]);
            $alumno = $stmt->fetch();

            if (!$alumno) {
                respuestaError('No se encontró un alumno con ese número de control');
            }

            // Lista de carreras para el dropdown
            $carreras = $conexion->query(
                "SELECT id_carrera, nombre FROM carreras WHERE activo = 1 ORDER BY nombre"
            )->fetchAll();

            respuestaExitosa(['alumno' => $alumno, 'carreras' => $carreras]);

        } catch (Exception $e) {
            respuestaError('Error al buscar alumno: ' . $e->getMessage());
        }
        break;

    // Actualizar datos del alumno
    case 'actualizar':
        $nc = trim($entrada['numero_control'] ?? '');
        if (!$nc) respuestaError('Número de control requerido');

        try {
            // Obtener ID del alumno
            $stmt = $conexion->prepare(
                "SELECT id_alumno FROM alumnos WHERE numero_control = ? LIMIT 1"
            );
            $stmt->execute([$nc]);
            $row = $stmt->fetch();
            if (!$row) respuestaError('Alumno no encontrado');

            $idAlumno = (int) $row['id_alumno'];

            // Actualizar tabla alumnos
            $stmt = $conexion->prepare("
                UPDATE alumnos SET
                    nombre           = ?,
                    apellido_paterno = ?,
                    apellido_materno = ?,
                    curp             = ?,
                    id_carrera       = ?,
                    email            = ?,
                    telefono         = ?
                WHERE id_alumno = ?
            ");
            $stmt->execute([
                trim($entrada['nombre']           ?? ''),
                trim($entrada['apellido_paterno'] ?? ''),
                trim($entrada['apellido_materno'] ?? ''),
                strtoupper(trim($entrada['curp']  ?? '')),
                ($entrada['id_carrera'] ? (int) $entrada['id_carrera'] : null),
                trim($entrada['email']            ?? ''),
                trim($entrada['telefono']         ?? ''),
                $idAlumno,
            ]);

            // Actualizar NSS en datos_imss si se envió
            $nss = trim($entrada['nss'] ?? '');
            if ($nss !== '') {
                $existe = $conexion->prepare(
                    "SELECT id_alumno FROM datos_imss WHERE id_alumno = ? LIMIT 1"
                );
                $existe->execute([$idAlumno]);

                if ($existe->fetch()) {
                    $conexion->prepare(
                        "UPDATE datos_imss SET nss = ? WHERE id_alumno = ?"
                    )->execute([$nss, $idAlumno]);
                } else {
                    $conexion->prepare(
                        "INSERT INTO datos_imss (id_alumno, nss) VALUES (?, ?)"
                    )->execute([$idAlumno, $nss]);
                }
            }

            registrarEnBitacora('EDITAR_ALUMNO', "Datos corregidos del alumno $nc (reporte de datos)");
            respuestaExitosa(null, 'Datos del alumno actualizados correctamente');

        } catch (Exception $e) {
            respuestaError('Error al actualizar: ' . $e->getMessage());
        }
        break;

    default:
        respuestaError('Acción no válida', 400);
}
