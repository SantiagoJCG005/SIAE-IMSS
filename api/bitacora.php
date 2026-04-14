<?php
/** * SI FUNCIONA NO LE MUEVAS!!!!!
 * SIAE-IMSS - API de Bitacora
 */

// Incluye archivo de autenticacion (login, roles, etc)
require_once __DIR__ . '/../includes/auth.php';

// Incluye funciones generales del sistema (conexion, utilidades, etc)
require_once __DIR__ . '/../includes/functions.php';

// Verifica si el usuario NO ha iniciado sesion
if (!estaLogueado()) {
    // Si no ha iniciado sesion, lo manda al login
    header('Location: ' . URL_BASE . 'views/auth/login.php');
    exit; // Detiene la ejecucion
}

// Verifica que el usuario tenga alguno de estos roles permitidos
requerirRol([ROL_SUPERADMIN, ROL_JEFA_SERVICIOS]);

// Obtiene la conexion a la base de datos
$conexion = obtenerConexion();

// Obtiene la accion enviada por la URL (por ejemplo ?action=export)
// Si no existe, se guarda vacio
$accion = $_GET['action'] ?? '';

// Verifica si la accion es "export"
if ($accion === 'export') {

    // Obtener filtros enviados por la URL
    $busqueda = $_GET['search'] ?? ''; // texto de busqueda
    $filtroAccion = $_GET['accion'] ?? ''; // filtro por tipo de accion
    $fechaDesde = $_GET['fecha_desde'] ?? ''; // fecha inicial
    $fechaHasta = $_GET['fecha_hasta'] ?? ''; // fecha final
    
    // Base de la condicion SQL (siempre verdadera al inicio)
    $condicionWhere = "WHERE 1=1";

    // Arreglo para guardar valores de forma segura
    $parametros = [];
    
    // Si hay texto de busqueda
    if (!empty($busqueda)) {
        // Agrega condicion para buscar en username, nombre o detalle
        $condicionWhere .= " AND (u.username LIKE ? OR u.nombre_completo LIKE ? OR b.detalle LIKE ?)";
        
        // Agrega los valores al arreglo de parametros
        $parametros[] = "%$busqueda%";
        $parametros[] = "%$busqueda%";
        $parametros[] = "%$busqueda%";
    }
    
    // Si se selecciono un tipo de accion
    if (!empty($filtroAccion)) {
        // Filtra por tipo de accion
        $condicionWhere .= " AND b.accion = ?";
        $parametros[] = $filtroAccion;
    }
    
    // Si hay fecha desde
    if (!empty($fechaDesde)) {
        // Filtra registros desde esa fecha
        $condicionWhere .= " AND DATE(b.fecha) >= ?";
        $parametros[] = $fechaDesde;
    }
    
    // Si hay fecha hasta
    if (!empty($fechaHasta)) {
        // Filtra registros hasta esa fecha
        $condicionWhere .= " AND DATE(b.fecha) <= ?";
        $parametros[] = $fechaHasta;
    }
    
    // Consulta SQL para obtener datos de la bitacora junto con datos del usuario
    $sql = "
        SELECT b.fecha, u.username, u.nombre_completo, b.accion, b.detalle, b.ip_address
        FROM bitacora b 
        LEFT JOIN usuarios u ON b.id_usuario = u.id_usuario 
        $condicionWhere 
        ORDER BY b.fecha DESC
    ";

    // Prepara la consulta para evitar inyeccion SQL
    $consulta = $conexion->prepare($sql);

    // Ejecuta la consulta con los parametros
    $consulta->execute($parametros);

    // Obtiene todos los registros
    $listaRegistros = $consulta->fetchAll();
    
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
    foreach ($listaRegistros as $fila) {
        // Escribe cada fila en el CSV
        fputcsv($output, [
            $fila['fecha'], // fecha del registro
            $fila['username'] ?? 'Sistema', // usuario o "Sistema" si es null
            $fila['nombre_completo'] ?? '-', // nombre o "-" si es null
            $fila['accion'], // accion realizada
            $fila['detalle'], // descripcion
            $fila['ip_address'] // direccion IP
        ]);
    }
    
    // Cierra el archivo de salida
    fclose($output);
    
    // Registra en la bitacora que se exporto el archivo
    registrarEnBitacora('EXPORTAR_BITACORA', 'Exportacion CSV de bitacora');

    exit; // Finaliza el proceso
}

// Si no se envio una accion valida

// Indica que la respuesta sera en formato JSON
header('Content-Type: application/json');

// Devuelve un mensaje de error en formato JSON
echo json_encode(['error' => 'Accion no valida']);