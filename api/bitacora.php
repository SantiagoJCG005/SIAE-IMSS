<?php
/**
 * SIAE-IMSS - API de Bitacora
 */

// Incluye archivo de autenticacion (login, roles, etc)
require_once __DIR__ . '/../includes/auth.php';

// Incluye funciones generales del sistema (conexion, utilidades, etc)
require_once __DIR__ . '/../includes/functions.php';

// Verifica si el usuario NO ha iniciado sesion
if (!isLoggedIn()) {
    // Si no ha iniciado sesion, lo manda al login
    header('Location: ' . BASE_URL . 'views/auth/login.php');
    exit; // Detiene la ejecucion
}

// Verifica que el usuario tenga alguno de estos roles permitidos
requireRole([ROL_SUPERADMIN, ROL_JEFA_SERVICIOS]);

// Obtiene la conexion a la base de datos
$pdo = getConnection();

// Obtiene la accion enviada por la URL (por ejemplo ?action=export)
// Si no existe, se guarda vacio
$action = $_GET['action'] ?? '';

// Verifica si la accion es "export"
if ($action === 'export') {

    // Obtener filtros enviados por la URL
    $search = $_GET['search'] ?? ''; // texto de busqueda
    $accionFilter = $_GET['accion'] ?? ''; // filtro por tipo de accion
    $fechaDesde = $_GET['fecha_desde'] ?? ''; // fecha inicial
    $fechaHasta = $_GET['fecha_hasta'] ?? ''; // fecha final
    
    // Base de la condicion SQL (siempre verdadera al inicio)
    $where = "WHERE 1=1";

    // Arreglo para guardar valores de forma segura
    $params = [];
    
    // Si hay texto de busqueda
    if (!empty($search)) {
        // Agrega condicion para buscar en username, nombre o detalle
        $where .= " AND (u.username LIKE ? OR u.nombre_completo LIKE ? OR b.detalle LIKE ?)";
        
        // Agrega los valores al arreglo de parametros
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    // Si se selecciono un tipo de accion
    if (!empty($accionFilter)) {
        // Filtra por tipo de accion
        $where .= " AND b.accion = ?";
        $params[] = $accionFilter;
    }
    
    // Si hay fecha desde
    if (!empty($fechaDesde)) {
        // Filtra registros desde esa fecha
        $where .= " AND DATE(b.fecha) >= ?";
        $params[] = $fechaDesde;
    }
    
    // Si hay fecha hasta
    if (!empty($fechaHasta)) {
        // Filtra registros hasta esa fecha
        $where .= " AND DATE(b.fecha) <= ?";
        $params[] = $fechaHasta;
    }
    
    // Consulta SQL para obtener datos de la bitacora junto con datos del usuario
    $sql = "
        SELECT b.fecha, u.username, u.nombre_completo, b.accion, b.detalle, b.ip_address
        FROM bitacora b 
        LEFT JOIN usuarios u ON b.id_usuario = u.id_usuario 
        $where 
        ORDER BY b.fecha DESC
    ";

    // Prepara la consulta para evitar inyeccion SQL
    $stmt = $pdo->prepare($sql);

    // Ejecuta la consulta con los parametros
    $stmt->execute($params);

    // Obtiene todos los registros
    $registros = $stmt->fetchAll();
    
    // Indica que la respuesta sera un archivo CSV
    header('Content-Type: text/csv; charset=utf-8');

    // Indica que se descargara un archivo con nombre dinamico (fecha y hora)
    header('Content-Disposition: attachment; filename="bitacora_' . date('Y-m-d_His') . '.csv"');
    
    // Abre la salida para escribir el archivo CSV
    $output = fopen('php://output', 'w');

    // Agrega BOM para que excel reconozca bien los caracteres UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Escribe la fila de encabezados del archivo CSV
    fputcsv($output, ['Fecha', 'Usuario', 'Nombre', 'Accion', 'Detalle', 'IP']);
    
    // Recorre cada registro obtenido
    foreach ($registros as $row) {
        // Escribe cada fila en el CSV
        fputcsv($output, [
            $row['fecha'], // fecha del registro
            $row['username'] ?? 'Sistema', // usuario o "Sistema" si es null
            $row['nombre_completo'] ?? '-', // nombre o "-" si es null
            $row['accion'], // accion realizada
            $row['detalle'], // descripcion
            $row['ip_address'] // direccion IP
        ]);
    }
    
    // Cierra el archivo de salida
    fclose($output);
    
    // Registra en la bitacora que se exporto el archivo
    registrarBitacora('EXPORTAR_BITACORA', 'Exportacion CSV de bitacora');

    exit; // Finaliza el proceso
}

// Si no se envio una accion valida

// Indica que la respuesta sera en formato JSON
header('Content-Type: application/json');

// Devuelve un mensaje de error en formato JSON
echo json_encode(['error' => 'Accion no valida']);