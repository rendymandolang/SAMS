<?php

namespace Database\Seeders;

use App\Support\AccessControlProvisioner;
use Illuminate\Database\Seeder;

class AccessControlSeeder extends Seeder
{
    public function run(): void
    {
        app(AccessControlProvisioner::class)->syncAllCompanies();
    }
}
