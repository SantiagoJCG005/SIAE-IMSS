<?php
/**
 * SimpleXLSX - Librería simple para leer archivos XLSX
 * Versión simplificada y funcional
 * 
 * Uso:
 *   $xlsx = SimpleXLSX::parse('archivo.xlsx');
 *   $filas = $xlsx->rows();
 */

class SimpleXLSX {
    
    private $sheets = [];
    private $sharedStrings = [];
    private $styles = [];
    private static $error = '';
    
    /**
     * Parsea un archivo XLSX
     */
    public static function parse($ruta) {
        self::$error = '';
        
        if (!file_exists($ruta)) {
            self::$error = 'Archivo no encontrado: ' . $ruta;
            return false;
        }
        
        $instancia = new self();
        
        if (!$instancia->leerArchivo($ruta)) {
            return false;
        }
        
        return $instancia;
    }
    
    /**
     * Retorna el último error
     */
    public static function parseError() {
        return self::$error;
    }
    
    /**
     * Lee el archivo XLSX (que es un ZIP)
     */
    private function leerArchivo($ruta) {
        $zip = new ZipArchive();
        
        if ($zip->open($ruta) !== true) {
            self::$error = 'No se pudo abrir el archivo XLSX';
            return false;
        }
        
        // Lee strings compartidos
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedStringsXml) {
            $this->parsearSharedStrings($sharedStringsXml);
        }
        
        // Lee la primera hoja (sheet1.xml)
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if (!$sheetXml) {
            self::$error = 'No se encontró la hoja de datos';
            $zip->close();
            return false;
        }
        
        $this->parsearHoja($sheetXml);
        
        $zip->close();
        return true;
    }
    
    /**
     * Parsea los strings compartidos
     */
    private function parsearSharedStrings($xml) {
        $doc = new DOMDocument();
        @$doc->loadXML($xml);
        
        $elementos = $doc->getElementsByTagName('si');
        
        foreach ($elementos as $elemento) {
            $texto = '';
            $nodos = $elemento->getElementsByTagName('t');
            foreach ($nodos as $nodo) {
                $texto .= $nodo->nodeValue;
            }
            $this->sharedStrings[] = $texto;
        }
    }
    
    /**
     * Parsea una hoja de cálculo
     */
    private function parsearHoja($xml) {
        $doc = new DOMDocument();
        @$doc->loadXML($xml);
        
        $filas = $doc->getElementsByTagName('row');
        $datos = [];
        
        foreach ($filas as $fila) {
            $filaNum = intval($fila->getAttribute('r'));
            $celdas = $fila->getElementsByTagName('c');
            $filaDatos = [];
            $ultimaColumna = 0;
            
            foreach ($celdas as $celda) {
                $ref = $celda->getAttribute('r');
                $columna = $this->letraANumero(preg_replace('/[0-9]/', '', $ref));
                
                // Rellena celdas vacías
                while ($ultimaColumna < $columna) {
                    $filaDatos[] = '';
                    $ultimaColumna++;
                }
                
                $valor = $this->obtenerValorCelda($celda);
                $filaDatos[] = $valor;
                $ultimaColumna = $columna + 1;
            }
            
            $datos[$filaNum] = $filaDatos;
        }
        
        // Ordena por número de fila y reindexar
        ksort($datos);
        $this->sheets[0] = array_values($datos);
    }
    
    /**
     * Obtiene el valor de una celda
     */
    private function obtenerValorCelda($celda) {
        $tipo = $celda->getAttribute('t');
        $valorNodo = $celda->getElementsByTagName('v')->item(0);
        
        if (!$valorNodo) {
            // Puede ser una celda con fórmula inline
            $inlineStr = $celda->getElementsByTagName('is')->item(0);
            if ($inlineStr) {
                $texto = '';
                $ts = $inlineStr->getElementsByTagName('t');
                foreach ($ts as $t) {
                    $texto .= $t->nodeValue;
                }
                return $texto;
            }
            return '';
        }
        
        $valor = $valorNodo->nodeValue;
        
        // Si es string compartido
        if ($tipo === 's') {
            $indice = intval($valor);
            return isset($this->sharedStrings[$indice]) ? $this->sharedStrings[$indice] : '';
        }
        
        // Si es inline string
        if ($tipo === 'inlineStr') {
            return $valor;
        }
        
        // Si es booleano
        if ($tipo === 'b') {
            return $valor === '1' ? 'TRUE' : 'FALSE';
        }
        
        // Número o texto directo
        return $valor;
    }
    
    /**
     * Convierte letra de columna a número (A=0, B=1, etc.)
     */
    private function letraANumero($letras) {
        $letras = strtoupper($letras);
        $resultado = 0;
        $longitud = strlen($letras);
        
        for ($i = 0; $i < $longitud; $i++) {
            $resultado *= 26;
            $resultado += ord($letras[$i]) - ord('A') + 1;
        }
        
        return $resultado - 1;
    }
    
    /**
     * Retorna todas las filas de la primera hoja
     */
    public function rows($indiceHoja = 0) {
        return isset($this->sheets[$indiceHoja]) ? $this->sheets[$indiceHoja] : [];
    }
    
    /**
     * Retorna el número de filas
     */
    public function rowsCount($indiceHoja = 0) {
        return count($this->rows($indiceHoja));
    }
}