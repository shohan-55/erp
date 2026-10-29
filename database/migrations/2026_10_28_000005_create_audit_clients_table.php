<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_clients')) {
            Schema::create('audit_clients', function (Blueprint $table) {
                $table->id();
                $table->foreignId('firm_id')->constrained('firms')->cascadeOnDelete();
                $table->string('name');
                $table->string('code')->nullable();
                $table->string('industry')->nullable();
                $table->string('location')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['firm_id', 'name']);
            });

            // Seed only the Principal Office HQ entry for any existing firms (no demo clients)
            $firms = DB::table('firms')->get();
            foreach ($firms as $firm) {
                DB::table('audit_clients')->insert([
                    'firm_id'    => $firm->id,
                    'name'       => 'General Office / Firm HQ',
                    'industry'   => 'Internal Administration',
                    'location'   => 'Firm Principal Office',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_clients');
    }
};
