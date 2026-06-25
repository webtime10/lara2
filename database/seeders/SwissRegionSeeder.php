<?php

namespace Database\Seeders;

use App\Models\SwissRegion;
use Illuminate\Database\Seeder;

class SwissRegionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('swiss_regions', []) as $slug => $row) {
            SwissRegion::updateOrCreate(
                ['slug' => $slug],
                [
                    'label' => $row['label'],
                    'location_code' => (int) $row['location_code'],
                ]
            );
        }
    }
}
