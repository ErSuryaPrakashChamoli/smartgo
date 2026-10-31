<?php

namespace App\Services\Inventory\Import;

use DateInterval;
use DateTimeInterface;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;

/**
 * Reads an SG-INV workbook (INVENTORY_IMPORT_SPEC §2) into plain rows: for each sheet, the column keys of
 * row 1 and the data rows from row 4, keyed by their row number, with empty rows left out. Dates come back
 * as DateTimeInterface; text is trimmed and empty cells are null. Uses `openspout/openspout`, which is
 * installed as a requirement of Filament; declaring it as a direct dependency (D-24, ADR-0012) waits for
 * the owner's specific authorisation.
 */
class WorkbookReader
{
    public const int FIRST_DATA_ROW = 4;

    /**
     * @return array{sheets: array<string, array{headers: list<string>, rows: array<int, list<mixed>>}>, meta: array<string, string>}|null null when the file is not a readable XLSX workbook
     */
    public function read(string $path): ?array
    {
        $options = new Options;
        $options->SHOULD_FORMAT_DATES = false;
        $reader = new Reader($options);

        try {
            $reader->open($path);
            $sheets = [];
            $meta = [];

            foreach ($reader->getSheetIterator() as $sheet) {
                $headers = [];
                $rows = [];

                foreach ($sheet->getRowIterator() as $number => $row) {
                    $cells = array_map($this->cell(...), $row->toArray());

                    if ($sheet->getName() === '_meta') {
                        if (filled($cells[0] ?? null)) {
                            $meta[(string) $cells[0]] = (string) ($cells[1] ?? '');
                        }

                        continue;
                    }

                    if ($number === 1) {
                        $headers = array_map(fn (mixed $cell): string => (string) $cell, $cells);
                    } elseif ($number >= self::FIRST_DATA_ROW && collect($cells)->contains(fn (mixed $cell): bool => $cell !== null)) {
                        $rows[$number] = $cells;
                    }
                }

                $sheets[$sheet->getName()] = ['headers' => $headers, 'rows' => $rows];
            }

            return ['sheets' => $sheets, 'meta' => $meta];
        } catch (Throwable) {
            return null;
        } finally {
            $reader->close();
        }
    }

    private function cell(mixed $value): mixed
    {
        return match (true) {
            $value instanceof DateTimeInterface => $value,
            $value instanceof DateInterval => sprintf('%02d:%02d', $value->h + $value->d * 24, $value->i),
            is_string($value) => trim($value) === '' ? null : trim($value),
            default => $value,
        };
    }
}
