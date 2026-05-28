<?php
/**
 * SIAE-IMSS - Extractor de texto de PDFs
 * Estrategia: buscar desde /FlateDecode hasta el siguiente stream\n
 * para tener fronteras exactas antes de descomprimir.
 */

class PdfTextExtractor {

    /**
     * Extrae todo el texto de un PDF
     */
    public static function extraer($rutaArchivo) {
        $datos = file_get_contents($rutaArchivo);
        if (!$datos) return '';

        $texto = '';

        // 1) Streams FlateDecode (contenido principal)
        $texto .= self::extraerFlateStreams($datos);

        // 2) Strings de metadatos y streams sin comprimir (por si acaso)
        $texto .= self::extraerStringsCrudos($datos);

        // 3) Limpiar sección de Sello Digital y cadenas hex largas antes de devolver
        return self::limpiarSelloDigital($texto);
    }

    /**
     * Elimina el Sello Digital del IMSS y cadenas hexadecimales largas del texto extraído.
     *
     * El Sello Digital es una firma hexadecimal al final del PDF (>200 chars hex consecutivos).
     * Si no se limpia, el extractor de fallback \d{11} puede capturar secuencias numéricas
     * dentro del hash como NSS falsos, contaminando la extracción de datos.
     */
    private static function limpiarSelloDigital($texto) {
        // Eliminar desde "Sello Digital:" (o variantes) hasta fin de párrafo
        $texto = preg_replace('/Sello\s+Digital\s*:[\s\S]{0,2000}?(?=\n\s*\n|\z)/i', '', $texto);

        // Eliminar cadenas hexadecimales puras de más de 30 caracteres consecutivos
        // (sello digital, firmas, hashes — nunca forman parte de datos IMSS)
        $texto = preg_replace('/[0-9A-F]{30,}/i', '', $texto);

        return $texto;
    }

    /**
     * Localiza cada /FlateDecode en el PDF, busca el stream\n inmediato
     * y lo descomprime. Es la estrategia más robusta sin biblioteca externa.
     */
    private static function extraerFlateStreams($datos) {
        $texto   = '';
        $offset  = 0;
        $datLen  = strlen($datos);

        while ($offset < $datLen) {

            // Buscar siguiente FlateDecode
            $posFlate = strpos($datos, 'FlateDecode', $offset);
            if ($posFlate === false) break;

            // Buscar "stream" + \r?\n dentro de los siguientes 800 bytes
            $zona = substr($datos, $posFlate, 800);
            if (!preg_match('/\bstream\r?\n/', $zona, $m, PREG_OFFSET_CAPTURE)) {
                $offset = $posFlate + 11;
                continue;
            }

            // Posición absoluta donde empieza el contenido del stream
            $streamStart = $posFlate + $m[0][1] + strlen($m[0][0]);

            // Buscar endstream
            $endPos = strpos($datos, 'endstream', $streamStart);
            if ($endPos === false) {
                $offset = $posFlate + 11;
                continue;
            }

            // Longitud máxima razonable (10 MB)
            $rawLen = $endPos - $streamStart;
            if ($rawLen <= 0 || $rawLen > 10 * 1024 * 1024) {
                $offset = $posFlate + 11;
                continue;
            }

            $streamData = substr($datos, $streamStart, $rawLen);

            // Quitar el \r\n final que separa los datos de "endstream"
            if (substr($streamData, -2) === "\r\n") {
                $streamData = substr($streamData, 0, -2);
            } elseif (substr($streamData, -1) === "\n" || substr($streamData, -1) === "\r") {
                $streamData = substr($streamData, 0, -1);
            }

            // Descomprimir
            $descomp = self::descomprimir($streamData);
            if ($descomp !== false && strlen($descomp) > 0) {
                $texto .= self::parsearContenido($descomp) . "\n";
            }

            $offset = $endPos + 9;
        }

        return $texto;
    }

