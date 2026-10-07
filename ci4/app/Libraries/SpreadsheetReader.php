<?php

namespace App\Libraries;

/**
 * Reads the first sheet of a .csv or .xlsx upload into rows of strings.
 *
 * .xlsx is a zip of XML files. The PHP zip extension is not always installed
 * (it is not on every XAMPP / shared host), so this reads the zip's central
 * directory itself and inflates the two parts it needs with gzinflate().
 * Formulas are not evaluated - the cached value Excel stored is used - and
 * only the first worksheet is read.
 */
class SpreadsheetReader
{
    private const MAX_ROWS = 5000;

    /**
     * @return list<list<string>> raw rows, header row included
     * @throws \RuntimeException when the file cannot be read as csv/xlsx
     */
    public function read(string $path, string $extension): array
    {
        $extension = strtolower($extension);

        $rows = match ($extension) {
            'csv', 'txt' => $this->readCsv($path),
            'xlsx'       => $this->readXlsx($path),
            default      => throw new \RuntimeException('Only .csv and .xlsx files can be imported.'),
        };

        if (count($rows) > self::MAX_ROWS + 1) {
            throw new \RuntimeException('That file has more than ' . self::MAX_ROWS . ' rows. Split it into smaller files.');
        }

        return $rows;
    }

    private function readCsv(string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException('The file could not be opened.');
        }

        // Excel saves "CSV UTF-8" with a byte-order mark; strip it so the
        // first header is not "\xEF\xBB\xBFplan_number".
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $rows = [];
        while (($row = fgetcsv($stream, 0, ',', '"', '\\')) !== false) {
            if ($row === [null] || $row === []) {
                continue;
            }
            $rows[] = array_map(static fn ($cell) => trim((string) $cell), $row);
        }
        fclose($stream);

        return $rows;
    }

    private function readXlsx(string $path): array
    {
        $zip = $this->unzip($path);

        $sheetName = isset($zip['xl/worksheets/sheet1.xml']) ? 'xl/worksheets/sheet1.xml' : null;
        if ($sheetName === null) {
            foreach (array_keys($zip) as $name) {
                if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                    $sheetName = $name;
                    break;
                }
            }
        }
        if ($sheetName === null) {
            throw new \RuntimeException('No worksheet was found in that Excel file.');
        }

        $shared = [];
        if (isset($zip['xl/sharedStrings.xml'])) {
            $xml = $this->xml($zip['xl/sharedStrings.xml']);
            foreach ($xml->si as $si) {
                $text = '';
                if (isset($si->t)) {
                    $text = (string) $si->t;
                } else {
                    foreach ($si->r as $run) {
                        $text .= (string) $run->t;
                    }
                }
                $shared[] = $text;
            }
        }

        $sheet = $this->xml($zip[$sheetName]);
        $rows = [];

        foreach ($sheet->sheetData->row ?? [] as $row) {
            $cells = [];
            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                $col = $this->columnIndex($ref);
                $type = (string) $cell['t'];

                if ($type === 'inlineStr') {
                    $value = (string) ($cell->is->t ?? '');
                } else {
                    $value = (string) ($cell->v ?? '');
                    if ($type === 's') {
                        $value = $shared[(int) $value] ?? '';
                    }
                }

                $cells[$col] = trim($value);
            }

            if ($cells === []) {
                continue;
            }

            $line = [];
            for ($i = 0; $i <= max(array_keys($cells)); $i++) {
                $line[] = $cells[$i] ?? '';
            }

            if (implode('', $line) === '') {
                continue;
            }

            $rows[] = $line;
        }

        return $rows;
    }

    /** Excel stores dates as day serial numbers ("45170" = 2023-09-01). */
    public static function excelSerialToDate(string $value): ?string
    {
        if (! preg_match('/^\d{4,6}(\.\d+)?$/', $value)) {
            return null;
        }

        $serial = (int) floor((float) $value);
        if ($serial < 20000 || $serial > 80000) {
            return null;
        }

        return gmdate('Y-m-d', ($serial - 25569) * 86400);
    }

    private function columnIndex(string $cellRef): int
    {
        preg_match('/^[A-Z]+/i', $cellRef, $m);
        $letters = strtoupper($m[0] ?? 'A');
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }

    private function xml(string $content): \SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        // LIBXML_NONET: never fetch anything over the network while parsing.
        $xml = simplexml_load_string($content, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            throw new \RuntimeException('That Excel file is damaged or not a real .xlsx workbook.');
        }

        return $xml;
    }

    /**
     * Minimal zip reader: file name => uncompressed contents, for stored
     * (method 0) and deflated (method 8) entries.
     *
     * @return array<string,string>
     */
    private function unzip(string $path): array
    {
        $data = file_get_contents($path);
        if ($data === false || strlen($data) < 22) {
            throw new \RuntimeException('That file is not a valid .xlsx workbook.');
        }

        $eocd = strrpos($data, "PK\x05\x06");
        if ($eocd === false) {
            throw new \RuntimeException('That file is not a valid .xlsx workbook.');
        }

        $entries = unpack('vcount/Voffset', substr($data, $eocd + 10, 2) . substr($data, $eocd + 16, 4));
        $offset  = $entries['offset'];
        $files   = [];

        for ($i = 0; $i < $entries['count']; $i++) {
            if (substr($data, $offset, 4) !== "PK\x01\x02") {
                break;
            }

            $h = unpack('vmethod/Vcsize/Vusize/vnlen/velen/vclen', substr($data, $offset + 10, 2) . substr($data, $offset + 20, 4) . substr($data, $offset + 24, 4) . substr($data, $offset + 28, 6));
            $localOffset = unpack('V', substr($data, $offset + 42, 4))[1];
            $name = substr($data, $offset + 46, $h['nlen']);
            $offset += 46 + $h['nlen'] + $h['elen'] + $h['clen'];

            // Only the parts a worksheet read needs; also skips anything with
            // an odd path (no traversal is possible - nothing is written to disk).
            if (! in_array($name, ['xl/sharedStrings.xml'], true) && ! preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                continue;
            }

            if (substr($data, $localOffset, 4) !== "PK\x03\x04") {
                continue;
            }

            $local = unpack('vnlen/velen', substr($data, $localOffset + 26, 4));
            $start = $localOffset + 30 + $local['nlen'] + $local['elen'];
            $raw   = substr($data, $start, $h['csize']);

            if ($h['method'] === 0) {
                $files[$name] = $raw;
            } elseif ($h['method'] === 8) {
                $inflated = @gzinflate($raw, 50 * 1024 * 1024);
                if ($inflated === false) {
                    throw new \RuntimeException('That Excel file could not be unpacked.');
                }
                $files[$name] = $inflated;
            }
        }

        return $files;
    }
}
