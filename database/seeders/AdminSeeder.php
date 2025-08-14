<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admins = [
            [
                'name' => "Lineup Admin",
                'email' => 'admin@lineup.com',
                'password' => Hash::make('12345678')
            ],
        ];

        foreach($admins as $admin){
            Admin::create($admin);
        }
    }
}
