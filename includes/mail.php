<?php
/**
 * SIAE-IMSS - Funciones de Correo Electrónico
 * Envío de correos usando SMTP
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Obtiene la configuración SMTP de la base de datos
 */
function obtenerConfigSMTP() {
    $conexion = obtenerConexion();
    
    // Nombres de campos como están en la BD
    $campos = ['smtp_servidor', 'smtp_puerto', 'smtp_usuario', 'smtp_password', 'smtp_encriptacion', 'smtp_email_remitente', 'smtp_nombre_remitente'];
    $config = [];
    
    foreach ($campos as $campo) {
        $stmt = $conexion->prepare("SELECT valor FROM configuracion WHERE tipo = 'smtp' AND clave = ? LIMIT 1");
        $stmt->execute([$campo]);
        $resultado = $stmt->fetch();
        $config[$campo] = $resultado ? $resultado['valor'] : '';
    }
    
    return $config;
}

/**
 * Envía un correo electrónico usando SMTP
 * 
 * @param string $para Email destinatario
 * @param string $asunto Asunto del correo
 * @param string $cuerpoHtml Contenido HTML del correo
 * @return array ['success' => bool, 'message' => string]
 */
function enviarCorreo($para, $asunto, $cuerpoHtml) {
    $config = obtenerConfigSMTP();
    
    // Validar configuración
    if (empty($config['smtp_servidor']) || empty($config['smtp_usuario'])) {
        return ['success' => false, 'message' => 'Configuración SMTP incompleta. Revisa que hayas llenado servidor y usuario.'];
    }
    
    $host = $config['smtp_servidor'];
    $port = intval($config['smtp_puerto']) ?: 587;
    $user = $config['smtp_usuario'];
    $pass = $config['smtp_password'];
    $encryption = $config['smtp_encriptacion'] ?: 'tls';
    $fromEmail = $config['smtp_email_remitente'] ?: $user;
    $fromName = $config['smtp_nombre_remitente'] ?: 'SIAE-IMSS';
    
    try {
        // Conexión SMTP
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);
        
        $prefix = ($encryption === 'ssl') ? 'ssl://' : '';
        $socket = @stream_socket_client(
            $prefix . $host . ':' . $port,
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $context
        );
        
        if (!$socket) {
            return ['success' => false, 'message' => "No se pudo conectar al servidor SMTP: $errstr ($errno)"];
        }
        
        // Leer respuesta inicial
        $respuesta = fgets($socket, 515);
        if (substr($respuesta, 0, 3) != '220') {
            fclose($socket);
            return ['success' => false, 'message' => "Respuesta inesperada del servidor: $respuesta"];
        }
        
        // EHLO
        fwrite($socket, "EHLO localhost\r\n");
        $respuesta = leerRespuestaSMTP($socket);
        
        // STARTTLS si es TLS
        if ($encryption === 'tls') {
            fwrite($socket, "STARTTLS\r\n");
            $respuesta = fgets($socket, 515);
            if (substr($respuesta, 0, 3) != '220') {
                fclose($socket);
                return ['success' => false, 'message' => "Error al iniciar TLS: $respuesta"];
            }
            
            // Activar encriptación
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            
            // EHLO de nuevo después de TLS
            fwrite($socket, "EHLO localhost\r\n");
            $respuesta = leerRespuestaSMTP($socket);
        }
        
        // AUTH LOGIN
        fwrite($socket, "AUTH LOGIN\r\n");
        $respuesta = fgets($socket, 515);
        if (substr($respuesta, 0, 3) != '334') {
            fclose($socket);
            return ['success' => false, 'message' => "El servidor no soporta AUTH LOGIN: $respuesta"];
        }
        
        // Usuario
        fwrite($socket, base64_encode($user) . "\r\n");
        $respuesta = fgets($socket, 515);
        if (substr($respuesta, 0, 3) != '334') {
            fclose($socket);
            return ['success' => false, 'message' => "Error en usuario: $respuesta"];
        }
        
        // Contraseña
        fwrite($socket, base64_encode($pass) . "\r\n");
        $respuesta = fgets($socket, 515);
        if (substr($respuesta, 0, 3) != '235') {
            fclose($socket);
            return ['success' => false, 'message' => "Autenticación fallida. Verifica usuario y contraseña. Si usas Gmail, necesitas una 'Contraseña de aplicación'."];
        }
        
        // MAIL FROM
        fwrite($socket, "MAIL FROM:<$fromEmail>\r\n");
        $respuesta = fgets($socket, 515);
        if (substr($respuesta, 0, 3) != '250') {
            fclose($socket);
            return ['success' => false, 'message' => "Error en MAIL FROM: $respuesta"];
        }
        
        // RCPT TO
        fwrite($socket, "RCPT TO:<$para>\r\n");
        $respuesta = fgets($socket, 515);
        if (substr($respuesta, 0, 3) != '250') {
            fclose($socket);
            return ['success' => false, 'message' => "Error en RCPT TO: $respuesta"];
        }
        
        // DATA
        fwrite($socket, "DATA\r\n");
        $respuesta = fgets($socket, 515);
        if (substr($respuesta, 0, 3) != '354') {
            fclose($socket);
            return ['success' => false, 'message' => "Error al iniciar DATA: $respuesta"];
        }
        
        // Construir mensaje
        $boundary = md5(time());
        $mensaje = "From: $fromName <$fromEmail>\r\n";
        $mensaje .= "To: $para\r\n";
        $mensaje .= "Subject: =?UTF-8?B?" . base64_encode($asunto) . "?=\r\n";
        $mensaje .= "MIME-Version: 1.0\r\n";
        $mensaje .= "Content-Type: text/html; charset=UTF-8\r\n";
        $mensaje .= "Content-Transfer-Encoding: base64\r\n";
        $mensaje .= "\r\n";
        $mensaje .= chunk_split(base64_encode($cuerpoHtml));
        $mensaje .= "\r\n.\r\n";
        
        fwrite($socket, $mensaje);
        $respuesta = fgets($socket, 515);
        if (substr($respuesta, 0, 3) != '250') {
            fclose($socket);
            return ['success' => false, 'message' => "Error al enviar mensaje: $respuesta"];
        }
        
        // QUIT
        fwrite($socket, "QUIT\r\n");
        fclose($socket);
        
        return ['success' => true, 'message' => 'Correo enviado correctamente'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Lee respuesta multilínea de SMTP
 */
function leerRespuestaSMTP($socket) {
    $respuesta = '';
    while ($linea = fgets($socket, 515)) {
        $respuesta .= $linea;
        if (substr($linea, 3, 1) == ' ') break;
    }
    return $respuesta;
}

/**
 * Genera plantilla HTML para correo de bienvenida
 */
function plantillaCorreoBienvenida($nombre, $usuario, $password, $urlSistema) {
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .header { background: #1a2744; color: white; padding: 30px; text-align: center; }
            .header h1 { margin: 0; font-size: 24px; }
            .content { padding: 30px; }
            .credentials { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin: 20px 0; }
            .credentials p { margin: 8px 0; }
            .credentials strong { color: #1a2744; }
            .btn { display: inline-block; background: #2563eb; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; margin-top: 20px; }
            .warning { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; }
            .footer { background: #f8fafc; padding: 20px; text-align: center; color: #64748b; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🎓 SIAE-IMSS</h1>
                <p>Sistema de Altas y Bajas IMSS</p>
            </div>
            <div class="content">
                <h2>¡Bienvenido(a), ' . htmlspecialchars($nombre) . '!</h2>
                <p>Se ha creado tu cuenta en el sistema SIAE-IMSS. A continuación encontrarás tus credenciales de acceso:</p>
                
                <div class="credentials">
                    <p><strong>Usuario:</strong> ' . htmlspecialchars($usuario) . '</p>
                    <p><strong>Contraseña:</strong> ' . htmlspecialchars($password) . '</p>
                </div>
                
                <div class="warning">
                    ⚠️ <strong>Importante:</strong> Por seguridad, deberás cambiar tu contraseña en tu primer inicio de sesión.
                </div>
                
                <p>Haz clic en el siguiente botón para ingresar al sistema:</p>
                <a href="' . htmlspecialchars($urlSistema) . '" class="btn">Ingresar al Sistema</a>
            </div>
            <div class="footer">
                <p>Este es un correo automático, por favor no responder.</p>
                <p>SIAE-IMSS © ' . date('Y') . '</p>
            </div>
        </div>
    </body>
    </html>';
}

/**
 * Genera plantilla HTML para correo de prueba
 */
function plantillaCorreoPrueba() {
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .header { background: #22c55e; color: white; padding: 30px; text-align: center; }
            .header h1 { margin: 0; font-size: 24px; }
            .content { padding: 30px; text-align: center; }
            .check { font-size: 60px; margin: 20px 0; }
            .footer { background: #f8fafc; padding: 20px; text-align: center; color: #64748b; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>📩 Prueba de Correo SMTP</h1>
            </div>
            <div class="content">
                <div class="check">📧</div>
                <h2>¡Funciona correctamente!</h2>
                <p>Si estás viendo este correo, la configuración SMTP está funcionando.</p>
                <p><strong>Fecha y hora:</strong> ' . date('d/m/Y H:i:s') . '</p>
            </div>
            <div class="footer">
                <p>SIAE-IMSS © ' . date('Y') . '</p>
            </div>
        </div>
    </body>
    </html>';
}

/**
 * Genera plantilla HTML para notificación de exportación TXT
 */
function plantillaCorreoExportacion($nombreJefa, $nombreAdmin, $rolAdmin, $tablaNombre, $tipoMov, $registros, $archivo, $fechaHora, $ip) {
    $colorTipo = $tipoMov === 'ALTA' ? '#22c55e' : '#ef4444';
    $iconoTipo = $tipoMov === 'ALTA' ? '📈' : '📉';
    
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .header { background: #1a2744; color: white; padding: 25px; text-align: center; }
            .header h1 { margin: 0; font-size: 22px; }
            .content { padding: 30px; }
            .alert-box { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 0 0 20px 0; border-radius: 0 6px 6px 0; }
            .info-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            .info-table td { padding: 12px; border-bottom: 1px solid #e2e8f0; }
            .info-table td:first-child { color: #64748b; width: 140px; }
            .info-table td:last-child { font-weight: 500; }
            .badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; color: white; }
            .footer { background: #f8fafc; padding: 20px; text-align: center; color: #64748b; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>📤 Notificación de Exportación</h1>
                <p style="margin: 10px 0 0 0; opacity: 0.9;">SIAE-IMSS</p>
            </div>
            <div class="content">
                <p>Hola <strong>' . htmlspecialchars($nombreJefa) . '</strong>,</p>
                
                <div class="alert-box">
                    <strong>' . htmlspecialchars($nombreAdmin) . '</strong> (' . htmlspecialchars($rolAdmin) . ') ha exportado un archivo TXT para el IMSS.
                </div>
                
                <table class="info-table">
                    <tr>
                        <td>Tabla:</td>
                        <td>' . htmlspecialchars($tablaNombre) . '</td>
                    </tr>
                    <tr>
                        <td>Tipo de movimiento:</td>
                        <td><span class="badge" style="background: ' . $colorTipo . ';">' . $iconoTipo . ' ' . $tipoMov . '</span></td>
                    </tr>
                    <tr>
                        <td>Registros:</td>
                        <td>' . $registros . ' alumnos</td>
                    </tr>
                    <tr>
                        <td>Archivo generado:</td>
                        <td><code style="background: #f1f5f9; padding: 2px 8px; border-radius: 4px;">' . htmlspecialchars($archivo) . '</code></td>
                    </tr>
                    <tr>
                        <td>Fecha y hora:</td>
                        <td>' . $fechaHora . '</td>
                    </tr>
                    <tr>
                        <td>IP:</td>
                        <td>' . htmlspecialchars($ip) . '</td>
                    </tr>
                </table>
                
                <p style="color: #64748b; font-size: 14px;">
                    Puedes revisar esta exportación en el sistema y marcarla como ✓ Revisada o ⚠️ Problema si detectas algún error.
                </p>
            </div>
            <div class="footer">
                <p>Este es un correo automático generado por SIAE-IMSS.</p>
                <p>© ' . date('Y') . ' - Tecnológico de Chetumal</p>
            </div>
        </div>
    </body>
    </html>';
}
