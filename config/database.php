<?php
/**
 * SIAE-IMSS - Configuración de Base de Datos
 * Conexión PDO a MySQL
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'siae_imss');
define('DB_USER', 'root');
define('DB_PASS', 'Denji_2005');
define('DB_CHARSET', 'utf8mb4');

/**
 * Obtener conexión PDO
 */
function getConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }
    
    return $pdo;
}
