# SuperSoft Enterprise

SuperSoft Enterprise adalah platform bisnis modular untuk hospitality dan wellness industry yang dikembangkan oleh **PT Supersoft Global Investama**.

Domain resmi yang direncanakan: **supersoft.id**.

## Product Suite

- **SaMS** — Super Asset Management System
- **SaS** — Super Accounting System
- **SPoS** — Super Point of Sale
- **SHMS** — Super Hotel Management System
- **SHRiS** — Super Human Resource Information System

Setiap perusahaan hanya memperoleh akses ke modul dan fitur yang tercantum dalam lisensinya. Modul yang digunakan bersama berbagi master data, kontrol akses, audit trail, dan integration engine yang sama.

## Technology Foundation

- Laravel 13 dan PHP 8.3+
- MySQL 8+
- Blade, Tailwind CSS, dan Vite
- Vue untuk layar operasional real-time apabila dibutuhkan
- Database queue, cache, dan session
- Private document storage dengan opsi cloud yang dapat dikembangkan

Laravel menjadi transactional core. Framework frontend tambahan hanya digunakan jika memberi manfaat yang jelas pada interaksi real-time; satu layar tidak mencampur beberapa framework frontend.

## Local Installation

Kebutuhan lokal:

- Laragon
- PHP 8.3+
- MySQL 8+
- Composer 2+
- Node.js 20.19+ atau 22.12+

Salin konfigurasi dan isi kredensial administrator awal:

```powershell
Copy-Item .env.example .env
composer install
php artisan key:generate
npm install
npm run build
```

Isi nilai berikut di `.env`:

```dotenv
INITIAL_ADMIN_NAME="SuperSoft Administrator"
INITIAL_ADMIN_EMAIL=admin@supersoft.local
INITIAL_ADMIN_PASSWORD="use-a-strong-local-password"
```

Buat instalasi kosong tanpa supplier, item, budget, transaksi, aset, atau COA:

```powershell
php artisan migrate:fresh --seed --seeder=Database\Seeders\FreshInstallationSeeder
php artisan serve
```

Jangan menyimpan `.env`, kata sandi, token, API key, data produksi, atau backup database ke Git.

## Development Data

`DatabaseSeeder` hanya digunakan sebagai fixture pengembangan dan automated test. Instalasi perusahaan wajib menggunakan `FreshInstallationSeeder`.

Database instalasi baru hanya berisi:

- PT Supersoft Global Investama;
- satu Head Office;
- administrator awal;
- katalog modul, permission, role, dan entitlement;
- tidak ada COA atau transaksi operasional.

## Security Baseline

- CSRF protection untuk perubahan data melalui web.
- Query binding melalui ORM dan query builder.
- Login throttling berdasarkan email dan alamat IP.
- Session regeneration setelah login.
- Role, permission, module entitlement, dan company scope.
- Security headers dan private cache policy.
- Attachment disimpan secara private dan diunduh melalui authorization check.
- Audit trail, transaction lock, idempotency, period lock, dan reversal ledger.
- Debug mode harus dinonaktifkan pada production.
- HTTPS dan secure session cookie wajib pada production.

Panduan lengkap tersedia di [SuperSoft Enterprise Foundation](docs/SUPERSOFT_ENTERPRISE_FOUNDATION.md) dan [Production Readiness](docs/PRODUCTION_READINESS.md).

## Quality Gates

Sebelum perubahan digabungkan atau dipasang ke server:

```powershell
php vendor/bin/pint --test
php artisan test
composer audit
npm audit
npm run build
```

Semua perubahan struktur database harus menggunakan migration. Perubahan transaksi keuangan atau inventory harus memiliki automated test untuk company scope, authorization, period lock, idempotency, dan audit trail.

## Ownership

Copyright © PT Supersoft Global Investama. Seluruh source code, desain produk, dokumentasi, dan identitas SuperSoft Enterprise dikelola sebagai aset perusahaan.
