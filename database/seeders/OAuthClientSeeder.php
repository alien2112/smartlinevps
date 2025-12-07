<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

class OAuthClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Check if personal access client already exists
        $existingClient = DB::table('oauth_clients')
            ->where('personal_access_client', true)
            ->first();

        if (!$existingClient) {
            // Create personal access client
            $clientId = Uuid::uuid4()->toString();
            $clientSecret = Str::random(40);

            DB::table('oauth_clients')->insert([
                'id' => $clientId,
                'user_id' => null,
                'name' => 'Personal Access Client',
                'secret' => Hash::make($clientSecret),
                'provider' => null,
                'redirect' => 'http://localhost',
                'personal_access_client' => true,
                'password_client' => false,
                'revoked' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Link to personal access clients table
            DB::table('oauth_personal_access_clients')->insert([
                'client_id' => $clientId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command->info('Personal Access Client created successfully!');
            $this->command->info('Client ID: ' . $clientId);
            $this->command->info('Client Secret: ' . $clientSecret);
        } else {
            $this->command->info('Personal Access Client already exists.');
        }

        // Create password grant client (optional, commonly used for API authentication)
        $passwordClient = DB::table('oauth_clients')
            ->where('password_client', true)
            ->first();

        if (!$passwordClient) {
            $passwordClientId = Uuid::uuid4()->toString();
            $passwordClientSecret = Str::random(40);

            DB::table('oauth_clients')->insert([
                'id' => $passwordClientId,
                'user_id' => null,
                'name' => 'Password Grant Client',
                'secret' => Hash::make($passwordClientSecret),
                'provider' => null,
                'redirect' => 'http://localhost',
                'personal_access_client' => false,
                'password_client' => true,
                'revoked' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command->info('Password Grant Client created successfully!');
            $this->command->info('Client ID: ' . $passwordClientId);
            $this->command->info('Client Secret: ' . $passwordClientSecret);
        } else {
            $this->command->info('Password Grant Client already exists.');
        }
    }
}

