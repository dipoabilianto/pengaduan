<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesTableSeeder::class);

        $this->seedUser('superuser@sidumas.test', 'Superuser', 'superuser');
        $this->seedUser('admin@sidumas.test', 'Admin Disdukcapil', 'Administrator');
        $this->seedUser('pejabat@sidumas.test', 'Kepala Bidang Pelayanan', 'Pelaksana');
        $this->seedUser('pengawas@sidumas.test', 'Pengawas Disdukcapil', 'Pengawas');
    }

    private function seedUser(string $email, string $name, string $role): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => bcrypt('password'), 'email_verified_at' => now()]
        );
        $user->assignRole($role);

        return $user;
    }
}
