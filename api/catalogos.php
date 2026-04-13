<?php
/**
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
if (!isLoggedIn()) {
    // Si no ha iniciado sesion, envia error
    jsonError('No autorizado', 401);
}

// Verifica que el usuario tenga rol de superadmin
requireRole([ROL_SUPERADMIN]);

// Obtiene conexion a la base de datos
$pdo = getConnection();

// Lee los datos que vienen en formato JSON desde el cliente
$input = json_decode(file_get_contents('php://input'), true);

// Obtiene la accion que se quiere hacer (crear, editar, eliminar)
$action = $input['action'] ?? '';

// Obtiene el nombre del catalogo que se va a usar
$tab = $input['tab'] ?? '';

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
if (!isset($tables[$tab])) {
    // Si no existe, envia error
    jsonError('Catalogo no valido');
}

// Guarda la configuracion del catalogo seleccionado
$config = $tables[$tab];

// Revisa que accion se quiere ejecutar
switch ($action) {

    // Crear un nuevo registro
    case 'create':

        // Listas vacias para armar la consulta
        $fields = [];
        $placeholders = [];
        $values = [];
        
        // Recorre los campos permitidos
        foreach ($config['fields'] as $field) {

            // Si ese campo viene en los datos enviados
            if (isset($input[$field])) {

                // Guarda nombre del campo
                $fields[] = $field;

                // Agrega un signo ? para la consulta segura
                $placeholders[] = '?';

                // Guarda el valor del campo
                $values[] = $input[$field];
            }
        }
        
        // Si no hay datos para guardar
        if (empty($fields)) {
            jsonError('No hay datos para insertar');
        }
        
        // Arma la consulta INSERT
        $sql = "INSERT INTO {$config['table']} (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";

        // Prepara la consulta
        $stmt = $pdo->prepare($sql);

        // Ejecuta la consulta con los valores
        $result = $stmt->execute($values);
        
        // Si se guardo correctamente
        if ($result) {

            // Guarda registro en bitacora
            registrarBitacora('CREAR_CATALOGO', "Nuevo registro en $tab");

            // Respuesta exitosa con el id creado
            jsonSuccess(['id' => $pdo->lastInsertId()], 'Registro creado correctamente');

        } else {
            // Error si falla
            jsonError('Error al crear registro');
        }
        break;
        
    // Actualizar un registro existente
    case 'update':

        // Obtiene el id y lo convierte a numero
        $id = intval($input['id'] ?? 0);

        // Si no hay id valido
        if (!$id) {
            jsonError('ID requerido');
        }
        
        // Listas para armar el UPDATE
        $sets = [];
        $values = [];
        
        // Recorre los campos
        foreach ($config['fields'] as $field) {

            // Si el campo viene en los datos
            if (isset($input[$field])) {

                // Agrega "campo = ?" a la consulta
                $sets[] = "$field = ?";

                // Guarda valor
                $values[] = $input[$field];
            }
        }
        
        // Si no hay datos para actualizar
        if (empty($sets)) {
            jsonError('No hay datos para actualizar');
        }
        
        // Agrega el id al final
        $values[] = $id;

        // Arma consulta UPDATE
        $sql = "UPDATE {$config['table']} SET " . implode(', ', $sets) . " WHERE {$config['id']} = ?";

        // Prepara consulta
        $stmt = $pdo->prepare($sql);

        // Ejecuta
        $result = $stmt->execute($values);
        
        // Si se actualizo bien
        if ($result) {

            // Guarda en bitacora
            registrarBitacora('EDITAR_CATALOGO', "Registro editado en $tab (ID: $id)");

            // Respuesta exitosa
            jsonSuccess(null, 'Registro actualizado correctamente');

        } else {
            // Error
            jsonError('Error al actualizar registro');
        }
        break;
        
    // Eliminar (realmente desactivar)
    case 'delete':

        // Obtiene el id
        $id = intval($input['id'] ?? 0);

        // Si no es valido
        if (!$id) {
            jsonError('ID requerido');
        }
        
        // No borra el registro, solo lo marca como inactivo
        $sql = "UPDATE {$config['table']} SET activo = 0 WHERE {$config['id']} = ?";

        // Prepara consulta
        $stmt = $pdo->prepare($sql);

        // Ejecuta
        $result = $stmt->execute([$id]);
        
        // Si funciona
        if ($result) {

            // Guarda en bitacora
            registrarBitacora('ELIMINAR_CATALOGO', "Registro desactivado en $tab (ID: $id)");

            // Respuesta correcta
            jsonSuccess(null, 'Registro eliminado correctamente');

        } else {
            // Error
            jsonError('Error al eliminar registro');
        }
        break;
        
    // Si la accion no existe
    default:
        jsonError('Accion no valida');
}