<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MasterDataController extends Controller
{
    private array $masters = [
        'suppliers' => [
            'title' => 'Supplier',
            'table' => 'suppliers',
            'code_field' => 'code',
            'description' => 'Data vendor untuk proses pembelian dan penerimaan barang.',
            'columns' => ['code' => 'Kode', 'name' => 'Nama', 'contact_person' => 'Kontak', 'phone' => 'Telepon', 'email' => 'Email'],
            'fields' => [
                'code' => ['label' => 'Kode Supplier', 'type' => 'text', 'required' => true],
                'name' => ['label' => 'Nama Supplier', 'type' => 'text', 'required' => true],
                'contact_person' => ['label' => 'Kontak Person', 'type' => 'text'],
                'phone' => ['label' => 'Telepon', 'type' => 'text'],
                'email' => ['label' => 'Email', 'type' => 'email'],
                'payment_terms_days' => ['label' => 'Termin Pembayaran (hari)', 'type' => 'number', 'default' => 0],
            ],
        ],
        'item-categories' => [
            'title' => 'Kategori Item',
            'table' => 'item_categories',
            'code_field' => 'code',
            'description' => 'Pengelompokan barang untuk inventory dan purchasing.',
            'columns' => ['code' => 'Kode', 'name' => 'Nama'],
            'fields' => [
                'code' => ['label' => 'Kode Kategori', 'type' => 'text', 'required' => true],
                'name' => ['label' => 'Nama Kategori', 'type' => 'text', 'required' => true],
            ],
        ],
        'units' => [
            'title' => 'Satuan',
            'table' => 'units',
            'code_field' => 'code',
            'description' => 'Satuan dasar dan satuan pembelian barang.',
            'columns' => ['code' => 'Kode', 'name' => 'Nama', 'decimal_places' => 'Desimal'],
            'fields' => [
                'code' => ['label' => 'Kode Satuan', 'type' => 'text', 'required' => true],
                'name' => ['label' => 'Nama Satuan', 'type' => 'text', 'required' => true],
                'decimal_places' => ['label' => 'Jumlah Desimal', 'type' => 'number', 'default' => 2],
            ],
        ],
        'storage-locations' => [
            'title' => 'Gudang / Lokasi',
            'table' => 'storage_locations',
            'code_field' => 'code',
            'description' => 'Lokasi penyimpanan stok per cabang.',
            'columns' => ['code' => 'Kode', 'name' => 'Nama', 'type' => 'Tipe', 'allow_negative_stock' => 'Stok Minus'],
            'fields' => [
                'code' => ['label' => 'Kode Lokasi', 'type' => 'text', 'required' => true],
                'name' => ['label' => 'Nama Lokasi', 'type' => 'text', 'required' => true],
                'type' => ['label' => 'Tipe', 'type' => 'select', 'options' => ['warehouse' => 'Warehouse', 'store' => 'Store', 'kitchen' => 'Kitchen', 'outlet' => 'Outlet'], 'default' => 'warehouse'],
                'allow_negative_stock' => ['label' => 'Izinkan Stok Minus', 'type' => 'checkbox'],
            ],
        ],
        'items' => [
            'title' => 'Item',
            'table' => 'items',
            'code_field' => 'sku',
            'description' => 'Master barang/jasa untuk pembelian, budget, dan stok.',
            'columns' => ['sku' => 'SKU', 'name' => 'Nama', 'item_type' => 'Tipe', 'minimum_stock' => 'Min Stok', 'standard_cost' => 'Harga Standar'],
            'fields' => [
                'sku' => ['label' => 'SKU', 'type' => 'text', 'required' => true],
                'name' => ['label' => 'Nama Item', 'type' => 'text', 'required' => true],
                'item_category_id' => ['label' => 'Kategori', 'type' => 'select', 'source' => 'item_categories'],
                'base_unit_id' => ['label' => 'Satuan Dasar', 'type' => 'select', 'source' => 'units', 'required' => true],
                'item_type' => ['label' => 'Tipe Item', 'type' => 'select', 'options' => ['inventory' => 'Inventory', 'asset' => 'Asset', 'service' => 'Service'], 'default' => 'inventory'],
                'minimum_stock' => ['label' => 'Minimum Stok', 'type' => 'number', 'default' => 0],
                'maximum_stock' => ['label' => 'Maximum Stok', 'type' => 'number'],
                'standard_cost' => ['label' => 'Harga Standar', 'type' => 'number', 'default' => 0],
                'description' => ['label' => 'Deskripsi', 'type' => 'textarea'],
            ],
        ],
    ];

    public function home(): View
    {
        $cards = collect($this->masters)
            ->map(function (array $config, string $key) {
                $count = DB::table($config['table'])
                    ->when($this->hasCompanyColumn($config['table']), fn ($query) => $query->where('company_id', $this->company()->id))
                    ->when($this->deletedAtColumn($config['table']) !== null, fn ($query) => $query->whereNull($this->deletedAtColumn($config['table'])))
                    ->count();

                return [
                    'key' => $key,
                    'title' => $config['title'],
                    'description' => $config['description'],
                    'count' => $count,
                ];
            })
            ->values();

        return view('master.home', compact('cards'));
    }

    public function index(string $master): View
    {
        $config = $this->masterConfig($master);
        $rows = DB::table($config['table'])
            ->when($this->hasCompanyColumn($config['table']), fn ($query) => $query->where('company_id', $this->company()->id))
            ->when($this->deletedAtColumn($config['table']) !== null, fn ($query) => $query->whereNull($this->deletedAtColumn($config['table'])))
            ->orderBy($config['code_field'])
            ->paginate(10);

        return view('master.index', compact('master', 'config', 'rows'));
    }

    public function create(string $master): View
    {
        $config = $this->masterConfig($master);
        $row = null;
        $options = $this->options();

        return view('master.form', compact('master', 'config', 'row', 'options'));
    }

    public function store(Request $request, string $master): RedirectResponse
    {
        $config = $this->masterConfig($master);
        $payload = $this->validatedPayload($request, $config);

        DB::table($config['table'])->insert($payload + [
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('master.index', $master)
            ->with('status', $config['title'].' berhasil ditambahkan.');
    }

    public function edit(string $master, int $id): View
    {
        $config = $this->masterConfig($master);
        $row = $this->findRow($config, $id);
        $options = $this->options();

        return view('master.form', compact('master', 'config', 'row', 'options'));
    }

    public function update(Request $request, string $master, int $id): RedirectResponse
    {
        $config = $this->masterConfig($master);
        $this->findRow($config, $id);

        $payload = $this->validatedPayload($request, $config, $id);
        $payload['updated_at'] = now();

        DB::table($config['table'])->where('id', $id)->update($payload);

        return redirect()
            ->route('master.index', $master)
            ->with('status', $config['title'].' berhasil diperbarui.');
    }

    public function destroy(string $master, int $id): RedirectResponse
    {
        $config = $this->masterConfig($master);
        $this->findRow($config, $id);

        if ($this->deletedAtColumn($config['table']) !== null) {
            DB::table($config['table'])->where('id', $id)->update([
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table($config['table'])->where('id', $id)->delete();
        }

        return redirect()
            ->route('master.index', $master)
            ->with('status', $config['title'].' berhasil dihapus.');
    }

    private function validatedPayload(Request $request, array $config, ?int $id = null): array
    {
        $company = $this->company();
        $branch = $this->branch();
        $rules = [];

        foreach ($config['fields'] as $field => $fieldConfig) {
            $fieldRules = [];
            $fieldRules[] = ($fieldConfig['required'] ?? false) ? 'required' : 'nullable';

            $typeRules = match ($fieldConfig['type']) {
                'email' => ['email'],
                'number' => ['numeric', 'min:0'],
                'checkbox' => ['boolean'],
                'select' => [isset($fieldConfig['source']) ? 'integer' : 'string'],
                default => ['string', 'max:255'],
            };

            $fieldRules = [...$fieldRules, ...$typeRules];

            if ($field === $config['code_field']) {
                $fieldRules[] = Rule::unique($config['table'], $field)
                    ->when($this->hasCompanyColumn($config['table']), fn ($rule) => $rule->where('company_id', $company->id))
                    ->when($config['table'] === 'storage_locations', fn ($rule) => $rule->where('branch_id', $branch->id))
                    ->ignore($id);
            }

            if ($fieldConfig['type'] === 'select' && isset($fieldConfig['options'])) {
                $fieldRules[] = Rule::in(array_keys($fieldConfig['options']));
            }

            if ($fieldConfig['type'] === 'select' && isset($fieldConfig['source'])) {
                $fieldRules[] = Rule::exists($fieldConfig['source'], 'id')
                    ->where('company_id', $company->id);
            }

            $rules[$field] = $fieldRules;
        }

        $validated = $request->validate($rules);

        foreach ($config['fields'] as $field => $fieldConfig) {
            if ($fieldConfig['type'] === 'checkbox') {
                $validated[$field] = $request->boolean($field);
            }

            if (($fieldConfig['type'] ?? null) === 'select' && ($validated[$field] ?? null) === '') {
                $validated[$field] = null;
            }

            if (! array_key_exists($field, $validated) && array_key_exists('default', $fieldConfig)) {
                $validated[$field] = $fieldConfig['default'];
            }
        }

        if ($this->hasCompanyColumn($config['table'])) {
            $validated['company_id'] = $company->id;
        }

        if ($config['table'] === 'storage_locations') {
            $validated['branch_id'] = $branch->id;
        }

        if (in_array($config['table'], ['suppliers', 'items'], true) && $id === null) {
            $validated['public_id'] = (string) Str::uuid();
        }

        if ($config['table'] === 'items') {
            $validated['barcode'] = null;
        }

        $validated['is_active'] = true;

        return $validated;
    }

    private function masterConfig(string $master): array
    {
        abort_unless(array_key_exists($master, $this->masters), 404);

        return $this->masters[$master];
    }

    private function findRow(array $config, int $id): object
    {
        $row = DB::table($config['table'])
            ->when($this->hasCompanyColumn($config['table']), fn ($query) => $query->where('company_id', $this->company()->id))
            ->where('id', $id)
            ->first();

        abort_unless($row, 404);

        return $row;
    }

    private function options(): array
    {
        $company = $this->company();

        return [
            'item_categories' => DB::table('item_categories')
                ->where('company_id', $company->id)
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray(),
            'units' => DB::table('units')
                ->where('company_id', $company->id)
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray(),
        ];
    }

    private function company(): object
    {
        return DB::table('companies')->where('is_active', true)->orderBy('id')->firstOrFail();
    }

    private function branch(): object
    {
        return DB::table('branches')->where('is_active', true)->orderBy('id')->firstOrFail();
    }

    private function hasCompanyColumn(string $table): bool
    {
        return in_array($table, ['suppliers', 'item_categories', 'units', 'storage_locations', 'items'], true);
    }

    private function deletedAtColumn(string $table): ?string
    {
        return in_array($table, ['suppliers', 'item_categories', 'storage_locations', 'items'], true) ? 'deleted_at' : null;
    }
}
