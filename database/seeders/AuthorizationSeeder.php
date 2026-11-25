<?php

namespace Database\Seeders;

use App\Domains\Authorization\Actions\Command\SyncAuthorizationCatalog;
use Illuminate\Database\Seeder;

class AuthorizationSeeder extends Seeder
{
    public function run(): void
    {
        SyncAuthorizationCatalog::run();
    }
}
