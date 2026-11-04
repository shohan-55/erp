<?php

use App\Support\LegacySchemaHealer;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        LegacySchemaHealer::heal();
    }

    public function down(): void
    {
        // Legacy workspace_id cleanup is irreversible
    }
};
