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
                'first_name' => "Lineup",
                'last_name' => "User",
                'email' => 'user@lineup.com',
                'password' => Hash::make('12345678')
            ],
            [
                'first_name' => "Lineup",
                'last_name' => "User 2",
                'email' => 'user2@lineup.com',
                'password' => Hash::make('12345678')
            ],
            [
                'first_name' => "Lineup",
                'last_name' => "User 3",
                'email' => 'user3@lineup.com',
                'password' => Hash::make('12345678')
            ],
            [
                'first_name' => "Lineup",
                'last_name' => "User 4",
                'email' => 'user4@lineup.com',
                'password' => Hash::make('12345678')
            ],
            [
                'first_name' => "Lineup",
                'last_name' => "User 5",
                'email' => 'user5@lineup.com',
                'password' => Hash::make('12345678')
            ],
        ];

        foreach($users as $user){
            User::create($user);
        }
    }
}
