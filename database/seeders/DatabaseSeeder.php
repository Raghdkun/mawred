<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Production\ProductionSeeder;
use Illuminate\Database\Seeder;

/**
 * `db:seed` runs the production seed only. Demo and performance data are separate (brief §21):
 *   php artisan db:seed --class="Database\Seeders\Demo\DemoSeeder"
 *   php artisan db:seed --class="Database\Seeders\Performance\PerformanceSeeder"
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ProductionSeeder::class);
    }
}
