<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        
        $json = File::get('database/data/users.json');
        $data = json_decode($json, true);

        foreach ($data as $obj) {
            DB::table('users')->insert([
                'id' => $obj['id'],
                'name' => $obj['name'],
                'email' => $obj['email'],
                'phone' => $obj['phone'],
                'dob' => $obj['dob'],
                'address' => $obj['address'],
                'email_verified_at' => $obj['email_verified_at'],
                'password' => $obj['password'],
                'remember_token' => $obj['remember_token'],
                'created_at' => $obj['created_at'],
                'updated_at' => $obj['updated_at']
            ]);
        }
    }
}
