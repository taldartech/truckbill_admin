<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::query()->updateOrCreate(
            ['email' => 'admin@truckbill.in'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $superAdmin->assignRole('Super Admin');

        $salesManager = User::query()->updateOrCreate(
            ['email' => 'sales.manager@truckbill.in'],
            [
                'name' => 'Sales Manager',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );
        $salesManager->assignRole('Sales Manager');

        $salesExec = User::query()->updateOrCreate(
            ['email' => 'sales@truckbill.in'],
            [
                'name' => 'Sales Executive',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'manager_id' => $salesManager->id,
            ],
        );
        $salesExec->assignRole('Sales Executive');

        $salesPartner = User::query()->updateOrCreate(
            ['email' => 'sales.partner@truckbill.in'],
            [
                'name' => 'Sales Partner',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'manager_id' => $salesManager->id,
            ],
        );
        $salesPartner->assignRole('Sales Partner');
    }
}
