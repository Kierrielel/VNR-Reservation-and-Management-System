<?php

namespace Database\Seeders;

use App\Models\Cottage;
use Illuminate\Database\Seeder;

class CottageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            Cottage::create([
                'name' => "Nipa Hut {$i}",
                'type' => 'cottage',
                'capacity' => 16,
                'price_day' => 800,
                'price_overnight' => null,
                'image' => null,
                'status' => 'available',
            ]);
        }
        for ($i = 1; $i <= 3; $i++) {
            Cottage::create([
                'name' => "Umbrella Cottage {$i}",
                'type' => 'cottage',
                'capacity' => 9,
                'price_day' => 400,
                'price_overnight' => null,
                'image' => null,
                'status' => 'available',
            ]);
        }
        Cottage::create([
            'name' => "Family Cottage",
            'type' => 'room',
            'capacity' => 10,
            'price_day' => 3600,
            'price_overnight' => 3600,
            'image' => null,
            'status' => 'available',
        ]);
        Cottage::create([
            'name' => "Deluxe Room",
            'type' => 'room',
            'capacity' => 5,
            'price_day' => 2000,
            'price_overnight' => 2000,
            'image' => null,
            'status' => 'available',
        ]);
        Cottage::create([
            'name' => "VIP Suite",
            'type' => 'room',
            'capacity' => 4,
            'price_day' => 4600,
            'price_overnight' => 4600,
            'image' => null,
            'status' => 'available',
        ]);
    }
}
