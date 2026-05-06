<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run()
    {
        $suppliers = [
            [
                'name' => 'Motolite Trading',
                'tin' => '001-234-567-000',
                'business_style' => 'Trading',
                'contact_person' => 'Roberto Santos',
                'email' => 'roberto@motolite.ph',
                'phone' => '+63 2 8234 5678',
                'address' => 'Banawe Street, Quezon City',
                'city' => 'Quezon City',
                'rating' => 4.5,
                'lead_time_days' => 7,
            ],
            [
                'name' => 'Speed Motorsports Supply',
                'tin' => '002-345-678-000',
                'business_style' => 'Parts Supply',
                'contact_person' => 'Maria Cruz',
                'email' => 'maria@speedmotorsports.ph',
                'phone' => '+63 2 8345 6789',
                'address' => 'Boni Avenue, Mandaluyong',
                'city' => 'Mandaluyong',
                'rating' => 4.2,
                'lead_time_days' => 5,
            ],
            [
                'name' => 'JVT Parts Distributor',
                'tin' => '003-456-789-000',
                'business_style' => 'Distributor',
                'contact_person' => 'Antonio Reyes',
                'email' => 'antonio@jvtparts.ph',
                'phone' => '+63 2 8456 7890',
                'address' => 'Marikina Industrial Park',
                'city' => 'Marikina',
                'rating' => 4.8,
                'lead_time_days' => 3,
            ],
            [
                'name' => 'Performance Parts Hub',
                'tin' => '004-567-890-000',
                'business_style' => 'Retail and Wholesale',
                'contact_person' => 'Sarah Gonzales',
                'email' => 'sarah@performancehub.ph',
                'phone' => '+63 2 8567 8901',
                'address' => 'Pasig City',
                'city' => 'Pasig',
                'rating' => 4.3,
                'lead_time_days' => 10,
            ],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::create($supplier);
        }
    }
}
