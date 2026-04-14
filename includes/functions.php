<?php
/** * SI FUNCIONA NO LE MUEVAS!!!!!
 * SIAE-IMSS - Funciones Helper
 */

/**
 * Limpiar datos de entrada (seguridad basica)
 */
function limpiar($entrada) {

    // Si es un arreglo, limpia cada elemento
    if (is_array($entrada)) {
        return array_map('sanitize', $entrada);
    }

    // Quita espacios y convierte caracteres especiales a seguro
    return htmlspecialchars(trim($entrada), ENT_QUOTES, 'UTF-8');
}

/**
 * Limpiar texto para IMSS (quitar acentos y caracteres raros)
 */
function limpiarParaIMSS($text) {

    // Convierte a mayusculas y quita espacios
    $text = mb_strtoupper(trim($text), 'UTF-8');
    
    // Lista de caracteres a reemplazar
    $busqueda = ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ü', 'Ñ', 'á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'];

    // Reemplazo sin acentos
    $replace = ['A', 'E', 'I', 'O', 'U', 'U', 'N', 'A', 'E', 'I', 'O', 'U', 'U', 'N'];
    
    // Reemplaza caracteres especiales
    $text = str_replace($busqueda, $replace, $text);
    
    // Elimina todo lo que no sea letra, numero o espacio
    $text = preg_replace('/[^A-Z0-9 ]/', '', $text);
    
    return $text;
}

/**
 * Ajustar texto a tamaño fijo (para archivos TXT IMSS)
 */
function rellenarParaIMSS($text, $length, $padChar = ' ', $padType = STR_PAD_RIGHT) {

    // Limpia texto
    $text = limpiarParaIMSS($text);

    // Ajusta tamaño rellenando con espacios u otro caracter
    return mb_substr(str_pad($text, $length, $padChar, $padType), 0, $length);
}

/**
 * Validar CURP
 */
function validarCURP($curp) {

    // Convierte a mayusculas y limpia
    $curp = strtoupper(trim($curp));

    // Patron que debe cumplir
    $pattern = '/^[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[0-9A-Z][0-9]$/';

    // Verifica si cumple
    return preg_match($pattern, $curp);
}

/**
 * Validar NSS (11 digitos)
 */
function validarNSS($nss) {

    // Elimina todo lo que no sea numero
    $nss = preg_replace('/[^0-9]/', '', $nss);

    // Verifica longitud
    return strlen($nss) === 11;
}

/**
 * Formatear fecha
 */
function formatearFecha($date, $format = 'd/m/Y') {

    // Si esta vacio, regresa vacio
    if (empty($date)) return '';

    // Convierte y formatea fecha
    return date($format, strtotime($date));
}

/**
 * Formatear fecha y hora
 */
function formatearFechaTime($date, $format = 'd/m/Y H:i') {

    // Si esta vacio, regresa vacio
    if (empty($date)) return '';

    // Convierte y formatea
    return date($format, strtotime($date));
}

/**
 * Enviar respuesta JSON
 */
function respuestaJson($datos, $statusCode = 200) {

    // Codigo HTTP
    http_response_code($statusCode);

    // Tipo de respuesta
    header('Content-Type: application/json');

    // Convierte a JSON y envia
    echo json_encode($datos);

    exit; // termina ejecucion
}

/**
 * Respuesta exitosa
 */
function respuestaExitosa($datos = null, $mensaje = 'Operacion exitosa') {

    // Llama funcion base con estructura de exito
    respuestaJson([
        'success' => true,
        'message' => $mensaje,
        'data' => $datos
    ]);
}

/**
 * Respuesta de error
 */
function respuestaError($mensaje = 'Error en la operacion', $statusCode = 400) {

    // Llama funcion base con error
    respuestaJson([
        'success' => false,
        'message' => $mensaje
    ], $statusCode);
}

/**
 * Obtener iniciales de un nombre
 */
function obtenerIniciales($name) {

    // Divide el nombre en palabras
    $words = explode(' ', trim($name));

    // Variable para iniciales
    $initials = '';

    // Recorre palabras
    foreach ($words as $word) {

        // Si no esta vacia
        if (!empty($word)) {

            // Toma primera letra
            $initials .= mb_substr($word, 0, 1, 'UTF-8');

            // Solo 2 letras maximo
            if (strlen($initials) >= 2) break;
        }
    }

    // Regresa en mayusculas
    return strtoupper($initials);
}

/**
 * Generar color para avatar
 */
function obtenerColorAvatar($name) {

    // Lista de colores posibles
    $colors = ['#3B8BD4', '#1D9E75', '#D85A30', '#7F77DD', '#D4537E', '#BA7517'];

    // Genera numero basado en el nombre
    $index = abs(crc32($name)) % count($colors);

    // Regresa color
    return $colors[$index];
}

/**
 * Paginacion de resultados
 */
function paginar($totalRegistros, $porPagina = 10, $currentPage = 1) {

    // Calcula total de paginas
    $totalPages = ceil($totalRegistros / $porPagina);

    // Ajusta pagina actual dentro del rango
    $currentPage = max(1, min($currentPage, $totalPages));

    // Calcula desde donde empezar
    $offset = ($currentPage - 1) * $porPagina;
    
    // Regresa datos de paginacion
    return [
        'total' => $totalRegistros,
        'per_page' => $porPagina,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'has_prev' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages
    ];
}

/**
 * Guardar mensaje temporal
 */
function guardarMensajeFlash($type, $mensaje) {

    // Guarda en sesion
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $mensaje
    ];
}

/**
 * Obtener y borrar mensaje temporal
 */
function obtenerMensajeFlash() {

    // Si existe mensaje
    if (isset($_SESSION['flash'])) {

        // Lo guarda en variable
        $flash = $_SESSION['flash'];

        // Lo elimina de sesion
        unset($_SESSION['flash']);

        return $flash;
    }

    return null;
}

/**
 * Verifica si la peticion es AJAX
 */
function esAjax() {

    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Obtener dato GET limpio
 */
function obtenerGet($key, $default = '') {

    return isset($_GET[$key]) ? limpiar($_GET[$key]) : $default;
}

/**
 * Obtener dato POST limpio
 */
function obtenerPost($key, $default = '') {

    return isset($_POST[$key]) ? limpiar($_POST[$key]) : $default;
}