    /**
     * Intenta descomprimir con todos los metodos disponibles.
     * Prueba zlib_decode, gzuncompress y gzinflate en orden de compatibilidad.
     */
    private static function descomprimir($data) {
        if (strlen($data) === 0) return false;

        // zlib_decode: el mas tolerante (PHP 5.4+), acepta cabecera o sin cabecera
        if (function_exists('zlib_decode')) {
            $r = @zlib_decode($data);
            if ($r !== false && strlen($r) > 0) return $r;
        }

        // gzuncompress: formato zlib (cabecera 2 bytes + deflate + checksum)
        $r = @gzuncompress($data);
        if ($r !== false && strlen($r) > 0) return $r;

        // gzinflate: deflate raw sin cabecera zlib
        $r = @gzinflate($data);
        if ($r !== false && strlen($r) > 0) return $r;

        // gzinflate saltando los 2 bytes de cabecera zlib (algunos PDFs)
        $r = @gzinflate(substr($data, 2));
        if ($r !== false && strlen($r) > 0) return $r;

        return false;
    }

    /**
     * Parsea el contenido de un stream PDF descomprimido y extrae el texto
     * Soporta: (string)Tj, [(array)]TJ, <hex>Tj, [<hex>]TJ
     */
    private static function parsearContenido($contenido) {
        $texto = '';

        // --- Strings entre paréntesis ---

        // (texto) Tj  o  (texto) '  o  (texto) "
        if (preg_match_all('/\(([^)\\\\]*(?:\\\\.[^)\\\\]*)*)\)\s*[Tj\'"]/', $contenido, $m)) {
            foreach ($m[1] as $str) {
                $decoded = self::decodificarString($str);
                if ($decoded !== '') $texto .= $decoded . '  ';
            }
        }

        // [(texto1)(kern)(texto2)] TJ
        if (preg_match_all('/\[((?:[^[\]]*|\([^)]*\))*)\]\s*TJ/', $contenido, $m)) {
            foreach ($m[1] as $arr) {
                if (preg_match_all('/\(([^)]*)\)/', $arr, $partes)) {
                    foreach ($partes[1] as $parte) {
                        $texto .= self::decodificarString($parte);
                    }
                }
                $texto .= '  ';
            }
        }

        // --- Strings hexadecimales ---

        // <hex> Tj
        if (preg_match_all('/<([0-9A-Fa-f]+)>\s*Tj/', $contenido, $m)) {
            foreach ($m[1] as $hex) {
                $t = self::hexToText($hex);
                if ($t !== '') $texto .= $t . '  ';
            }
        }

        // [<hex1>num<hex2>...] TJ
        if (preg_match_all('/\[((?:<[0-9A-Fa-f]*>|\s*-?\d+\s*)*)\]\s*TJ/', $contenido, $m)) {
            foreach ($m[1] as $arr) {
                if (preg_match_all('/<([0-9A-Fa-f]+)>/', $arr, $hexParts)) {
                    foreach ($hexParts[1] as $hex) {
                        $texto .= self::hexToText($hex);
                    }
                }
                $texto .= '  ';
            }
        }

        return $texto;
    }

    /**
     * Convierte un string hex de PDF a texto legible
     * Maneja codificación de 1 byte (Latin-1) y 2 bytes (Unicode/CIDFont)
     */
    private static function hexToText($hex) {
        $hex = strtoupper($hex);
        $len = strlen($hex);
        if ($len === 0) return '';

        $texto = '';

        // Intentar decodificación de 2 bytes (CIDFont / Unicode)
        if ($len % 4 === 0 && $len >= 4) {
            $tmp = '';
            $todoDigitos = true;
            for ($i = 0; $i < $len; $i += 4) {
                $cp = hexdec(substr($hex, $i, 4));
                if ($cp >= 0x20 && $cp <= 0x7E) {
                    $tmp .= chr($cp);
                } elseif ($cp === 0) {
                    // nulo, ignorar
                } else {
                    $todoDigitos = false;
                    break;
                }
            }
            if ($todoDigitos || preg_match('/\d/', $tmp)) {
                return $tmp;
            }
        }

        // Decodificación de 1 byte (ISO-8859-1 / WinAnsi)
        for ($i = 0; $i + 1 < $len; $i += 2) {
            $c = hexdec(substr($hex, $i, 2));
            $texto .= ($c >= 0x20 && $c <= 0x7E) ? chr($c) : '';
        }

        return $texto;
    }

    /**
     * Decodifica escapes de un string PDF entre paréntesis
     */
    private static function decodificarString($str) {
        // Escapes estándar PDF
        $str = preg_replace_callback('/\\\\([0-7]{1,3})/', function($m) {
            return chr(octdec($m[1]));
        }, $str);

        $str = str_replace(
            ['\\n', '\\r', '\\t', '\\b', '\\f', '\\(', '\\)', '\\\\'],
            ["\n",  "\r",  "\t",  "\x08", "\x0C", '(',   ')',   '\\'],
            $str
        );

        // Solo retornar si contiene texto ASCII legible
        $limpio = preg_replace('/[^\x20-\x7E\n\r\t]/', '', $str);
        return $limpio;
    }

