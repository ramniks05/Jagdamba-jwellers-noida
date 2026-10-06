<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('production')) {
            $this->call(FoundationSeeder::class);
        }

        $this->call(AccessSeeder::class);
        $this->call(MasterDataSeeder::class);
        $this->call(CommerceSeeder::class);
    }
}
