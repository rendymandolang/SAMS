<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SupplierCatalogScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SupplierCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_catalog_is_scanned_reviewed_published_and_compared_by_normalized_unit(): void
    {
        Storage::fake('local');
        $this->seed();
        $user = User::query()->where('email', 'admin@sams.local')->firstOrFail();
        $supplier = DB::table('suppliers')->firstOrFail();
        $csv = "SKU,Nama,Harga,Kemasan,Brand,Kategori\nSAL-500,Salmon Fillet Fresh 500 gr,200000,500 gr,Ocean Test,Seafood\nPAPER-A4,Kertas A4 80gsm,65000,1 box,Office Test,ATK\n";
        $response = $this->actingAs($user)->post('/supplier-catalogs', ['supplier_id' => $supplier->id, 'name' => 'Mixed Catalog', 'currency' => 'IDR', 'catalog_file' => UploadedFile::fake()->createWithContent('catalog.csv', $csv)]);
        $catalog = DB::table('supplier_catalogs')->firstOrFail();
        $response->assertRedirect('/supplier-catalogs/'.$catalog->id);
        $this->assertDatabaseHas('supplier_catalogs', ['id' => $catalog->id, 'status' => 'scanned', 'row_count' => 2]);
        $salmon = DB::table('supplier_catalog_items')->where('source_sku', 'SAL-500')->firstOrFail();
        $this->assertSame('KG', $salmon->normalized_unit);
        $this->assertEquals(.5, (float) $salmon->normalized_quantity);
        $this->assertEquals(400000, (float) $salmon->normalized_unit_price);
        $this->actingAs($user)->post('/supplier-catalogs/'.$catalog->id.'/publish')->assertRedirect();
        $this->actingAs($user)->post('/supplier-catalogs/compare', ['query' => 'salmon fillet', 'quantity' => 10, 'unit' => 'KG', 'budget' => 4500000])->assertRedirect();
        $run = DB::table('supplier_comparison_runs')->firstOrFail();
        $result = json_decode($run->results, true)[0];
        $summary = json_decode($run->summary, true);
        $this->assertEquals(4000000, $result['total_cost']);
        $this->assertTrue($result['within_budget']);
        $this->assertSame($supplier->name, $summary['recommended_supplier']);
        $this->assertEquals(500000, $summary['budget_remaining']);
        $this->actingAs($user)->get('/supplier-catalogs?comparison='.$run->id)->assertOk()->assertSee('Supplier Budget AI')->assertSee('Riwayat Analisis Budget');
        $this->actingAs($user)->post('/supplier-catalogs/comparisons/'.$run->id.'/decide', ['catalog_item_id' => $result['catalog_item_id'], 'decision_reason' => 'Harga terbaik dan sesuai budget'])->assertRedirect();
        $this->assertDatabaseHas('supplier_comparison_runs', ['id' => $run->id, 'status' => 'selected', 'selected_supplier_id' => $supplier->id, 'selected_catalog_item_id' => $result['catalog_item_id']]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'supplier_catalog_compared', 'auditable_id' => $run->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'supplier_recommendation_selected', 'auditable_id' => $run->id]);
    }

    public function test_xlsx_scanner_supports_generic_furniture_and_atk_columns(): void
    {
        $sheet = new Spreadsheet;
        $sheet->getActiveSheet()->fromArray([['Product Code', 'Product Name', 'Retail Price', 'Pack Size', 'Brand', 'Category'], ['CHR-01', 'Kursi Kantor Ergonomic', 1750000, '1 pcs', 'Furniture Test', 'Furniture'], ['PEN-12', 'Pulpen Biru 12 pcs', 48000, '12 pcs', 'Office Test', 'ATK']]);
        $path = tempnam(sys_get_temp_dir(), 'sams-catalog-').'.xlsx';
        (new Xlsx($sheet))->save($path);
        $scan = app(SupplierCatalogScanner::class)->scan($path, 'xlsx');
        @unlink($path);
        $this->assertCount(2, $scan['items']);
        $this->assertSame('PCS', $scan['items'][0]['normalized_unit']);
        $this->assertEquals(1750000, $scan['items'][0]['normalized_unit_price']);
        $this->assertEquals(4000, $scan['items'][1]['normalized_unit_price']);
    }

    public function test_unpublished_catalog_is_not_used_and_staff_cannot_upload(): void
    {
        Storage::fake('local');
        $this->seed();
        $staff = User::query()->where('email', 'staff@sams.local')->firstOrFail();
        $supplier = DB::table('suppliers')->firstOrFail();
        $this->actingAs($staff)->post('/supplier-catalogs', ['supplier_id' => $supplier->id, 'name' => 'Blocked', 'currency' => 'IDR', 'catalog_file' => UploadedFile::fake()->createWithContent('catalog.csv', "Nama,Harga\nMeja,100000")])->assertForbidden();
        $this->assertDatabaseCount('supplier_catalogs', 0);
    }

    public function test_text_pdf_scanner_reads_inline_and_separate_price_lines(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'sams-pdf-').'.pdf';
        file_put_contents($path, $this->simplePdf(['Salmon Fillet Fresh 500 gr Rp 200.000', 'Kursi Kantor Ergonomic 1 pcs', 'Rp 1.750.000']));
        $scan = app(SupplierCatalogScanner::class)->scan($path, 'pdf');
        @unlink($path);
        $this->assertCount(2, $scan['items']);
        $this->assertSame('Salmon Fillet Fresh 500 gr', $scan['items'][0]['source_name']);
        $this->assertEquals(400000, $scan['items'][0]['normalized_unit_price']);
        $this->assertSame('Kursi Kantor Ergonomic 1 pcs', $scan['items'][1]['source_name']);
        $this->assertEquals(1750000, $scan['items'][1]['normalized_unit_price']);
        $this->assertFalse($scan['summary']['requires_ocr']);
    }

    private function simplePdf(array $lines): string
    {
        $content = 'BT /F1 12 Tf 72 760 Td ';
        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $content .= '0 -22 Td ';
            }$content .= '('.str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line).') Tj ';
        }$content .= 'ET';
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>', 3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>', 4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>', 5 => '<< /Length '.strlen($content).' >>'."\nstream\n{$content}\nendstream"];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$object}\nendobj\n";
        }$xref = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        for ($i = 1; $i <= 5; $i++) {
            $pdf .= sprintf("%010d 00000 n \n",$offsets[$i]);
        }

return $pdf."trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }
}