    /**
     * Extrae strings legibles de los metadatos y objetos no comprimidos del PDF
     */
    private static function extraerStringsCrudos($datos) {
        $texto = '';

        // Strings entre paréntesis en metadatos (fuera de streams)
        // Filtra binario: solo acepta si al menos el 70% son caracteres ASCII
        if (preg_match_all('/\(([^()]{3,100})\)/', $datos, $m)) {
            foreach ($m[1] as $str) {
                $ascii = preg_replace('/[^\x20-\x7E]/', '', $str);
                if (strlen($ascii) >= strlen($str) * 0.7 && strlen($ascii) >= 3) {
                    $texto .= $ascii . ' ';
                }
            }
        }

        return $texto;
    }

    // ─────────────────────────────────────────────────────────────
    //  MÉTODOS PÚBLICOS
    // ─────────────────────────────────────────────────────────────

    /**
     * Para cada NSS encontrado en el texto extrae apellido paterno, apellido materno,
     * nombre(s) y CURP tal como aparecen en las líneas de datos IMSS del PDF.
     *
     * El segmento entre el final del NSS y el inicio del siguiente registro contiene:
     *   palabras en mayúsculas (nombre) → números (salario, fecha, UMF, op, folio) → CURP
     *
     * @param string      $texto          Texto extraído del PDF
     * @param string|null $patronalConDV  Registro patronal completo con DV
     * @return array  NSS → ['apellido_paterno'=>, 'apellido_materno'=>, 'nombre'=>, 'curp'=>]
     */
    public static function extraerLineasDetalladas($texto, $patronalConDV = null) {
        $registros = [];

        $patronPrefix = $patronalConDV
            ? preg_quote($patronalConDV, '/')
            : '[A-Z][A-Z0-9]{9}\d';

        if (!preg_match_all('/' . $patronPrefix . '(\d{11})/', $texto, $matches, PREG_OFFSET_CAPTURE)) {
            return $registros;
        }

        $total = count($matches[1]);
        
        // ── 1) PRE-CALCULAR CURPS GLOBALES PARA ASIGNACIÓN POR CERCANÍA ──
        $curpsGlobales = [];
        // Intento primario: buscar CURPs en todo el texto
        if (preg_match_all('/[A-Z]{4}\d{6}[HM][A-Z]{2}[A-Z]{3}[A-Z0-9]\d/', $texto, $cm, PREG_OFFSET_CAPTURE)) {
            foreach ($cm[0] as $match) {
                $curpText = $match[0];
                $curpPos = $match[1];
                
                // Encontrar el NSS más cercano a este CURP
                $closestNssIdx = -1;
                $minDist = 999999;
                for ($i = 0; $i < $total; $i++) {
                    $dist = abs($curpPos - $matches[1][$i][1]);
                    if ($dist < $minDist) {
                        $minDist = $dist;
                        $closestNssIdx = $i;
                    }
                }
                
                // Si la distancia es razonable (max ~300 chars) y es el más cercano, asignar
                // Esto soluciona los CURPs cruzados porque cada CURP se ancla a su NSS correspondiente,
                // sin importar si se renderizó antes o después del NSS en el stream del PDF.
                if ($minDist < 300 && $closestNssIdx !== -1) {
                    $curpsGlobales[$closestNssIdx] = $curpText;
                }
            }
        }

        // ── 2) PROCESAR CADA REGISTRO ──
        for ($i = 0; $i < $total; $i++) {
            $nss    = $matches[1][$i][0];
            $nssPos = $matches[1][$i][1];
            $endPos    = $nssPos + 11;
            $nextStart = isset($matches[0][$i + 1]) ? $matches[0][$i + 1][1] : strlen($texto);
            $segFwd    = substr($texto, $endPos, $nextStart - $endPos);

            // Asignar CURP detectada globalmente
            $curp = $curpsGlobales[$i] ?? null;

            // ── NOMBRES ───────────────────────────────────────────────────────────
            // Para el último registro de cada página, el PDF intercala el pie de página
            // entre el nombre y el campo de salario. Limpiar esos fragmentos primero.
            $segFwd = preg_replace('/\s+P.{0,3}gina\s+\d.*$/su',        ' ', $segFwd);
            $segFwd = preg_replace('/\s+Este\s+documento\b.*$/su',       ' ', $segFwd);
            $segFwd = preg_replace('/\s+Sello\s+Digital\b.*$/su',        ' ', $segFwd);
            $segFwd = preg_replace('/\s+Contacto:\s.*$/su',              ' ', $segFwd);
            $segFwd = preg_replace('/\s+[A-Z0-9]{60,}.*$/su',           ' ', $segFwd); // hash del sello

            // Prioridad 1: campo de salario " 000000 " (siempre presente en IMSS).
            // Prioridad 2: primer número de 3+ dígitos. Límite máximo: 75 chars.
            $maxNombre = 75;
            if (preg_match('/[ \t]\d{6}[ \t]/', $segFwd, $dm, PREG_OFFSET_CAPTURE)) {
                $segNombre = substr($segFwd, 0, min($dm[0][1], $maxNombre));
            } elseif (preg_match('/\d{3,}/', $segFwd, $dm, PREG_OFFSET_CAPTURE)) {
                $segNombre = substr($segFwd, 0, min($dm[0][1], $maxNombre));
            } else {
                $segNombre = substr($segFwd, 0, $maxNombre);
            }

            // Si el segNombre quedó muy corto (caso segunda línea antes del nombre),
            // buscarlo en la ventana ampliada con el mismo límite.
            if (strlen(trim($segNombre)) < 4) {
                if (preg_match('/[ \t]\d{6}[ \t]/', $segFwd, $dm, PREG_OFFSET_CAPTURE)) {
                    $posNum = min($dm[0][1], $maxNombre);
                } elseif (preg_match('/\d{3,}/', $segFwd, $dm, PREG_OFFSET_CAPTURE)) {
                    $posNum = min($dm[0][1], $maxNombre);
                } else {
                    $posNum = false;
                }
                $segNombre = ($posNum !== false && $posNum > 4)
                    ? substr($segFwd, 0, $posNum)
                    : substr($segFwd, 0, $maxNombre);
            }

            $bloques = array_values(array_filter(array_map('trim', explode('  ', $segNombre)), function($b) {
                return strlen($b) > 0 && !preg_match('/^\d+$/', $b);
            }));

            if (count($bloques) >= 3) {
                $apellidosPdf = $bloques[0] . ' ' . $bloques[1];
                $nombrePdf    = implode(' ', array_slice($bloques, 2));
            } elseif (count($bloques) === 2) {
                $apellidosPdf = $bloques[0];
                $nombrePdf    = $bloques[1];
            } else {
                // Sin separación columnar: guardar todo como apellidos
                $apellidosPdf = trim($segNombre) ?: null;
                $nombrePdf    = null;
            }

            // Limpiar espacios internos redundantes y limitar longitud por seguridad
            if ($apellidosPdf) $apellidosPdf = substr(preg_replace('/\s+/', ' ', $apellidosPdf), 0, 149);
            if ($nombrePdf)    $nombrePdf    = substr(preg_replace('/\s+/', ' ', $nombrePdf),    0, 199);

            $registros[$nss][] = [
                'apellidos' => $apellidosPdf ?: null,
                'nombre'    => $nombrePdf    ?: null,
                'curp'      => $curp,
            ];
        }

        return $registros;
    }

