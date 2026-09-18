<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database: the ARO catalogue always; demo firm,
     * users and matters outside production.
     */
    public function run(): void
    {
        $this->call(AroSeeder::class);

        if (! app()->isProduction()) {
            $this->call(DemoSeeder::class);
        }
    }
}
