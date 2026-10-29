<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add invitation_token & invitation_sent_at to users table for real account activation
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'invitation_token')) {
                    $table->string('invitation_token', 100)->nullable()->index();
                }
                if (! Schema::hasColumn('users', 'invitation_sent_at')) {
                    $table->timestamp('invitation_sent_at')->nullable();
                }
            });
        }

        // 2. Monthly Allowance & Payroll Records (Draft -> Review -> Approve -> Paid -> Lock)
        if (! Schema::hasTable('payroll_records')) {
            Schema::create('payroll_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('firm_id')->constrained('firms')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('partner_unit')->default('Partner Unit 01');
                $table->string('period', 7); // YYYY-MM e.g. 2026-10
                $table->decimal('base_allowance', 10, 2)->default(0);
                $table->decimal('approved_conveyance', 10, 2)->default(0);
                $table->decimal('attendance_adjustment', 10, 2)->default(0);
                $table->decimal('net_payable', 10, 2)->default(0);
                $table->integer('present_days')->default(0);
                $table->integer('late_days')->default(0);
                $table->integer('leave_days')->default(0);
                $table->string('status', 30)->default('draft'); // draft, reviewed, approved, paid, locked
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('locked_at')->nullable();
                $table->json('policy_snapshot')->nullable();
                $table->timestamps();

                $table->unique(['firm_id', 'user_id', 'period']);
            });
        }

        // 3. Practice In-App Notifications
        if (! Schema::hasTable('practice_notifications')) {
            Schema::create('practice_notifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('firm_id')->constrained('firms')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
                $table->string('target_role', 30)->nullable(); // null = all roles in firm, or partner/manager/student
                $table->string('icon', 50)->default('bell');
                $table->string('title');
                $table->text('message');
                $table->boolean('is_read')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        // 4. Multi-Partner Policy Proposals & Consensus Voting
        if (! Schema::hasTable('policy_proposals')) {
            Schema::create('policy_proposals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('firm_id')->constrained('firms')->cascadeOnDelete();
                $table->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('proposed_office_start', 10)->default('10:00');
                $table->string('proposed_office_end', 10)->default('18:00');
                $table->integer('proposed_grace_minutes')->default(10);
                $table->decimal('proposed_conveyance_limit', 10, 2)->default(300.00);
                $table->date('effective_date')->nullable();
                $table->integer('required_votes')->default(3);
                $table->json('approvals')->nullable(); // Array of partner user IDs or names who approved
                $table->string('status', 30)->default('pending_consensus'); // pending_consensus, enacted, rejected
                $table->timestamps();
            });
        }

        // 5. Immutable Practice Audit Logs
        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('firm_id')->nullable()->constrained('firms')->cascadeOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('actor_name')->nullable();
                $table->string('actor_role', 30)->nullable();
                $table->string('module', 50); // attendance, leave, conveyance, payroll, policy, team, platform
                $table->string('action', 100);
                $table->string('target_ref', 100)->nullable();
                $table->text('summary');
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('policy_proposals');
        Schema::dropIfExists('practice_notifications');
        Schema::dropIfExists('payroll_records');

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'invitation_token')) {
                    $table->dropColumn('invitation_token');
                }
                if (Schema::hasColumn('users', 'invitation_sent_at')) {
                    $table->dropColumn('invitation_sent_at');
                }
            });
        }
    }
};