    /**
     * Extrae el número de lote del nombre del archivo
     * Ejemplo: Recibo_E292977432_Lote_417105637.pdf → "417105637"
     */
    public static function extraerNumeroLote($nombreArchivo) {
        if (preg_match('/[Ll]ote[_\s-](\d+)/i', $nombreArchivo, $m)) {
            return $m[1];
        }
        return pathinfo($nombreArchivo, PATHINFO_FILENAME);
    }

    /**
     * Detecta el tipo de movimiento (alta/baja) del texto extraído.
     *
     * Estrategia 1 — keywords en metadatos/encabezado del PDF.
     * Estrategia 2 — código de operación dentro de las líneas de datos IMSS:
     *   Alta: "...UMF001 08 folio..." → patrón "\d{3}001 08 "
     *   Baja: "...UMF000 02XX..."    → patrón "\d{3}000 02" o "0229"
     * Se cuentan ocurrencias para ser robusto ante texto parcialmente extraído.
     */
    public static function detectarTipo($texto) {
        $upper = mb_strtoupper($texto, 'UTF-8');

        // Estrategia 1: keywords explícitos
        foreach (['ALTA', 'INSCRIPCION', 'INSCR'] as $ind) {
            if (strpos($upper, $ind) !== false) return 'alta';
        }
        foreach (['BAJA', 'CANCELACION'] as $ind) {
            if (strpos($upper, $ind) !== false) return 'baja';
        }

        // Estrategia 2: código de operación en líneas de datos IMSS
        // Alta: UMF '001' seguido de código '08' → "001 08 "
        $cntAlta = preg_match_all('/001\s+08\s/', $texto);
        // Baja: UMF '000' seguido de código '02' → "000 02" o la secuencia "0229"
        $cntBaja = preg_match_all('/000\s+02[\s\d]/', $texto)
                 + preg_match_all('/0229/', $texto);

        if ($cntAlta > 0 && $cntAlta >= $cntBaja) return 'alta';
        if ($cntBaja > 0) return 'baja';

        return null;
    }

