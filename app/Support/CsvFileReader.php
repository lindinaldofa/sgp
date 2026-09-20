<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Reads a CSV file line by line with the encoding/format quirks real-world
 * exports actually have — Excel in pt-BR locale defaults to `;` instead of
 * `,`, and often writes a UTF-8 BOM and/or Windows-1252/ISO-8859-1 bytes.
 * Getting any of these wrong doesn't crash the import, it just silently
 * mismaps every column, so this is the single place both the upload step
 * and the processing job go through to stay consistent.
 *
 * Trade-off: reads one physical line per row (not full RFC4180), so a
 * quoted field containing an embedded newline would break — acceptable
 * here since contact fields (name, email, phone, city...) never span lines.
 */
final class CsvFileReader
{
    private const DELIMITER_CANDIDATES = [',', ';', "\t"];

    /** @var resource */
    private $handle;

    private string $delimiter;

    /** @param resource $handle */
    private function __construct($handle, string $delimiter)
    {
        $this->handle = $handle;
        $this->delimiter = $delimiter;
    }

    /** Opens the file, detects its delimiter from the header line, and rewinds. */
    public static function open(string $path): ?self
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return null;
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);

            return null;
        }

        $delimiter = self::detectDelimiter(self::normalizeLine($firstLine));
        rewind($handle);

        return new self($handle, $delimiter);
    }

    public function delimiter(): string
    {
        return $this->delimiter;
    }

    /** @return array<int, string>|null */
    public function readRow(): ?array
    {
        $line = fgets($this->handle);
        if ($line === false) {
            return null;
        }

        $row = str_getcsv(self::normalizeLine($line), $this->delimiter);

        return array_map(fn ($value) => is_string($value) ? trim($value) : $value, $row);
    }

    public function close(): void
    {
        fclose($this->handle);
    }

    private static function detectDelimiter(string $headerLine): string
    {
        $best = ',';
        $bestCount = 0;

        foreach (self::DELIMITER_CANDIDATES as $candidate) {
            $count = substr_count($headerLine, $candidate);
            if ($count > $bestCount) {
                $bestCount = $count;
                $best = $candidate;
            }
        }

        return $best;
    }

    private static function normalizeLine(string $line): string
    {
        // BOM UTF-8, comum em CSVs exportados pelo Excel.
        $line = preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line;

        if (! mb_check_encoding($line, 'UTF-8')) {
            $detected = mb_detect_encoding($line, ['Windows-1252', 'ISO-8859-1', 'UTF-8'], true) ?: 'Windows-1252';
            $line = mb_convert_encoding($line, 'UTF-8', $detected);
        }

        return $line;
    }
}
