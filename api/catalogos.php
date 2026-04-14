<?php
/** * SI FUNCIONA NO LE MUEVAS!!!!!
 * SIAE-IMSS - API de Catalogos
 * Este archivo maneja las operaciones para los catalogos del sistema.
 */

// Indica que la respuesta sera en formato JSON (para que el sistema lo entienda)
header('Content-Type: application/json');

// Incluye archivo que controla login y permisos de usuario
require_once __DIR__ . '/../includes/auth.php';

// Incluye funciones generales del sistema (conexion, utilidades, etc)
require_once __DIR__ . '/../includes/functions.php';

// Verifica si el usuario ha iniciado sesion
if (!estaLogueado()) {
    // Si no ha iniciado sesion, envia error
    respuestaError('No autorizado', 401);
}

// Verifica que el usuario tenga rol de superadmin
requerirRol([ROL_SUPERADMIN]);

// Obtiene conexion a la base de datos
$conexion = obtenerConexion();

// Lee los datos que vienen en formato JSON desde el cliente
$entrada = json_decode(file_get_contents('php://input'), true);

// Obtiene la accion que se quiere hacer (crear, editar, eliminar)
$accion = $entrada['action'] ?? '';

// Obtiene el nombre del catalogo que se va a usar
$pestana = $entrada['tab'] ?? '';

// Relaciona cada catalogo con su tabla y campos en la base de datos
$tables = [
    'carreras' => ['table' => 'carreras', 'id' => 'id_carrera', 'fields' => ['clave', 'nombre', 'id_nivel', 'activo']],
    'niveles' => ['table' => 'nivel', 'id' => 'id_nivel', 'fields' => ['nombre', 'descripcion', 'activo']],
    'modalidades' => ['table' => 'modalidad', 'id' => 'id_modalidad', 'fields' => ['nombre', 'descripcion', 'activo']],
    'periodos' => ['table' => 'periodo_escolar', 'id' => 'id_periodo', 'fields' => ['nombre', 'fecha_inicio', 'fecha_fin', 'activo']],
    'semestres' => ['table' => 'semestre', 'id' => 'id_semestre', 'fields' => ['numero', 'nombre', 'activo']],
    'motivos' => ['table' => 'motivos_bajas', 'id' => 'id_motivo', 'fields' => ['clave', 'descripcion', 'activo']]
];

// Verifica si el catalogo existe
if (!isset($tables[$pestana])) {
    // Si no existe, envia error
    respuestaError('Catalogo no valido');
}

// Guarda la configuracion del catalogo seleccionado
$config = $tables[$pestana];

// Revisa que accion se quiere ejecutar
switch ($accion) {

    // Crear un nuevo registro
    case 'create':

        // Listas vacias para armar la consulta
        $fields = [];
        $placeholders = [];
        $values = [];
        
        // Recorre los campos permitidos
        foreach ($config['fields'] as $field) {

            // Si ese campo viene en los datos enviados
            if (isset($entrada[$field])) {

                // Guarda nombre del campo
                $fields[] = $field;

                // Agrega un signo ? para la consulta segura
                $placeholders[] = '?';

                // Guarda el valor del campo
                $values[] = $entrada[$field];
            }
        }
        
        // Si no hay datos para guardar
        if (empty($fields)) {
            respuestaError('No hay datos para insertar');
        }
        
        // Arma la consulta INSERT
        $sql = "INSERT INTO {$config['table']} (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";

        // Prepara la consulta
        $consulta = $conexion->prepare($sql);

        // Ejecuta la consulta con los valores
        $resultado = $consulta->execute($values);
        
        // Si se guardo correctamente
        if ($resultado) {

            // Guarda registro en bitacora
            registrarEnBitacora('CREAR_CATALOGO', "Nuevo registro en $pestana");

            // Respuesta exitosa con el id creado
            respuestaExitosa(['id' => $conexion->lastInsertId()], 'Registro creado correctamente');

        } else {
            // Error si falla
            respuestaError('Error al crear registro');
        }
        break;
        
    // Actualizar un registro existente
    case 'update':

        // Obtiene el id y lo convierte a numero
        $id = intval($entrada['id'] ?? 0);

        // Si no hay id valido
        if (!$id) {
            respuestaError('ID requerido');
        }
        
        // Listas para armar el UPDATE
        $sets = [];
        $values = [];
        
        // Recorre los campos
        foreach ($config['fields'] as $field) {

            // Si el campo viene en los datos
            if (isset($entrada[$field])) {

                // Agrega "campo = ?" a la consulta
                $sets[] = "$field = ?";

                // Guarda valor
                $values[] = $entrada[$field];
            }
        }
        
        // Si no hay datos para actualizar
        if (empty($sets)) {
            respuestaError('No hay datos para actualizar');
        }
        
        // Agrega el id al final
        $values[] = $id;

        // Arma consulta UPDATE
        $sql = "UPDATE {$config['table']} SET " . implode(', ', $sets) . " WHERE {$config['id']} = ?";

        // Prepara consulta
        $consulta = $conexion->prepare($sql);

        // Ejecuta
        $resultado = $consulta->execute($values);
        
        // Si se actualizo bien
        if ($resultado) {

            // Guarda en bitacora
            registrarEnBitacora('EDITAR_CATALOGO', "Registro editado en $pestana (ID: $id)");

            // Respuesta exitosa
            respuestaExitosa(null, 'Registro actualizado correctamente');

        } else {
            // Error
            respuestaError('Error al actualizar registro');
        }
        break;
        
    // Eliminar (realmente desactivar)
    case 'delete':

        // Obtiene el id
        $id = intval($entrada['id'] ?? 0);

        // Si no es valido
        if (!$id) {
            respuestaError('ID requerido');
        }
        
        // No borra el registro, solo lo marca como inactivo
        $sql = "UPDATE {$config['table']} SET activo = 0 WHERE {$config['id']} = ?";

        // Prepara consulta
        $consulta = $conexion->prepare($sql);

        // Ejecuta
        $resultado = $consulta->execute([$id]);
        
        // Si funciona
        if ($resultado) {

            // Guarda en bitacora
            registrarEnBitacora('ELIMINAR_CATALOGO', "Registro desactivado en $pestana (ID: $id)");

            // Respuesta correcta
            respuestaExitosa(null, 'Registro eliminado correctamente');

        } else {
            // Error
            respuestaError('Error al eliminar registro');
        }
        break;
        
    // Si la accion no existe
    default:
        respuestaError('Accion no valida');
}