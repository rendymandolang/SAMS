<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Smalot\PdfParser\Parser;

class SupplierCatalogScanner
{
    private array $aliases = [
        'sku' => ['sku', 'code', 'kode', 'item code', 'product code'], 'name' => ['name', 'nama', 'item', 'product', 'product name', 'description', 'deskripsi'],
        'price' => ['price', 'harga', 'unit price', 'harga jual', 'retail price', 'wholesale price'], 'unit' => ['unit', 'satuan', 'size', 'pack', 'pack size', 'kemasan', 'uom'],
        'brand' => ['brand', 'merk', 'merek'], 'category' => ['category', 'kategori', 'department'], 'moq' => ['moq', 'minimum order', 'minimum qty'], 'stock' => ['stock', 'stok', 'availability'],
    ];

    public function scan(string $path, string $extension): array
    {
        return $extension === 'pdf' ? $this->pdf($path) : $this->spreadsheet($path);
    }

    private function spreadsheet(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $rows = $reader->load($path)->getActiveSheet()->toArray(null, true, true, false);
        [$headerIndex, $map] = $this->detectHeader($rows);
        if ($headerIndex === null) {
            throw new RuntimeException('Header nama produk dan harga tidak ditemukan.');
        }
        $result = [];
        foreach (array_slice($rows, $headerIndex + 1, 5000, true) as $index => $row) {
            $item = $this->mapRow($row, $map, $index + 1);
            if ($item) {
                $result[] = $item;
            }
        }

        return ['items' => $result, 'summary' => ['format' => 'spreadsheet', 'header_row' => $headerIndex + 1, 'rows_scanned' => count($rows), 'requires_ocr' => false]];
    }

    private function pdf(string $path): array
    {
        $text = (new Parser)->parseFile($path)->getText();
        $items = [];
        $lineNo = 0;
        $previousText = '';
        foreach (preg_split('/\R/u', $text) as $line) {
            $lineNo++;
            $line = trim(preg_replace('/\s+/u', ' ', $line));
            if ($line === '') {
                continue;
            }
            if (preg_match('/^(?:Rp\.?\s*)?([0-9][0-9\.\,]{2,})\s*$/iu', $line, $m) && mb_strlen($previousText) >= 2) {
                $name = $previousText;
                $price = $this->number($m[1]);
            } elseif (preg_match('/^(.*?)\s+(?:Rp\.?\s*)?([0-9][0-9\.\,]{2,})\s*$/iu', $line, $m) && mb_strlen(trim($m[1])) >= 2) {
                $name = trim($m[1]);
                $price = $this->number($m[2]);
            } else {
                $previousText = $line;

                continue;
            }
            if ($price > 0) {
                $items[] = $this->build(['name' => $name, 'price' => $price, 'unit' => null, 'source_row' => $lineNo, 'raw' => ['line' => $line]], .55);
            }$previousText = '';
        }

        return ['items' => $items, 'summary' => ['format' => 'pdf', 'text_length' => mb_strlen($text), 'requires_ocr' => mb_strlen(trim($text)) < 50, 'rows_scanned' => $lineNo]];
    }

    private function detectHeader(array $rows): array
    {
        foreach (array_slice($rows, 0, 10, true) as $i => $row) {
            $map = [];
            foreach ($row as $col => $header) {
                $h = mb_strtolower(trim((string) $header));
                foreach ($this->aliases as $field => $aliases) {
                    if (in_array($h, $aliases, true)) {
                        $map[$field] = $col;
                    }
                }
            }if (isset($map['name'],$map['price'])) {
                return [$i, $map];
            }
        }

return [null, []];
    }

    private function mapRow(array $row, array $map, int $sourceRow): ?array
    {
        $name = trim((string) ($row[$map['name']] ?? ''));
        $price = $this->number($row[$map['price']] ?? 0);
        if ($name === '' || $price <= 0) {
            return null;
        }
        $data = [];
        foreach ($map as $field => $col) {
            $data[$field] = $row[$col] ?? null;
        }$data['name'] = $name;
        $data['price'] = $price;
        $data['source_row'] = $sourceRow;
        $data['raw'] = $data;

        return $this->build($data, .85);
    }

    private function build(array $data, float $confidence): array
    {
        $unit = app(CatalogUnitNormalizer::class)->parse(isset($data['unit']) ? (string) $data['unit'] : null, $data['name']);
        $normalized = max(.000001, $unit['normalized_quantity']);

        return ['source_sku' => filled($data['sku'] ?? null) ? trim((string) $data['sku']) : null, 'source_name' => $data['name'], 'brand' => $data['brand'] ?? null, 'category' => $data['category'] ?? null, 'description' => null, ...$unit, 'price' => (float) $data['price'], 'normalized_unit_price' => (float) $data['price'] / $normalized, 'minimum_order_quantity' => max(1, $this->number($data['moq'] ?? 1)), 'stock_status' => $data['stock'] ?? null, 'source_row' => $data['source_row'] ?? null, 'confidence' => $confidence, 'raw_data' => $data['raw'] ?? $data];
    }

    private function number(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        $v = preg_replace('/[^0-9,\.\-]/', '', (string) $value);
        if (str_contains($v, '.') && str_contains($v, ',')) {
            $v = str_replace('.', '', $v);
            $v = str_replace(',', '.', $v);
        } elseif (preg_match('/^[\-]?\d{1,3}(?:\.\d{3})+$/', $v)) {
            $v = str_replace('.', '', $v);
        } elseif (preg_match('/^[\-]?\d{1,3}(?:,\d{3})+$/', $v)) {
            $v = str_replace(',','',$v);
        } else {
            $v = str_replace(',','.',$v);
        }

        return (float) $v;
    }
}
