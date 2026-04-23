<?php
/** * SI FUNCIONA NO LE MUEVAS!!!!!
 * SIAE-IMSS - Configuracion de Base de Datos
 * Conexion PDO a MySQL
 */

// Define el servidor de base de datos (normalmente localhost)
define('DB_HOST', 'localhost');

// Nombre de la base de datos
define('DB_NAME', 'siae_imss');

// Usuario de la base de datos
define('DB_USER', 'root');

// Contraseña de la base de datos
define('DB_PASS', 'Denji_2005');

// Tipo de codificacion de caracteres
define('DB_CHARSET', 'utf8mb4');

/**
 * Funcion para obtener la conexion a la base de datos
 */
function obtenerConexion() {

    // Variable estatica que guarda la conexion (para no crearla varias veces)
    static $conexion = null;
    
    // Si aun no existe la conexion
    if ($conexion === null) {
        try {

            // Crea la cadena de conexion (dsn)
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

            // Opciones de configuracion de PDO
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // muestra errores como excepciones
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // devuelve resultados como arreglo asociativo
                PDO::ATTR_EMULATE_PREPARES => false, // usa consultas preparadas reales
            ];

            // Crea la conexion PDO usando los datos
            $conexion = new PDO($dsn, DB_USER, DB_PASS, $options);

        } catch (PDOException $e) {

            // Si ocurre error, detiene el sistema y muestra mensaje
            die("Error de conexion: " . $e->getMessage());
        }
    }
    
    // Regresa la conexion
    return $conexion;
}