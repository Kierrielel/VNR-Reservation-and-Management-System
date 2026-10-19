<?php

namespace Database\Seeders;

use App\Models\EventPackage;
use Illuminate\Database\Seeder;

class EventPackageSeeder extends Seeder
{
    public function run(): void
    {
        EventPackage::create([
            'name' => 'Bronze',
            'price' => 15000,
            'hours' => 4,
            'inclusions' => [
                'Basic Venue Decoration',
                'Tables & Chairs Access',
            ],
            'image' => null,
            'status' => 'available',
        ]);

        EventPackage::create([
            'name' => 'Silver',
            'price' => 30000,
            'hours' => 6,
            'inclusions' => [
                'Tables & Chairs Arrangement',
                'Basic Floral Styling',
            ],
            'image' => null,
            'status' => 'available',
        ]);

        EventPackage::create([
            'name' => 'Gold',
            'price' => 50000,
            'hours' => 10,
            'inclusions' => [
                'Tables & Chairs Setup',
                'Premium Floral Design & Styling',
                'Basic Light & Sound Setup',
            ],
            'image' => null,
            'status' => 'available',
        ]);
    }
}
