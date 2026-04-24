<?php
/**
 * SIAE-IMSS - API para Recuperar Contrasena
 * Maneja la generacion de tokens y actualizacion de contrasenas
 * SIN ACENTOS EN LOS COMENTARIOS COMO SE SOLICITO
 */

header('Content-Type: application/json; charset=utf-8');

// Carga de archivos base
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mail.php';

$conexion = obtenerConexion();

// Leemos json si viene de fetch, o arreglos $_POST
$datosEntrada = json_decode(file_get_contents('php://input'), true);
$accion = $datosEntrada['action'] ?? $_POST['action'] ?? '';

switch ($accion) {
    case 'solicitar_token':
        $email = trim($datosEntrada['email'] ?? $_POST['email'] ?? '');
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            respuestaError('Ingresa un correo electronico valido');
        }
        
        try {
            // Verificamos si el correo existe en el sistema
            $consulta = $conexion->prepare("SELECT id_usuario, nombre_completo, activo FROM usuarios WHERE email = ?");
            $consulta->execute([$email]);
            $usuario = $consulta->fetch();
            
            // Siempre enviamos respuesta exitosa para evitar que ataquen adivinando correos (seguridad)
            if (!$usuario || $usuario['activo'] == 0) {
                respuestaExitosa(null, 'Si el correo esta registrado, recibiras un enlace pronto.');
                break;
            }
            
            // Generar token seguro de 64 caracteres
            $token = bin2hex(random_bytes(32));
            
            // Fecha de expiracion: 2 horas desde ahora
            $fechaExpiracion = date('Y-m-d H:i:s', strtotime('+2 hours'));
            
            // Guardar el token en la base de datos (tabla password_resets)
            $insert = $conexion->prepare("
                INSERT INTO password_resets (email, token, fecha_expiracion, usado) 
                VALUES (?, ?, ?, 0)
            ");
            $insert->execute([$email, $token, $fechaExpiracion]);
            
            // Construir URL absoluta para el correo electronico
            $protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $dominio = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $urlRestablecer = $protocolo . $dominio . URL_BASE . "views/auth/reset_password.php?token=" . $token;
            
            // Preparar el cuerpo del correo en HTML
            $cuerpo = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 8px;'>
                    <h2 style='color: #2563EB;'>Recuperacion de Contrasena</h2>
                    <p>Hola <strong>{$usuario['nombre_completo']}</strong>,</p>
                    <p>Hemos recibido una solicitud para restablecer la contrasena de tu cuenta en SIAE-IMSS.</p>
                    <p>Para crear una nueva contrasena, haz clic en el siguiente boton. Este enlace sera valido por 2 horas:</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$urlRestablecer}' style='background-color: #2563EB; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;'>Restablecer mi contrasena</a>
                    </div>
                    <p>Si no puedes hacer clic en el boton, copia y pega este enlace en tu navegador:</p>
                    <p style='color: #6B7280; font-size: 13px; word-break: break-all;'>{$urlRestablecer}</p>
                    <p>Si tu no realizaste esta solicitud, puedes ignorar este correo y tu cuenta seguira segura.</p>
                    <hr style='border: none; border-top: 1px solid #e0e0e0; margin: 20px 0;'>
                    <p style='font-size: 12px; color: #9CA3AF; text-align: center;'>Sistema SIAE-IMSS. Este es un correo automatico, por favor no respondas.</p>
                </div>
            ";
            
            // Usamos la funcion enviarCorreo que ya existe en includes/mail.php
            $resultadoMail = enviarCorreo($email, "SIAE-IMSS: Restablece tu contrasena", $cuerpo);
            
            if ($resultadoMail['success']) {
                respuestaExitosa(null, 'Si el correo esta registrado, recibiras un enlace pronto.');
            } else {
                // En caso de que falle SMTP
                respuestaError('Hubo un problema al enviar el correo. Por favor contacta al administrador.');
            }
            
        } catch (Exception $e) {
            respuestaError('Error de servidor al procesar la solicitud.');
        }
        break;

    case 'actualizar_password':
        $token = $datosEntrada['token'] ?? $_POST['token'] ?? '';
        $nuevaPassword = $datosEntrada['password'] ?? $_POST['password'] ?? '';
        $confirmarPassword = $datosEntrada['confirmar_password'] ?? $_POST['confirmar_password'] ?? '';
        
        if (empty($token)) {
            respuestaError('El enlace no es valido o esta incompleto.');
        }
        
        if (strlen($nuevaPassword) < 8) {
            respuestaError('La contrasena debe tener al menos 8 caracteres.');
        }
        
        if ($nuevaPassword !== $confirmarPassword) {
            respuestaError('Las contrasenas no coinciden.');
        }
        
        try {
            // Verificar que el token exista, no haya sido usado y no este expirado
            $consulta = $conexion->prepare("
                SELECT email FROM password_resets 
                WHERE token = ? AND usado = 0 AND fecha_expiracion > NOW()
                ORDER BY fecha_creacion DESC LIMIT 1
            ");
            $consulta->execute([$token]);
            $resetData = $consulta->fetch();
            
            if (!$resetData) {
                respuestaError('El enlace ha expirado o ya fue utilizado. Por favor solicita uno nuevo.');
            }
            
            $email = $resetData['email'];
            
            // Iniciar transaccion para actualizar ambos registros al mismo tiempo sin fallos a medias
            $conexion->beginTransaction();
            
            // 1. Actualizar la contrasena del usuario
            $passwordHash = password_hash($nuevaPassword, PASSWORD_DEFAULT);
            $updateUser = $conexion->prepare("UPDATE usuarios SET password_hash = ? WHERE email = ?");
            $updateUser->execute([$passwordHash, $email]);
            
            // 2. Marcar el token como usado
            $updateToken = $conexion->prepare("UPDATE password_resets SET usado = 1 WHERE token = ?");
            $updateToken->execute([$token]);
            
            $conexion->commit();
            
            respuestaExitosa(null, 'Tu contrasena ha sido actualizada correctamente. Ya puedes iniciar sesion.');
            
        } catch (Exception $e) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }
            respuestaError('Error al actualizar la contrasena.');
        }
        break;
        
    default:
        respuestaError('Accion no valida');
        break;
}
