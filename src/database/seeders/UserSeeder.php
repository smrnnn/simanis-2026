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
        $sadmin = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            ['name' => 'Super Admin', 'password' => Hash::make('password')]
        );
        $sadmin->assignRole('super_admin');

        $akademik = User::firstOrCreate(
            ['email' => 'adm@admin.com'],
            ['name' => 'Akademik', 'password' => Hash::make('password')]
        );
        $akademik->assignRole('akademik');

        $guru = User::firstOrCreate(
            ['email' => 'guru@admin.com'],
            ['name' => 'Guru', 'password' => Hash::make('password')]
        );
        $guru->assignRole('guru');

        $siswa = User::firstOrCreate(
            ['email' => 'siswa@admin.com'],
            ['name' => 'Siswa', 'password' => Hash::make('password')]
        );
        $siswa->assignRole('siswa');

        $ortu = User::firstOrCreate(
            ['email' => 'ortu@admin.com'],
            ['name' => 'Orang Tua', 'password' => Hash::make('password')]
        );
        $ortu->assignRole('orang_tua');
    }
}