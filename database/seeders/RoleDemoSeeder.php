<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use Illuminate\Support\Str;

class RoleDemoSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'title' => 'POS Access',
                'slug' => 'pos-access',
                'description' => 'This role have only POS related privileges',
                'permissions' => array_fill(0, 218, 'pos_privilege'),
            ],
            [
                'title' => 'Resturnt POS access',
                'slug' => 'resturnt-pos-access',
                'description' => 'Resturnt POS access',
                'permissions' => ['restaurant_pos'],
            ],
            [
                'title' => 'WEB Access all 3 features',
                'slug' => 'web-access-all-3-features',
                'description' => 'WEB Access all 3 features with shop management',
                'permissions' => ['web_access'],
            ],
            [
                'title' => 'WEB + POS access with all 3 features',
                'slug' => 'web-pos-access-with-all-3-features',
                'description' => 'WEB + POS access with all 3 features',
                'permissions' => ['web_access', 'pos_access'],
            ],
            [
                'title' => 'All Access',
                'slug' => 'all-access',
                'description' => 'All administrative, web, and POS system privileges',
                'permissions' => ['all_access'],
            ],
            [
                'title' => 'RM3 7DX',
                'slug' => 'rm3-7dx',
                'description' => '—',
                'permissions' => array_fill(0, 300, 'rm_privilege'),
            ],
        ];

        foreach ($roles as $r) {
            Role::firstOrCreate(
                ['slug' => $r['slug']],
                [
                    'id' => (string) Str::uuid(),
                    'title' => $r['title'],
                    'description' => $r['description'],
                    'permissions' => $r['permissions'],
                    'is_deleted' => false,
                ]
            );
        }
    }
}
