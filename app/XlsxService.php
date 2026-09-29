<?php
declare(strict_types=1);

final class XlsxService
{
    /** @return array<int,array<int,string>> */
    public function readRows(string $path): array
    {
        if (!is_file($path) || filesize($path) === 0) throw new RuntimeException('El archivo de Excel está vacío o no se pudo leer.');
        if (filesize($path) > 10 * 1024 * 1024) throw new RuntimeException('El archivo excede el límite permitido de 10 MB.');

        $sharedXml = $this->zipEntry($path, 'xl/sharedStrings.xml', false);
        $sheetXml = $this->zipEntry($path, 'xl/worksheets/sheet1.xml', true);
        $shared = $sharedXml !== null ? $this->sharedStrings($sharedXml) : [];
        $rows = [];

        if (!preg_match_all('/<(?:[A-Za-z0-9_]+:)?row\\b[^>]*>(.*?)<\\/(?:[A-Za-z0-9_]+:)?row>/si', $sheetXml, $rowMatches)) {
            throw new RuntimeException('La hoja de Excel no contiene registros para importar.');
        }
        foreach ($rowMatches[1] as $rowXml) {
            $values = [];
            if (!preg_match_all('/<(?:[A-Za-z0-9_]+:)?c\\b([^>]*)>(.*?)<\\/(?:[A-Za-z0-9_]+:)?c>/si', $rowXml, $cells, PREG_SET_ORDER)) continue;
            foreach ($cells as $cell) {
                $attrs = $cell[1];
                $body = $cell[2];
                $ref = $this->attr($attrs, 'r');
                $type = $this->attr($attrs, 't');
                $col = $this->columnIndex($ref);
                if ($col < 0 || $col > 50) continue;
                $value = '';
                if ($type === 'inlineStr') {
                    $value = $this->allText($body);
                } elseif (preg_match('/<(?:[A-Za-z0-9_]+:)?v\\b[^>]*>(.*?)<\\/(?:[A-Za-z0-9_]+:)?v>/si', $body, $m)) {
                    $raw = $this->xmlDecode($m[1]);
                    $value = $type === 's' ? ($shared[(int)$raw] ?? '') : ($type === 'b' ? ($raw === '1' ? '1' : '0') : $raw);
                }
                $values[$col] = trim($value);
            }
            if (!$values) continue;
            $max = max(array_keys($values));
            $dense = array_fill(0, $max + 1, '');
            foreach ($values as $i => $v) $dense[$i] = $v;
            if (count(array_filter($dense, static fn($v) => trim((string)$v) !== '')) > 0) $rows[] = $dense;
        }
        if (!$rows) throw new RuntimeException('La hoja de Excel no contiene registros para importar.');
        return $rows;
    }

    /** @return array<int,string> */
    private function sharedStrings(string $xmlText): array
    {
        $out = [];
        if (!preg_match_all('/<(?:[A-Za-z0-9_]+:)?si\\b[^>]*>(.*?)<\\/(?:[A-Za-z0-9_]+:)?si>/si', $xmlText, $items)) return $out;
        foreach ($items[1] as $item) $out[] = $this->allText($item);
        return $out;
    }

    private function allText(string $xml): string
    {
        $parts = [];
        if (preg_match_all('/<(?:[A-Za-z0-9_]+:)?t\\b[^>]*>(.*?)<\\/(?:[A-Za-z0-9_]+:)?t>/si', $xml, $m)) {
            foreach ($m[1] as $text) $parts[] = $this->xmlDecode($text);
        }
        return implode('', $parts);
    }

    private function attr(string $attrs, string $name): string
    {
        if (preg_match('/(?:^|\\s)'.preg_quote($name, '/').'\\s*=\\s*(["\\\'])(.*?)\\1/si', $attrs, $m)) return $this->xmlDecode($m[2]);
        return '';
    }

    private function xmlDecode(string $value): string
    {
        return html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function columnIndex(string $ref): int
    {
        if (!preg_match('/^([A-Z]+)/i', $ref, $m)) return -1;
        $n = 0;
        foreach (str_split(strtoupper($m[1])) as $ch) $n = $n * 26 + (ord($ch) - 64);
        return $n - 1;
    }

    private function zipEntry(string $path, string $entry, bool $required): ?string
    {
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($path) !== true) throw new RuntimeException('El archivo Excel no es válido.');
            $data = $zip->getFromName($entry);
            $zip->close();
            if ($data === false) {
                if ($required) throw new RuntimeException('El archivo Excel no contiene la hoja esperada.');
                return null;
            }
            return $data;
        }
        return $this->zipEntryWithoutExtension($path, $entry, $required);
    }

    private function zipEntryWithoutExtension(string $path, string $entry, bool $required): ?string
    {
        if (!function_exists('gzinflate')) {
            throw new RuntimeException('El servidor necesita la extensión zlib o ZIP para leer archivos .xlsx.');
        }
        $bytes = file_get_contents($path);
        if ($bytes === false) throw new RuntimeException('No se pudo leer el archivo Excel.');
        $pos = strrpos($bytes, "PK\x05\x06");
        if ($pos === false || strlen($bytes) < $pos + 22) throw new RuntimeException('El archivo no tiene una estructura XLSX válida.');
        $eocd = unpack('vdisk/vdiskStart/ventriesDisk/ventries/VcdSize/VcdOffset', substr($bytes, $pos + 4, 16));
        $offset = (int)($eocd['cdOffset'] ?? 0);
        $limit = $offset + (int)($eocd['cdSize'] ?? 0);
        $total = strlen($bytes);
        while ($offset + 46 <= $total && $offset < $limit) {
            if (substr($bytes, $offset, 4) !== "PK\x01\x02") break;
            $h = unpack('vverMade/vverNeed/vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vusize/vnameLen/vextraLen/vcommentLen/vdisk/vintAttr/VextAttr/VlocalOffset', substr($bytes, $offset + 4, 42));
            $nameLen = (int)$h['nameLen'];
            $extraLen = (int)$h['extraLen'];
            $commentLen = (int)$h['commentLen'];
            $name = substr($bytes, $offset + 46, $nameLen);
            if ($name === $entry) {
                $usize = (int)$h['usize'];
                if ($usize > 25 * 1024 * 1024) throw new RuntimeException('El archivo Excel contiene una hoja demasiado grande.');
                $local = (int)$h['localOffset'];
                if (substr($bytes, $local, 4) !== "PK\x03\x04") throw new RuntimeException('El archivo XLSX está dañado.');
                $lh = unpack('vver/vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vusize/vnameLen/vextraLen', substr($bytes, $local + 4, 26));
                $dataStart = $local + 30 + (int)$lh['nameLen'] + (int)$lh['extraLen'];
                $compressed = substr($bytes, $dataStart, (int)$h['csize']);
                $method = (int)$h['method'];
                if ($method === 0) return $compressed;
                if ($method === 8) {
                    $decoded = @gzinflate($compressed);
                    if ($decoded === false) throw new RuntimeException('No se pudo descomprimir una parte del archivo XLSX.');
                    return $decoded;
                }
                throw new RuntimeException('El método de compresión del XLSX no es compatible.');
            }
            $offset += 46 + $nameLen + $extraLen + $commentLen;
        }
        if ($required) throw new RuntimeException('El archivo Excel no contiene la hoja esperada.');
        return null;
    }
}
