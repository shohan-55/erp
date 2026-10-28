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

            // Seed default clients for existing firms
            $firms = DB::table('firms')->get();
            $defaultClients = [
                ['name' => 'BEXIMCO Textiles Audit', 'industry' => 'Textiles & Garments', 'location' => 'Kashimpur, Gazipur'],
                ['name' => 'Square Pharmaceuticals Audit', 'industry' => 'Pharmaceuticals', 'location' => 'Kaliakair, Gazipur'],
                ['name' => 'BRAC Bank PLC (Motijheel Branch)', 'industry' => 'Banking & Financial', 'location' => 'Motijheel C/A, Dhaka'],
                ['name' => 'ACI Logistics Limited (Shwapno)', 'industry' => 'Retail & FMCG', 'location' => 'Tejgaon I/A, Dhaka'],
                ['name' => 'General Office / Firm HQ', 'industry' => 'Internal Administration', 'location' => 'Firm Principal Office'],
            ];

            foreach ($firms as $firm) {
                foreach ($defaultClients as $client) {
                    DB::table('audit_clients')->insert([
                        'firm_id'    => $firm->id,
                        'name'       => $client['name'],
                        'industry'   => $client['industry'],
                        'location'   => $client['location'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_clients');
    }
};
