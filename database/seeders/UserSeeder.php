<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'first_name' => "",
                'last_name' => "",
                'email' => '',
                'password' => Hash::make('PASSWORD')
            ],
            [
                'first_name' => "",
                'last_name' => "",
                'email' => '',
                'password' => Hash::make('PASSWORD')
            ],
        ];

        foreach($users as $user){
            User::create($user);
        }
    }
}
