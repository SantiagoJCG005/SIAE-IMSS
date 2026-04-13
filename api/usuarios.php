<?php
/**
 * SIAE-IMSS - API de Usuarios
 */

// Indica que la respuesta sera en formato JSON
header('Content-Type: application/json');

// Incluye archivo de autenticacion (login, roles, etc)
require_once __DIR__ . '/../includes/auth.php';

// Incluye funciones generales (conexion, utilidades, etc)
require_once __DIR__ . '/../includes/functions.php';

// Verifica si el usuario ha iniciado sesion
if (!isLoggedIn()) {
    // Si no ha iniciado sesion, envia error
    jsonError('No autorizado', 401);
}

// Obtiene conexion a la base de datos
$pdo = getConnection();

// Obtiene la accion desde GET o POST
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Lee datos enviados en formato JSON
$input = json_decode(file_get_contents('php://input'), true);

// Si hay datos JSON, toma la accion desde ahi
if ($input) {
    $action = $input['action'] ?? $action;
}

// Revisa que accion se va a ejecutar
switch ($action) {

    // Obtener datos de un usuario por ID
    case 'get':

        // Solo superadmin puede hacer esto
        requireRole([ROL_SUPERADMIN]);
        
        // Obtiene el id del usuario
        $id = intval($_GET['id'] ?? 0);

        // Consulta a la base de datos
        $stmt = $pdo->prepare("SELECT id_usuario, username, email, nombre_completo, id_rol, activo FROM usuarios WHERE id_usuario = ?");

        // Ejecuta consulta
        $stmt->execute([$id]);

        // Obtiene resultado
        $user = $stmt->fetch();
        
        // Si existe el usuario
        if ($user) {
            jsonSuccess($user); // Respuesta correcta
        } else {
            jsonError('Usuario no encontrado', 404); // Error si no existe
        }
        break;
        
    // Crear un nuevo usuario
    case 'create':

        // Solo superadmin
        requireRole([ROL_SUPERADMIN]);
        
        // Obtiene y limpia datos
        $username = trim($input['username'] ?? '');
        $email = trim($input['email'] ?? '');
        $nombre = trim($input['nombre_completo'] ?? '');
        $password = $input['password'] ?? '';
        $rol = intval($input['id_rol'] ?? 0);
        $activo = intval($input['activo'] ?? 1);
        
        // Valida que no esten vacios
        if (empty($username) || empty($email) || empty($nombre) || empty($password) || !$rol) {
            jsonError('Todos los campos son obligatorios');
        }
        
        // Valida longitud de contraseña
        if (strlen($password) < 8) {
            jsonError('La contraseña debe tener al menos 8 caracteres');
        }
        
        // Verifica que el username no exista
        $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            jsonError('El nombre de usuario ya existe');
        }
        
        // Verifica que el email no exista
        $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            jsonError('El email ya esta registrado');
        }
        
        // Inserta nuevo usuario
        $stmt = $pdo->prepare("
            INSERT INTO usuarios (username, email, nombre_completo, password_hash, id_rol, activo, fecha_creacion)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        // Ejecuta con datos
        $result = $stmt->execute([
            $username,
            $email,
            $nombre,
            password_hash($password, PASSWORD_DEFAULT), // Encripta contraseña
            $rol,
            $activo
        ]);
        
        // Si se creo correctamente
        if ($result) {
            registrarBitacora('CREAR_USUARIO', "Usuario creado: $username");
            jsonSuccess(['id' => $pdo->lastInsertId()], 'Usuario creado correctamente');
        } else {
            jsonError('Error al crear usuario');
        }
        break;
        
    // Actualizar usuario existente
    case 'update':

        // Solo superadmin
        requireRole([ROL_SUPERADMIN]);
        
        // Obtiene datos
        $id = intval($input['id_usuario'] ?? 0);
        $username = trim($input['username'] ?? '');
        $email = trim($input['email'] ?? '');
        $nombre = trim($input['nombre_completo'] ?? '');
        $password = $input['password'] ?? '';
        $rol = intval($input['id_rol'] ?? 0);
        $activo = intval($input['activo'] ?? 1);
        
        // Verifica id valido
        if (!$id) {
            jsonError('ID de usuario requerido');
        }
        
        // Verifica username unico
        $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE username = ? AND id_usuario != ?");
        $stmt->execute([$username, $id]);
        if ($stmt->fetch()) {
            jsonError('El nombre de usuario ya existe');
        }
        
        // Verifica email unico
        $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE email = ? AND id_usuario != ?");
        $stmt->execute([$email, $id]);
        if ($stmt->fetch()) {
            jsonError('El email ya esta registrado');
        }
        
        // Si se quiere cambiar contraseña
        if (!empty($password)) {

            // Valida longitud
            if (strlen($password) < 8) {
                jsonError('La contraseña debe tener al menos 8 caracteres');
            }

            // Actualiza con contraseña
            $stmt = $pdo->prepare("
                UPDATE usuarios SET username = ?, email = ?, nombre_completo = ?, password_hash = ?, id_rol = ?, activo = ?
                WHERE id_usuario = ?
            ");

            $result = $stmt->execute([
                $username,
                $email,
                $nombre,
                password_hash($password, PASSWORD_DEFAULT),
                $rol,
                $activo,
                $id
            ]);

        } else {
            // Actualiza sin cambiar contraseña
            $stmt = $pdo->prepare("
                UPDATE usuarios SET username = ?, email = ?, nombre_completo = ?, id_rol = ?, activo = ?
                WHERE id_usuario = ?
            ");

            $result = $stmt->execute([
                $username,
                $email,
                $nombre,
                $rol,
                $activo,
                $id
            ]);
        }
        
        // Resultado
        if ($result) {
            registrarBitacora('EDITAR_USUARIO', "Usuario editado: $username");
            jsonSuccess(null, 'Usuario actualizado correctamente');
        } else {
            jsonError('Error al actualizar usuario');
        }
        break;
        
    // Activar o desactivar usuario
    case 'toggle':

        // Solo superadmin
        requireRole([ROL_SUPERADMIN]);
        
        // Datos
        $id = intval($input['id_usuario'] ?? 0);
        $activo = intval($input['activo'] ?? 0);
        
        // Actualiza estado
        $stmt = $pdo->prepare("UPDATE usuarios SET activo = ? WHERE id_usuario = ?");
        $result = $stmt->execute([$activo, $id]);
        
        // Resultado
        if ($result) {
            $accion = $activo ? 'ACTIVAR' : 'DESACTIVAR';
            registrarBitacora($accion . '_USUARIO', "Usuario ID: $id");
            jsonSuccess(null, 'Estado actualizado correctamente');
        } else {
            jsonError('Error al actualizar estado');
        }
        break;
        
    // Exportar usuarios a CSV
    case 'export':

        // Solo superadmin
        requireRole([ROL_SUPERADMIN]);
        
        // Consulta usuarios con rol
        $stmt = $pdo->query("
            SELECT u.username, u.nombre_completo, u.email, r.nombre as rol, 
                   IF(u.activo, 'Activo', 'Inactivo') as estado,
                   u.ultimo_login, u.fecha_creacion
            FROM usuarios u
            LEFT JOIN roles r ON u.id_rol = r.id_rol
            ORDER BY u.id_usuario
        ");

        // Obtiene resultados
        $usuarios = $stmt->fetchAll();
        
        // Configura descarga CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="usuarios_' . date('Y-m-d') . '.csv"');
        
        // Abre salida
        $output = fopen('php://output', 'w');

        // BOM para excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Encabezados
        fputcsv($output, ['Usuario', 'Nombre Completo', 'Email', 'Rol', 'Estado', 'Ultimo Login', 'Fecha Creacion']);
        
        // Escribe filas
        foreach ($usuarios as $user) {
            fputcsv($output, $user);
        }
        
        // Cierra archivo
        fclose($output);
        
        // Guarda en bitacora
        registrarBitacora('EXPORTAR_USUARIOS', 'Exportacion CSV de usuarios');

        exit;
        break;
        
    // Accion no valida
    default:
        jsonError('Accion no valida', 400);
}