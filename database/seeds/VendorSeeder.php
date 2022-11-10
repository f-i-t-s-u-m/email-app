<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VendorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $json = File::get('database/data/vendors.json');
        $data = json_decode($json, true);

        foreach ($data as $obj) {
            DB::table('vendors')->insert([
                'id' => $obj['id'],
                'name' => $obj['name'],
                'logo_url' => $obj['logo_url'],
                'created_at' => $obj['created_at'],
                'updated_at' => $obj['updated_at']
            ]);
        }
    }
}
