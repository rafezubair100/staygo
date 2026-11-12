<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(['email' => 'admin@staygo.test'], [
            'name' => 'Staygo Administrator', 'password' => 'ChangeThisPassword123!',
            'role' => 'admin', 'country' => 'United Kingdom',
        ]);

        $samples = [
            ['The Grand Birmingham', 'Birmingham', 'United Kingdom', 4, 'A centrally located sample property record for local development.', 119],
            ['Riverside City Hotel', 'London', 'United Kingdom', 4, 'A sample property record. Connect a live supplier for current offers.', 165],
            ['Kathmandu Valley Stay', 'Kathmandu', 'Nepal', 3, 'A sample property record for testing destination pages.', 54],
            ['Shinjuku Central Hotel', 'Tokyo', 'Japan', 4, 'A sample property record for testing worldwide search UI.', 132],
            ['Dubai Marina Suites', 'Dubai', 'United Arab Emirates', 5, 'A sample property record for development only.', 210],
            ['Paris Garden Hotel', 'Paris', 'France', 4, 'A sample property record for development only.', 178],
        ];
        foreach ($samples as [$name, $city, $country, $stars, $description, $price]) {
            Hotel::firstOrCreate(['slug' => Str::slug($name)], [
                'name' => $name, 'city' => $city, 'country' => $country,
                'stars' => $stars, 'description' => $description,
                'indicative_price' => $price, 'supplier' => 'seed_sample',
                'amenities' => ['Wi-Fi', 'Reception', 'Non-smoking rooms'],
            ]);
        }
    }
}
