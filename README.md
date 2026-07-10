# SAMS

Super Asset Management System (SAMS) adalah platform operasional modular untuk
inventory, purchasing, budgeting, approval, asset, dan audit.

## Local Development

Kebutuhan lokal:

- Laragon
- PHP 8.3+
- MySQL 8+
- Composer 2+
- Node.js dan npm

Konfigurasi awal:

```powershell
Copy-Item .env.example .env
composer install
php artisan key:generate
php artisan migrate
npm install
npm run dev
php artisan serve
```

Konfigurasi database Laragon standar:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sams
DB_USERNAME=root
DB_PASSWORD=
```

Jangan menyimpan `.env`, kata sandi, token, atau data produksi ke Git.

## Current Foundation

- Laravel 13
- MySQL database `sams`
- Login/logout lokal
- Role awal user (`super_admin`, `staff`) dan status aktif
- Role/permission dasar untuk `super_admin`, `purchasing`, `warehouse`, `finance`, dan `staff`
- User Management untuk super admin: tambah user, edit role/status, dan reset password
- Dashboard awal SAMS dengan sidebar modul
- Seeder akun admin, company, branch, department, unit, supplier, gudang, kategori, dan item demo
- Purchase Request draft, edit draft, detail, nomor dokumen otomatis, budget line, validasi sisa budget, submit, approve, dan reject awal
- Purchase Order draft dari Purchase Request approved, submit, dan approve awal
- Print/Save PDF Purchase Order dengan format dokumen A4, supplier, item, total, dan area tanda tangan
- Print/Save PDF Purchase Request dan Goods Receipt dengan format dokumen A4 dan area tanda tangan
- Goods Receipt draft dari Purchase Order approved, posting GR, dan stock movement masuk gudang
- Stock On Hand per gudang dari aggregate stock movement
- Stock Opname draft dari saldo sistem, hasil hitung fisik, posting adjustment selisih plus/minus ke stock movement
- Print/Save PDF Stock Opname dengan ringkasan selisih, item, nilai variance, dan area tanda tangan
- Laporan Mutasi Stok dengan filter tanggal, gudang, item, saldo awal, movement masuk/keluar, dan saldo berjalan
- Multi-company, multi-branch, dan department
- Supplier dan item master
- Department budget
- Purchase Request dan Purchase Order
- Goods Receipt dan stock ledger
- Stock Opname dan adjustment ledger
- Approval workflow
- Attachments dan audit log

Blueprint database tersedia di
[`docs/SAMS_DATABASE_ERD_V1.md`](docs/SAMS_DATABASE_ERD_V1.md).

## Local Login

Setelah menjalankan migration dan seeder:

```powershell
php artisan migrate --seed
php artisan serve
```

Buka aplikasi lokal, lalu login dengan:

- Email: `admin@sams.local`
- Password: `password`

Password ini hanya untuk development lokal dan wajib diganti sebelum staging/VPS.

Seeder juga menyediakan user demo role:

- `purchasing@sams.local` / `password`
- `warehouse@sams.local` / `password`
- `finance@sams.local` / `password`
- `staff@sams.local` / `password`

## Demo Master Data

Seeder lokal mengisi data sample realistis untuk hotel/resto:

- kategori item seperti F&B, Housekeeping, Linen, Engineering, Office, dan Asset;
- satuan seperti PCS, KG, LTR, BTL, PACK, ROLL, dan SET;
- supplier demo;
- lokasi gudang/outlet;
- item operasional seperti beras, coffee beans, air mineral, linen, tissue, lampu, chemical, dan asset laptop.
- budget tahunan sample per departemen dan account code.

Data ini hanya sample lokal. Semua bisa diedit atau dihapus dari halaman Master Data sebelum website live.

## Next Build Steps

Urutan kerja berikutnya:

1. Master data item, kategori, satuan, supplier, dan gudang.
2. Print/PDF laporan inventory.
3. Role/permission yang lebih detail untuk tiap modul.
4. Audit trail tampilan dan laporan budget.
5. Asset register dan maintenance workflow.

## VPS Migration Strategy

Saat aplikasi siap dipindahkan:

1. Siapkan Linux VPS dengan Nginx, PHP-FPM, MySQL/PostgreSQL, Redis, dan SSL.
2. Deploy source code melalui Git.
3. Buat `.env` produksi langsung di VPS.
4. Jalankan `composer install --no-dev --optimize-autoloader`.
5. Jalankan `php artisan migrate --force`.
6. Bangun aset dengan `npm ci && npm run build`.
7. Aktifkan queue worker, scheduler, backup database, dan monitoring.

Database produksi tidak disalin dengan menimpa migration. Data lokal yang perlu
dipertahankan akan diekspor, diverifikasi, lalu diimpor melalui prosedur migrasi.
