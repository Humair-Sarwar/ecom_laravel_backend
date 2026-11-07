<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Business;
use App\Models\User;
use App\Models\Role;
use App\Models\Country;

class BusinessDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Fetch or create users for reference
        $qaisar = User::firstOrCreate(
            ['email' => 'donesoltech@gmail.com'],
            [
                'first_name' => 'Qaisar',
                'last_name' => 'Malik',
                'user_type' => 2,
                'phone' => '+44 7911 123456',
                'password' => bcrypt('password123'),
                'status' => true,
                'business_name' => 'DoneSol Tech Services LTD',
            ]
        );

        $waseem = User::firstOrCreate(
            ['email' => 'wshahzad868@gmail.com'],
            [
                'first_name' => 'Waseem',
                'last_name' => 'Shehzad',
                'user_type' => 2,
                'phone' => '+92 300 1234567',
                'password' => bcrypt('password123'),
                'status' => true,
                'business_name' => 'Coffee Shop',
            ]
        );

        $waseem2 = User::firstOrCreate(
            ['email' => 'wshahzad777@gmail.com'],
            [
                'first_name' => 'Waseem',
                'last_name' => 'Shehzad',
                'user_type' => 2,
                'phone' => '+92 301 7654321',
                'password' => bcrypt('password123'),
                'status' => true,
                'business_name' => 'Architect SOI',
            ]
        );

        $demoUser = User::firstOrCreate(
            ['email' => 'demo@hotmail.com'],
            [
                'first_name' => 'Demo',
                'last_name' => 'Account',
                'user_type' => 2,
                'phone' => '+44 7922 987654',
                'password' => bcrypt('password123'),
                'status' => true,
                'business_name' => 'Gadgets & TECH',
            ]
        );

        $khurram = User::firstOrCreate(
            ['email' => 'khurram@gmail.com'],
            [
                'first_name' => 'Khurram',
                'last_name' => 'Ali',
                'user_type' => 2,
                'phone' => '+44 7933 555666',
                'password' => bcrypt('password123'),
                'status' => true,
                'business_name' => 'Khurram Store',
            ]
        );

        $mtechUser = User::firstOrCreate(
            ['email' => 'mtech@test.com'],
            [
                'first_name' => 'M',
                'last_name' => 'Tech',
                'user_type' => 2,
                'phone' => '+92 305 4443332',
                'password' => bcrypt('password123'),
                'status' => true,
                'business_name' => 'M Tech 1',
            ]
        );

        $itechUser = User::firstOrCreate(
            ['email' => 'itech@gmail.com'],
            [
                'first_name' => 'I',
                'last_name' => 'Tech',
                'user_type' => 2,
                'phone' => '+44 7944 111222',
                'password' => bcrypt('password123'),
                'status' => true,
                'business_name' => 'iTech System',
            ]
        );

        // Fetch roles by title
        $posRole = Role::where('title', 'like', '%POS%')->first();
        $allRole = Role::where('title', 'like', '%All%')->first();
        $webPosRole = Role::where('title', 'like', '%WEB%')->first() ?? $posRole;

        // Fetch countries
        $uk = Country::where('iso', 'GB')->orWhere('name', 'like', '%Kingdom%')->first();
        $pk = Country::where('iso', 'PK')->orWhere('name', 'like', '%Pakistan%')->first();

        $businesses = [
            [
                'owner_id' => $qaisar->id,
                'role_id' => $posRole?->id,
                'role_title' => $posRole?->title ?? 'POS Access',
                'name' => 'DoneSol Tech Services LTD',
                'branch_name' => 'Test',
                'business_type' => 'Electronics Store',
                'email' => 'donesoltech@gmail.com',
                'mobile_no' => '+44 7911 123456',
                'whatsapp_no' => '+44 7911 123456',
                'is_system_website' => true,
                'is_personal_website' => false,
                'package_level' => 'Basic',
                'services' => ['sale', 'trade_in', 'repair', 'pos'],
                'schema_type' => 'new',
                'schema_name' => 'qaisar_malik',
                'address' => '12 High Street',
                'city_name' => 'London',
                'state_region' => 'Greater London',
                'post_code' => 'E1 6AN',
                'country_id' => $uk?->id,
                'country_name' => 'United Kingdom',
                'currency' => 'GBP',
                'status' => 'active',
                'created_at' => '2026-11-07 05:30:00',
            ],
            [
                'owner_id' => $waseem->id,
                'role_id' => $allRole?->id,
                'role_title' => $allRole?->title ?? 'All Access',
                'name' => 'Coffee Shop',
                'branch_name' => 'Sargodha',
                'business_type' => 'Liquor Store',
                'email' => 'wshahzad868@gmail.com',
                'mobile_no' => '+92 300 1234567',
                'whatsapp_no' => '+92 300 1234567',
                'is_system_website' => false,
                'is_personal_website' => true,
                'website_url' => 'https://coffeeshop.test',
                'package_level' => 'Basic',
                'services' => ['sale', 'pos', 'own_site'],
                'schema_type' => 'new',
                'schema_name' => 'waseeem_shahzad',
                'address' => 'Satellite Town Main Road',
                'city_name' => 'Sargodha',
                'state_region' => 'Punjab',
                'post_code' => '40100',
                'country_id' => $pk?->id,
                'country_name' => 'Pakistan',
                'currency' => 'PKR',
                'status' => 'active',
                'created_at' => '2026-11-03 09:21:00',
            ],
            [
                'owner_id' => $waseem2->id,
                'role_id' => $allRole?->id,
                'role_title' => $allRole?->title ?? 'All Access',
                'name' => 'Architect SOI',
                'branch_name' => 'Sargodha Branch',
                'business_type' => 'Apparel Store',
                'email' => 'wshahzad777@gmail.com',
                'mobile_no' => '+92 301 7654321',
                'whatsapp_no' => '+92 301 7654321',
                'is_system_website' => false,
                'is_personal_website' => true,
                'website_url' => 'https://architect-soi.test',
                'package_level' => 'Basic',
                'services' => ['sale', 'pos', 'own_site'],
                'schema_type' => 'existing',
                'schema_name' => 'waseeem_shahzad',
                'address' => 'University Road',
                'city_name' => 'Sargodha',
                'state_region' => 'Punjab',
                'post_code' => '40100',
                'country_id' => $pk?->id,
                'country_name' => 'Pakistan',
                'currency' => 'PKR',
                'status' => 'active',
                'created_at' => '2026-11-03 10:15:00',
            ],
            [
                'owner_id' => $demoUser->id,
                'role_id' => $posRole?->id,
                'role_title' => $posRole?->title ?? 'POS Access',
                'name' => 'Gadgets & TECH',
                'branch_name' => 'London',
                'business_type' => 'Electronics Store',
                'email' => 'demo@hotmail.com',
                'mobile_no' => '+44 7922 987654',
                'whatsapp_no' => '+44 7922 987654',
                'is_system_website' => true,
                'is_personal_website' => false,
                'package_level' => 'Basic',
                'services' => ['sale', 'trade_in', 'repair', 'pos'],
                'schema_type' => 'new',
                'schema_name' => 'demo_account1',
                'address' => '45 Regent Street',
                'city_name' => 'London',
                'state_region' => 'Greater London',
                'post_code' => 'W1B 2EL',
                'country_id' => $uk?->id,
                'country_name' => 'United Kingdom',
                'currency' => 'GBP',
                'status' => 'active',
                'created_at' => '2026-10-15 14:00:00',
            ],
            [
                'owner_id' => $khurram->id,
                'role_id' => $webPosRole?->id,
                'role_title' => $webPosRole?->title ?? 'WEB + POS access with all 3 features',
                'name' => 'Khurram',
                'branch_name' => 'Store',
                'business_type' => 'Flower Shop',
                'email' => 'khurram@gmail.com',
                'mobile_no' => '+44 7933 555666',
                'whatsapp_no' => '+44 7933 555666',
                'is_system_website' => true,
                'is_personal_website' => false,
                'package_level' => 'Basic',
                'services' => ['sale', 'repair', 'pos'],
                'schema_type' => 'existing',
                'schema_name' => 'public',
                'address' => '22 Oxford Avenue',
                'city_name' => 'Manchester',
                'state_region' => 'Greater Manchester',
                'post_code' => 'M1 4ET',
                'country_id' => $uk?->id,
                'country_name' => 'United Kingdom',
                'currency' => 'GBP',
                'status' => 'active',
                'created_at' => '2026-09-18 18:38:00',
            ],
            [
                'owner_id' => $mtechUser->id,
                'role_id' => $allRole?->id,
                'role_title' => $allRole?->title ?? 'All Access',
                'name' => 'M Tech',
                'branch_name' => 'M Tech 1',
                'business_type' => 'Electronics Store',
                'email' => 'mtech@test.com',
                'mobile_no' => '+92 305 4443332',
                'whatsapp_no' => '+92 305 4443332',
                'is_system_website' => true,
                'is_personal_website' => false,
                'package_level' => 'Basic',
                'services' => ['sale', 'repair', 'pos'],
                'schema_type' => 'existing',
                'schema_name' => 'public',
                'address' => 'Hafeez Centre Plaza',
                'city_name' => 'Lahore',
                'state_region' => 'Punjab',
                'post_code' => '54000',
                'country_id' => $pk?->id,
                'country_name' => 'Pakistan',
                'currency' => 'PKR',
                'status' => 'active',
                'created_at' => '2026-08-27 10:01:00',
            ],
            [
                'owner_id' => $itechUser->id,
                'role_id' => $allRole?->id,
                'role_title' => $allRole?->title ?? 'All Access',
                'name' => 'iTech',
                'branch_name' => 'System',
                'business_type' => 'Electronics Store',
                'email' => 'itech@gmail.com',
                'mobile_no' => '+44 7944 111222',
                'whatsapp_no' => '+44 7944 111222',
                'is_system_website' => true,
                'is_personal_website' => false,
                'package_level' => 'Basic',
                'services' => ['sale', 'repair', 'pos'],
                'schema_type' => 'existing',
                'schema_name' => 'public',
                'address' => '10 Commercial Road',
                'city_name' => 'Birmingham',
                'state_region' => 'West Midlands',
                'post_code' => 'B1 1AA',
                'country_id' => $uk?->id,
                'country_name' => 'United Kingdom',
                'currency' => 'GBP',
                'status' => 'active',
                'created_at' => '2026-08-20 11:08:00',
            ],
            [
                'owner_id' => $qaisar->id,
                'role_id' => $posRole?->id,
                'role_title' => $posRole?->title ?? 'POS Access',
                'name' => 'DoneSol Alpha Lab',
                'branch_name' => 'East Lab',
                'business_type' => 'Electronics Store',
                'email' => 'alphalab@donesol.com',
                'mobile_no' => '+44 7911 999888',
                'whatsapp_no' => '+44 7911 999888',
                'is_system_website' => true,
                'is_personal_website' => false,
                'package_level' => 'Standard',
                'services' => ['sale', 'repair', 'pos'],
                'schema_type' => 'existing',
                'schema_name' => 'qaisar_malik',
                'address' => '88 Canary Wharf',
                'city_name' => 'London',
                'state_region' => 'Greater London',
                'post_code' => 'E14 5AB',
                'country_id' => $uk?->id,
                'country_name' => 'United Kingdom',
                'currency' => 'GBP',
                'status' => 'blocked',
                'created_at' => '2026-07-10 12:00:00',
            ],
        ];

        foreach ($businesses as $bData) {
            Business::updateOrCreate(
                ['name' => $bData['name'], 'branch_name' => $bData['branch_name']],
                $bData
            );
        }
    }
}
