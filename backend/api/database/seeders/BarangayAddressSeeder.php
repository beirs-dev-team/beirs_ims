<?php

namespace Database\Seeders;

use App\Models\Address\BarangayAddress;
use Illuminate\Database\Seeder;

class BarangayAddressSeeder extends Seeder
{
    public function run(): void
    {
        $addresses = [
            'abbra',
            'liong',
            'mabolo',
            'perez',
            'pulang-bukid',
            'sacred-heart',
            'sapang-daan',
            'sudlon',
            'tinago',
            'tres-rosas',

        ];

        foreach ($addresses as $name) {
            BarangayAddress::updateOrCreate(
                ['name' => $name],
                ['description' => null, 'is_active' => true]
            );
        }
    }
}
