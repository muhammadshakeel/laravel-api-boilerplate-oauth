<?php

use Illuminate\Database\Seeder;

class ClientTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $client_id = bcrypt('client_id');
        $client_secret = bcrypt('client_secret');

        DB::table('oauth_clients')->insert([
            'id' => $client_id,
            'secret' => $client_secret,
            'name' => 'App Client',
            'created_at' => \Carbon\Carbon::now(),
            'updated_at' => \Carbon\Carbon::now(),
        ]);
    }
}