    /**
     * Extrae la fecha del acuse IMSS del texto del PDF.
     *
     * Prioridad:
     *  1. "Fecha acuse:"  dd/mm/yyyy  — la fecha oficial del comprobante IMSS
     *  2. "Fecha recepción/movimiento/proceso:" dd/mm/yyyy
     *  3. Cualquier "Fecha:" dd/mm/yyyy en el cuerpo
     *  4. Primera dd/mm/yyyy encontrada en el cuerpo
     *  5. Metadatos PDF (D:YYYYMMDD) — último recurso (es la fecha de creación del archivo,
     *     no la del movimiento IMSS)
     */
    public static function extraerFecha($texto) {
        // 1. "Fecha acuse: 11/09/2025" — la más específica y confiable
        if (preg_match('/fecha\s+acuse\s*:?\s*(\d{2})[\/\-](\d{2})[\/\-](\d{4})/i', $texto, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }

        // 2. "Fecha recepción / movimiento / proceso / emisión / vigencia" — formato dd/mm/yyyy
        if (preg_match('/fecha\s+(?:de\s+)?(?:recepci[oó]n|movimiento|proceso|emisi[oó]n|vigencia)\s*:?\s*(\d{2})[\/\-](\d{2})[\/\-](\d{4})/i', $texto, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }

        // 2b. Mismo campo pero en formato ISO yyyy-mm-dd (PDFs "IMSS Desde su Empresa")
        //     "Fecha y hora de recepción del lote: 2025-09-11 18:05"
        if (preg_match('/fecha\s+(?:y\s+hora\s+(?:de\s+)?)?(?:recepci[oó]n|movimiento|proceso|emisi[oó]n|vigencia)(?:\s+del?\s+\w+)?\s*:?\s*(\d{4})-(\d{2})-(\d{2})/i', $texto, $m)) {
            return $m[1] . '-' . $m[2] . '-' . $m[3];
        }

        // 3. Cualquier "Fecha:" seguida de dd/mm/yyyy en el cuerpo
        if (preg_match('/fecha\s*:?\s*(\d{2})[\/\-](\d{2})[\/\-](\d{4})/i', $texto, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }

        // 4. Primera dd/mm/yyyy o dd-mm-yyyy en el cuerpo del documento
        if (preg_match('/(\d{2})[\/\-](\d{2})[\/\-](\d{4})/', $texto, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }

        // 4b. Primera fecha ISO yyyy-mm-dd válida (año 2000-2099) — fallback para este tipo de PDF
        if (preg_match('/(20\d{2})-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])/', $texto, $m)) {
            return $m[1] . '-' . $m[2] . '-' . $m[3];
        }

        // 5. Metadatos PDF: D:YYYYMMDDHHmmSS (fecha de creación del archivo, no del movimiento)
        if (preg_match('/D:(\d{4})(\d{2})(\d{2})/', $texto, $m)) {
            return $m[1] . '-' . $m[2] . '-' . $m[3];
        }

        return null;
    }

    /**
     * Extrae todos los NSS del texto.
     *
     * En los PDFs del IMSS el NSS aparece pegado al registro patronal:
     *   E292977432 + 1(DV) + 69200528151(NSS) → "E292977432169200528151"
     *
     * @param string      $texto          Texto extraído del PDF
     * @param string|null $patronalConDV  Registro patronal completo con DV (ej. "E2929774321")
     */
    public static function extraerNSS($texto, $patronalConDV = null) {
        $encontrados = [];

        // 1) Patrón específico con el patronal conocido: E2929774321 + NSS(11)
        if ($patronalConDV) {
            $pat = preg_quote($patronalConDV, '/');
            if (preg_match_all('/' . $pat . '(\d{11})/', $texto, $m)) {
                foreach ($m[1] as $nss) $encontrados[$nss] = $nss;
            }
        }

        // 2) Patrón genérico: 1 letra + 9 alfanum + 1 dígito(DV) + 11 dígitos(NSS)
        //    Cubre cualquier registro patronal sin necesitar conocerlo de antemano
        if (preg_match_all('/[A-Z][A-Z0-9]{9}\d(\d{11})/', $texto, $m)) {
            foreach ($m[1] as $nss) {
                if (!isset($encontrados[$nss])) $encontrados[$nss] = $nss;
            }
        }

        // 3) Fallback: NSS aislado de 11 dígitos (no adyacente a otros dígitos)
        if (preg_match_all('/(?<!\d)(\d{11})(?!\d)/', $texto, $m)) {
            foreach ($m[1] as $nss) {
                if (!isset($encontrados[$nss])) $encontrados[$nss] = $nss;
            }
        }

        return array_values($encontrados);
    }

    /**
     * Extrae NSS con conteo de apariciones para detectar duplicados en el PDF.
     *
     * El patrón primario (patronal específico o genérico) cuenta todas las ocurrencias
     * antes de deduplicar. Los patrones de fallback solo agregan NSS nuevos (count = 1).
     *
     * @return array {
     *   'nss'         => string[],  // lista única de NSS
     *   'total_bruto' => int,       // total de apariciones en el PDF (incluye duplicados)
     *   'duplicados'  => array[],   // [['nss' => '...', 'veces' => N], ...]
     * }
     */
    public static function extraerNSSDetallado($texto, $patronalConDV = null) {
        // $primario: conteo por NSS del patrón autoritativo (fuente del total_bruto)
        $primario = [];
        // $extra: NSS adicionales encontrados solo por patrones de respaldo
        $extra    = [];

        // 1) Patrón específico — autoritativo para total_bruto, cuenta TODAS las ocurrencias
        if ($patronalConDV) {
            $pat = preg_quote($patronalConDV, '/');
            if (preg_match_all('/' . $pat . '(\d{11})/', $texto, $m)) {
                foreach ($m[1] as $nss) {
                    $primario[$nss] = ($primario[$nss] ?? 0) + 1;
                }
            }
        }

        if (empty($primario)) {
            // Sin patronal conocido: patrón genérico actúa como primario
            if (preg_match_all('/[A-Z][A-Z0-9]{9}\d(\d{11})/', $texto, $m)) {
                foreach ($m[1] as $nss) {
                    $primario[$nss] = ($primario[$nss] ?? 0) + 1;
                }
            }
        } else {
            // Patrón genérico solo agrega NSS nuevos al extra
            if (preg_match_all('/[A-Z][A-Z0-9]{9}\d(\d{11})/', $texto, $m)) {
                foreach ($m[1] as $nss) {
                    if (!isset($primario[$nss]) && !isset($extra[$nss])) {
                        $extra[$nss] = 1;
                    }
                }
            }
        }

        // Fallback: solo agrega NSS no encontrados por ningún patrón anterior
        if (preg_match_all('/(?<!\d)(\d{11})(?!\d)/', $texto, $m)) {
            foreach ($m[1] as $nss) {
                if (!isset($primario[$nss]) && !isset($extra[$nss])) {
                    $extra[$nss] = 1;
                }
            }
        }

        // total_bruto usa solo el patrón primario → coincide con el conteo real del PDF
        $totalBruto = array_sum($primario);

        $duplicados = [];
        foreach ($primario as $nss => $veces) {
            if ($veces > 1) {
                $duplicados[] = ['nss' => $nss, 'veces' => $veces];
            }
        }

        return [
            'nss'         => array_merge(array_keys($primario), array_keys($extra)),
            'total_bruto' => $totalBruto,
            'duplicados'  => $duplicados,
        ];
    }
}
