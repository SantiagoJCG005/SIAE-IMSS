<?php
/** * SI FUNCIONA NO LE MUEVAS!!!!!
 * SIAE-IMSS - API de Usuarios
 */

// Indica que la respuesta sera en formato JSON
header('Content-Type: application/json');

// Incluye archivo de autenticacion (login, roles, etc)
require_once __DIR__ . '/../includes/auth.php';

// Incluye funciones generales (conexion, utilidades, etc)
require_once __DIR__ . '/../includes/functions.php';

// Incluye funciones de correo
require_once __DIR__ . '/../includes/mail.php';

// Verifica si el usuario ha iniciado sesion
if (!estaLogueado()) {
    // Si no ha iniciado sesion, envia error
    respuestaError('No autorizado', 401);
}

// Obtiene conexion a la base de datos
$conexion = obtenerConexion();

// Obtiene la accion desde GET o POST
$accion = $_GET['action'] ?? $_POST['action'] ?? '';

// Lee datos enviados en formato JSON
$entrada = json_decode(file_get_contents('php://input'), true);

// Si hay datos JSON, toma la accion desde ahi
if ($entrada) {
    $accion = $entrada['action'] ?? $accion;
}

// Genera una contrasena temporal segura de 10 caracteres
function generarPasswordTemporal() {
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#$';
    $password = '';
    for ($i = 0; $i < 10; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

// Revisa que accion se va a ejecutar
switch ($accion) {

    // Obtener datos de un usuario por ID
    case 'get':

        // Solo superadmin puede hacer esto
        requerirRol([ROL_SUPERADMIN]);
        
        // Obtiene el id del usuario
        $id = intval($_GET['id'] ?? 0);

        // Consulta a la base de datos
        $consulta = $conexion->prepare("SELECT id_usuario, username, email, nombre_completo, id_rol, activo FROM usuarios WHERE id_usuario = ?");

        // Ejecuta consulta
        $consulta->execute([$id]);

        // Obtiene resultado
        $usuario = $consulta->fetch();
        
        // Si existe el usuario
        if ($usuario) {
            respuestaExitosa($usuario); // Respuesta correcta
        } else {
            respuestaError('Usuario no encontrado', 404); // Error si no existe
        }
        break;
        
    // Crear un nuevo usuario
    case 'create':

        // Solo superadmin
        requerirRol([ROL_SUPERADMIN]);
        
        // Obtiene y limpia datos
        $username = trim($entrada['username'] ?? '');
        $email = trim($entrada['email'] ?? '');
        $nombre = trim($entrada['nombre_completo'] ?? '');
        $password = $entrada['password'] ?? '';
        $rol = intval($entrada['id_rol'] ?? 0);
        $activo = intval($entrada['activo'] ?? 1);
        
        // Valida que no esten vacios
        if (empty($username) || empty($email) || empty($nombre) || empty($password) || !$rol) {
            respuestaError('Todos los campos son obligatorios');
        }
        
        // Valida longitud de contraseña
        if (strlen($password) < 8) {
            respuestaError('La contraseña debe tener al menos 8 caracteres');
        }
        
        // Verifica que el username no exista
        $consulta = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE username = ?");
        $consulta->execute([$username]);
        if ($consulta->fetch()) {
            respuestaError('El nombre de usuario ya existe');
        }
        
        // Verifica que el email no exista
        $consulta = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
        $consulta->execute([$email]);
        if ($consulta->fetch()) {
            respuestaError('El email ya esta registrado');
        }
        
        // Genera contrasena temporal — la real nunca se envia por correo
        $passwordTemporal = generarPasswordTemporal();

        // Inserta nuevo usuario con flag de cambio obligatorio
        $consulta = $conexion->prepare("
            INSERT INTO usuarios (username, email, nombre_completo, password_hash, id_rol, activo, debe_cambiar_password, fecha_creacion)
            VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
        ");

        $resultado = $consulta->execute([
            $username,
            $email,
            $nombre,
            password_hash($passwordTemporal, PASSWORD_DEFAULT),
            $rol,
            $activo
        ]);

        if ($resultado) {
            registrarEnBitacora('CREAR_USUARIO', "Usuario creado: $username");

            $protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $dominio = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $urlLogin = $protocolo . $dominio . URL_BASE . "views/auth/login.php";

            // Solo se envia la contraseña temporal — el usuario debe cambiarla al primer login
            $cuerpo = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 8px;'>
                    <h2 style='color: #2563EB;'>Bienvenido a SIAE-IMSS</h2>
                    <p>Hola <strong>{$nombre}</strong>,</p>
                    <p>Tu cuenta ha sido creada. Usa esta contrasena temporal para ingresar por primera vez:</p>
                    <div style='background-color: #FEF3C7; padding: 15px; border-radius: 6px; margin: 20px 0; border-left: 4px solid #F59E0B;'>
                        <p style='margin: 5px 0;'><strong>Usuario:</strong> {$username}</p>
                        <p style='margin: 5px 0;'><strong>Contrasena temporal:</strong> {$passwordTemporal}</p>
                    </div>
                    <p style='color: #DC2626;'><strong>Al ingresar, el sistema te pedira crear una nueva contraseña.</strong> Esta contraseña temporal expira despues del primer uso.</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$urlLogin}' style='background-color: #2563EB; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;'>Ingresar al Sistema</a>
                    </div>
                    <hr style='border: none; border-top: 1px solid #e0e0e0; margin: 20px 0;'>
                    <p style='font-size: 12px; color: #9CA3AF; text-align: center;'>Sistema SIAE-IMSS. Este es un correo automatico, por favor no respondas.</p>
                </div>
            ";

            enviarCorreo($email, "Acceso a SIAE-IMSS - Contraseña temporal", $cuerpo);

            respuestaExitosa(['id' => $conexion->lastInsertId()], 'Usuario creado correctamente');
        } else {
            respuestaError('Error al crear usuario');
        }
        break;
        
    // Actualizar usuario existente
    case 'update':

        // Solo superadmin
        requerirRol([ROL_SUPERADMIN]);
        
        // Obtiene datos
        $id = intval($entrada['id_usuario'] ?? 0);
        $username = trim($entrada['username'] ?? '');
        $email = trim($entrada['email'] ?? '');
        $nombre = trim($entrada['nombre_completo'] ?? '');
        $password = $entrada['password'] ?? '';
        $rol = intval($entrada['id_rol'] ?? 0);
        $activo = intval($entrada['activo'] ?? 1);
        
        // Verifica id valido
        if (!$id) {
            respuestaError('ID de usuario requerido');
        }
        
        // Verifica username unico
        $consulta = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE username = ? AND id_usuario != ?");
        $consulta->execute([$username, $id]);
        if ($consulta->fetch()) {
            respuestaError('El nombre de usuario ya existe');
        }
        
        // Verifica email unico
        $consulta = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE email = ? AND id_usuario != ?");
        $consulta->execute([$email, $id]);
        if ($consulta->fetch()) {
            respuestaError('El email ya esta registrado');
        }
        
        // Si se quiere cambiar contraseña
        if (!empty($password)) {

            // Valida longitud
            if (strlen($password) < 8) {
                respuestaError('La contraseña debe tener al menos 8 caracteres');
            }

            // Actualiza con contraseña
            $consulta = $conexion->prepare("
                UPDATE usuarios SET username = ?, email = ?, nombre_completo = ?, password_hash = ?, id_rol = ?, activo = ?
                WHERE id_usuario = ?
            ");

            $resultado = $consulta->execute([
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
            $consulta = $conexion->prepare("
                UPDATE usuarios SET username = ?, email = ?, nombre_completo = ?, id_rol = ?, activo = ?
                WHERE id_usuario = ?
            ");

            $resultado = $consulta->execute([
                $username,
                $email,
                $nombre,
                $rol,
                $activo,
                $id
            ]);
        }
        
        // Resultado
        if ($resultado) {
            registrarEnBitacora('EDITAR_USUARIO', "Usuario editado: $username");
            respuestaExitosa(null, 'Usuario actualizado correctamente');
        } else {
            respuestaError('Error al actualizar usuario');
        }
        break;
        
    // Activar o desactivar usuario
    case 'toggle':

        // Solo superadmin
        requerirRol([ROL_SUPERADMIN]);
        
        // Datos
        $id = intval($entrada['id_usuario'] ?? 0);
        $activo = intval($entrada['activo'] ?? 0);
        
        // Actualiza estado
        $consulta = $conexion->prepare("UPDATE usuarios SET activo = ? WHERE id_usuario = ?");
        $resultado = $consulta->execute([$activo, $id]);
        
        // Resultado
        if ($resultado) {
            $accion = $activo ? 'ACTIVAR' : 'DESACTIVAR';
            registrarEnBitacora($accion . '_USUARIO', "Usuario ID: $id");
            respuestaExitosa(null, 'Estado actualizado correctamente');
        } else {
            respuestaError('Error al actualizar estado');
        }
        break;
        
    // Exportar usuarios a CSV
    case 'export':

        // Solo superadmin
        requerirRol([ROL_SUPERADMIN]);
        
        // Consulta usuarios con rol
        $consulta = $conexion->query("
            SELECT u.username, u.nombre_completo, u.email, r.nombre as rol, 
                   IF(u.activo, 'Activo', 'Inactivo') as estado,
                   u.ultimo_login, u.fecha_creacion
            FROM usuarios u
            LEFT JOIN roles r ON u.id_rol = r.id_rol
            ORDER BY u.id_usuario
        ");

        // Obtiene resultados
        $listaUsuarios = $consulta->fetchAll();
        
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
        foreach ($listaUsuarios as $usuario) {
            fputcsv($output, $usuario);
        }
        
        // Cierra archivo
        fclose($output);
        
        // Guarda en bitacora
        registrarEnBitacora('EXPORTAR_USUARIOS', 'Exportacion CSV de usuarios');

        exit;
        break;
        
    // Cambiar contrasena del usuario actual
    case 'cambiar_password':

        $idUsuario = obtenerIdUsuarioActual();
        $nuevaPassword = $entrada['nueva_password'] ?? '';
        $confirmar = $entrada['confirmar_password'] ?? '';
        $passwordActual = $entrada['password_actual'] ?? '';
        $esForzado = isset($entrada['forzado']) && $entrada['forzado'] === true;

        if (empty($nuevaPassword) || empty($confirmar)) {
            respuestaError('Completa todos los campos');
        }

        if (strlen($nuevaPassword) < 8) {
            respuestaError('La contrasena debe tener al menos 8 caracteres');
        }

        if ($nuevaPassword !== $confirmar) {
            respuestaError('Las contrasenas no coinciden');
        }

        // Si no es cambio forzado, valida la contrasena actual
        if (!$esForzado) {
            if (empty($passwordActual)) {
                respuestaError('Ingresa tu contrasena actual');
            }
            $consulta = $conexion->prepare("SELECT password_hash FROM usuarios WHERE id_usuario = ?");
            $consulta->execute([$idUsuario]);
            $usuario = $consulta->fetch();
            if (!$usuario || !password_verify($passwordActual, $usuario['password_hash'])) {
                respuestaError('La contrasena actual es incorrecta');
            }
        }

        try {
            // Actualiza contrasena y limpia el flag de cambio obligatorio
            $consulta = $conexion->prepare("
                UPDATE usuarios SET password_hash = ?, debe_cambiar_password = 0 WHERE id_usuario = ?
            ");
            $consulta->execute([password_hash($nuevaPassword, PASSWORD_DEFAULT), $idUsuario]);

            // Actualiza el flag en la sesion activa
            $_SESSION['user']['debe_cambiar_password'] = 0;

            registrarEnBitacora('CAMBIAR_PASSWORD', 'Contrasena actualizada');
            respuestaExitosa(null, 'Contrasena actualizada correctamente');

        } catch (Exception $e) {
            respuestaError('Error al actualizar contrasena');
        }
        break;

    // Accion no valida
    default:
        respuestaError('Accion no valida', 400);
}