<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Setting::updateOrCreate(['key' => 'guest_rate_day'], ['value' => '150']);
        Setting::updateOrCreate(['key' => 'guest_rate_overnight'], ['value' => '200']);
    }
}
