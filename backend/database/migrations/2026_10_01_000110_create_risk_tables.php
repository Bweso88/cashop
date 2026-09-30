<?php

use App\Domain\Risk\Enums\ComplianceCaseStatus;
use App\Domain\Risk\Enums\ComplianceCaseType;
use App\Domain\Risk\Enums\RiskAction;
use App\Domain\Risk\Enums\RiskSubjectType;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('name');
            // Paramètres calibrés par la conformité (seuils, fenêtres…).
            $table->jsonb('parameters')->default('{}');
            $table->string('action', 15);
            $table->unsignedSmallInteger('score_weight')->default(0);
            $table->boolean('is_active')->default(false);
            $table->timestampsTz();
        });
        Check::enum('risk_rules', 'action', RiskAction::class);

        Schema::create('risk_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained();
            $table->foreignUuid('transfer_id')->nullable()->constrained();
            $table->string('rule_code', 40);
            $table->unsignedSmallInteger('score');
            $table->string('action_taken', 15);
            $table->jsonb('details')->nullable();
            $table->timestampTz('created_at');

            $table->index(['user_id', 'created_at']);
        });
        Check::enum('risk_events', 'action_taken', RiskAction::class);
        Check::appendOnly('risk_events');

        Schema::create('risk_scores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('subject_type', 10);
            $table->uuid('subject_id');
            $table->unsignedSmallInteger('score');
            $table->jsonb('factors');
            $table->timestampTz('computed_at');

            $table->index(['subject_type', 'subject_id', 'computed_at']);
        });
        Check::enum('risk_scores', 'subject_type', RiskSubjectType::class);
        Check::add('risk_scores', 'risk_scores_range', 'score <= 100');

        Schema::create('compliance_cases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained();
            $table->foreignUuid('transfer_id')->nullable()->constrained();
            $table->string('type', 15);
            $table->string('status', 20)->default(ComplianceCaseStatus::Open->value);
            $table->unsignedSmallInteger('priority')->default(3);
            $table->foreignUuid('assigned_to')->nullable()->constrained('users');
            $table->text('notes')->nullable(); // chiffrées
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'priority']);
        });
        Check::enum('compliance_cases', 'type', ComplianceCaseType::class);
        Check::enum('compliance_cases', 'status', ComplianceCaseStatus::class);
        Check::add('compliance_cases', 'compliance_cases_priority_range', 'priority BETWEEN 1 AND 5');
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_cases');
        Schema::dropIfExists('risk_scores');
        Schema::dropIfExists('risk_events');
        Schema::dropIfExists('risk_rules');
    }
};
