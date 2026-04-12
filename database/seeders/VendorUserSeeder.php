<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class VendorUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vendorUser = [
            'first_name' => 'Vendor',
            'last_name' => 'User',
            'email' => 'vendor@gmail.com',
            'password' => bcrypt(12345),
            'user_type' => 2, // 2 for vendor
        ];
        User::create($vendorUser);
    }
}
