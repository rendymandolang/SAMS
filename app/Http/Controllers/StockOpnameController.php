<?php

namespace App\Http\Controllers;

use App\Support\AuditLogger;
use App\Support\CompanyContext;
use App\Support\DocumentStateMachine;
use App\Support\TransactionPeriodLock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StockOpnameController extends Controller
{
    public function index(): View
    {
        $company = $this->company();

        $stockOpnames = DB::table('stock_opnames')
            ->join('storage_locations', 'storage_locations.id', '=', 'stock_opnames.storage_location_id')
            ->join('users', 'users.id', '=', 'stock_opnames.created_by')
            ->where('stock_opnames.company_id', $company->id)
            ->select(
                'stock_opnames.*',
                'storage_locations.code as location_code',
                'storage_locations.name as location_name',
                'users.name as creator_name',
            )
            ->orderByDesc('stock_opnames.count_date')
            ->orderByDesc('stock_opnames.id')
            ->paginate(10);

        return view('stock_opnames.index', compact('stockOpnames'));
    }

    public function create(Request $request): View
    {
        $company = $this->company();
        $locations = $this->storageLocations();
        $selectedLocationId = $request->integer('storage_location_id') ?: ($locations->first()?->id);
        $selectedLocation = $selectedLocationId
            ? $locations->firstWhere('id', $selectedLocationId)
            : null;

        $balances = $selectedLocation
            ? $this->stockBalances((int) $company->id, (int) $selectedLocation->id)
            : collect();

        return view('stock_opnames.create', compact('locations', 'selectedLocationId', 'selectedLocation', 'balances'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'storage_location_id' => ['required', 'integer', 'exists:storage_locations,id'],
            'count_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.counted_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        $company = $this->company();
        $location = $this->findLocation((int) $validated['storage_location_id']);
        $balances = $this->stockBalances((int) $company->id, (int) $location->id);

        if ($balances->isEmpty()) {
            throw ValidationException::withMessages([
                'storage_location_id' => 'Lokasi ini belum memiliki saldo stok untuk diopname.',
            ]);
        }

        $inputLines = collect($validated['items'])->keyBy(fn (array $line) => (int) $line['item_id']);

        $stockOpnameId = DB::transaction(function () use ($company, $location, $validated, $balances, $inputLines) {
            $now = now();

            $stockOpnameId = DB::table('stock_opnames')->insertGetId([
                'company_id' => $company->id,
                'branch_id' => $location->branch_id,
                'storage_location_id' => $location->id,
                'created_by' => auth()->id(),
                'document_number' => $this->nextDocumentNumber((int) $company->id, (int) $location->branch_id),
                'count_date' => $validated['count_date'],
                'status' => 'draft',
                'notes' => $validated['notes'] ?? null,
                'posted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($balances as $balance) {
                $input = $inputLines->get((int) $balance->item_id, []);
                $countedQuantity = array_key_exists('counted_quantity', $input) && $input['counted_quantity'] !== null
                    ? (float) $input['counted_quantity']
                    : null;
                $varianceQuantity = $countedQuantity === null
                    ? 0
                    : $countedQuantity - (float) $balance->quantity_on_hand;

                DB::table('stock_opname_items')->insert([
                    'stock_opname_id' => $stockOpnameId,
                    'item_id' => $balance->item_id,
                    'system_quantity' => $balance->quantity_on_hand,
                    'counted_quantity' => $countedQuantity,
                    'variance_quantity' => $varianceQuantity,
                    'unit_cost' => $balance->average_cost,
                    'notes' => $input['notes'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            return $stockOpnameId;
        });

        return redirect()
            ->route('stock-opnames.show', $stockOpnameId)
            ->with('status', 'Stock Opname berhasil dibuat sebagai draft.');
    }

    public function show(int $stockOpname): View
    {
        $header = $this->findStockOpname($stockOpname);
        $items = $this->stockOpnameItems((int) $header->id);
        $summary = $this->summary($items);

        return view('stock_opnames.show', compact('header', 'items', 'summary'));
    }

    public function print(int $stockOpname): View
    {
        $header = $this->findStockOpname($stockOpname);
        $items = $this->stockOpnameItems((int) $header->id);
        $summary = $this->summary($items);
        $company = $this->company();
        $branch = DB::table('branches')->where('id', $header->branch_id)->first();

        return view('stock_opnames.print', compact('header', 'items', 'summary', 'company', 'branch'));
    }

    private function stockOpnameItems(int $stockOpnameId)
    {
        return DB::table('stock_opname_items')
            ->join('items', 'items.id', '=', 'stock_opname_items.item_id')
            ->join('units', 'units.id', '=', 'items.base_unit_id')
            ->where('stock_opname_items.stock_opname_id', $stockOpnameId)
            ->select(
                'stock_opname_items.*',
                'items.sku',
                'items.name as item_name',
                'units.code as unit_code',
            )
            ->orderBy('items.name')
            ->get();
    }

    private function summary($items): array
    {
        return [
            'line_count' => $items->count(),
            'variance_value' => $items->sum(fn (object $item) => (float) $item->variance_quantity * (float) $item->unit_cost),
            'positive_variance' => $items->sum(fn (object $item) => max(0, (float) $item->variance_quantity)),
            'negative_variance' => $items->sum(fn (object $item) => abs(min(0, (float) $item->variance_quantity))),
        ];
    }

    public function post(int $stockOpname): RedirectResponse
    {
        $header = $this->findStockOpname($stockOpname);

        $unfilledCount = DB::table('stock_opname_items')
            ->where('stock_opname_id', $header->id)
            ->whereNull('counted_quantity')
            ->count();

        if ($unfilledCount > 0) {
            return redirect()
                ->route('stock-opnames.show', $header->id)
                ->with('status', 'Lengkapi quantity hasil hitung fisik sebelum posting opname.');
        }

        $posted = DB::transaction(function () use ($header): bool {
            $lockedHeader = DB::table('stock_opnames')->where('id', $header->id)->lockForUpdate()->first();
            if (! $lockedHeader || ! DocumentStateMachine::allows('stock_opname', $lockedHeader->status, 'posted')) {
                return false;
            }

            TransactionPeriodLock::ensureOpen((int) $lockedHeader->company_id, 'inventory', $lockedHeader->count_date);

            $now = now();
            $items = DB::table('stock_opname_items')
                ->where('stock_opname_id', $header->id)
                ->get();

            foreach ($items as $item) {
                $varianceQuantity = (float) $item->counted_quantity - (float) $item->system_quantity;

                DB::table('stock_opname_items')->where('id', $item->id)->update([
                    'variance_quantity' => $varianceQuantity,
                    'updated_at' => $now,
                ]);

                if ($varianceQuantity == 0.0) {
                    continue;
                }

                DB::table('stock_movements')->insert([
                        'company_id' => $lockedHeader->company_id,
                        'branch_id' => $lockedHeader->branch_id,
                        'storage_location_id' => $lockedHeader->storage_location_id,
                    'item_id' => $item->item_id,
                    'movement_type' => 'stock_opname_adjustment',
                        'movement_at' => $lockedHeader->count_date,
                    'quantity' => $varianceQuantity,
                    'unit_cost' => $item->unit_cost,
                    'total_cost' => $varianceQuantity * (float) $item->unit_cost,
                        'source_type' => 'stock_opname',
                        'source_id' => $lockedHeader->id,
                        'source_line_id' => $item->id,
                        'created_by' => auth()->id(),
                        'notes' => $lockedHeader->document_number,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('stock_opnames')->where('id', $lockedHeader->id)->update([
                'status' => 'posted',
                'posted_at' => $now,
                'updated_at' => $now,
            ]);

            AuditLogger::log('stock_opname_posted', 'stock_opname', (int) $lockedHeader->id, ['status' => $lockedHeader->status], ['status' => 'posted'], (int) $lockedHeader->company_id);

            return true;
        });

        if (! $posted) {
            return redirect()->route('stock-opnames.show', $header->id)->with('status', 'Hanya Stock Opname draft yang bisa diposting.');
        }

        return redirect()
            ->route('stock-opnames.show', $header->id)
            ->with('status', 'Stock Opname berhasil diposting dan selisih stok sudah masuk sebagai adjustment.');
    }

    private function findStockOpname(int $stockOpname): object
    {
        $company = $this->company();

        $header = DB::table('stock_opnames')
            ->join('storage_locations', 'storage_locations.id', '=', 'stock_opnames.storage_location_id')
            ->join('users', 'users.id', '=', 'stock_opnames.created_by')
            ->where('stock_opnames.company_id', $company->id)
            ->where('stock_opnames.id', $stockOpname)
            ->select(
                'stock_opnames.*',
                'storage_locations.code as location_code',
                'storage_locations.name as location_name',
                'users.name as creator_name',
            )
            ->first();

        abort_unless($header, 404);

        return $header;
    }

    private function stockBalances(int $companyId, int $storageLocationId)
    {
        return DB::table('stock_movements')
            ->join('items', 'items.id', '=', 'stock_movements.item_id')
            ->join('units', 'units.id', '=', 'items.base_unit_id')
            ->where('stock_movements.company_id', $companyId)
            ->where('stock_movements.storage_location_id', $storageLocationId)
            ->select(
                'stock_movements.item_id',
                'items.sku',
                'items.name as item_name',
                'units.code as unit_code',
                DB::raw('SUM(stock_movements.quantity) as quantity_on_hand'),
                DB::raw('SUM(stock_movements.total_cost) as stock_value'),
                DB::raw('CASE WHEN SUM(stock_movements.quantity) = 0 THEN 0 ELSE SUM(stock_movements.total_cost) / SUM(stock_movements.quantity) END as average_cost'),
            )
            ->groupBy('stock_movements.item_id', 'items.sku', 'items.name', 'units.code')
            ->havingRaw('SUM(stock_movements.quantity) <> 0')
            ->orderBy('items.name')
            ->get();
    }

    private function storageLocations()
    {
        $company = $this->company();

        return DB::table('storage_locations')
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();
    }

    private function findLocation(int $storageLocationId): object
    {
        $location = $this->storageLocations()->firstWhere('id', $storageLocationId);

        abort_unless($location, 422);

        return $location;
    }

    private function nextDocumentNumber(int $companyId, int $branchId): string
    {
        $period = now()->format('Ym');
        $sequence = DB::table('document_sequences')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('document_type', 'stock_opname')
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            DB::table('document_sequences')->insert([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'document_type' => 'stock_opname',
                'prefix' => 'SO',
                'next_number' => 1,
                'padding' => 5,
                'period_format' => 'Ym',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('document_sequences')
                ->where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('document_type', 'stock_opname')
                ->lockForUpdate()
                ->first();
        }

        $number = str_pad((string) $sequence->next_number, (int) $sequence->padding, '0', STR_PAD_LEFT);

        DB::table('document_sequences')->where('id', $sequence->id)->update([
            'next_number' => $sequence->next_number + 1,
            'updated_at' => now(),
        ]);

        return "{$sequence->prefix}-{$period}-{$number}";
    }

    private function company(): object
    {
        return app(CompanyContext::class)->current();
    }
}
