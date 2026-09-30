<?php

use App\Domain\Kyc\Enums\KycCheckResult;
use App\Domain\Kyc\Enums\KycCheckType;
use App\Domain\Kyc\Enums\KycDocumentStatus;
use App\Domain\Kyc\Enums\KycDocumentType;
use App\Domain\Kyc\Enums\KycStatus;
use App\Domain\Kyc\Enums\RiskRating;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_levels', function (Blueprint $table) {
            $table->string('code', 20)->primary();
            $table->string('name');
            $table->unsignedSmallInteger('rank')->unique();
            // Exigences (documents, vérifications) — structure à valider par la conformité.
            $table->jsonb('requirements')->default('[]');
            $table->text('description')->nullable();
            $table->timestampsTz();
        });

        Schema::create('kyc_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('level_code', 20);
            $table->string('status', 12)->default(KycStatus::Pending->value);
            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users');
            $table->text('rejection_reason')->nullable();
            $table->string('risk_rating', 8)->nullable();
            $table->timestampsTz();

            $table->foreign('level_code')->references('code')->on('kyc_levels');
            $table->index('status');
        });
        Check::enum('kyc_profiles', 'status', KycStatus::class);
        Check::enum('kyc_profiles', 'risk_rating', RiskRating::class);

        Schema::create('kyc_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('kyc_profile_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('side', 5)->nullable();
            $table->text('number_encrypted')->nullable();
            $table->string('number_hash', 64)->nullable()->index();
            $table->char('issuing_country', 2)->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            // Chemin dans le stockage objet chiffré (bucket privé, URL signées).
            $table->string('storage_path');
            $table->string('mime_type', 50);
            $table->char('sha256', 64);
            $table->string('status', 10)->default(KycDocumentStatus::Pending->value);
            $table->timestampsTz();

            $table->foreign('issuing_country')->references('code')->on('countries');
        });
        Check::enum('kyc_documents', 'type', KycDocumentType::class);
        Check::enum('kyc_documents', 'status', KycDocumentStatus::class);
        Check::add('kyc_documents', 'kyc_documents_side_check', "side IS NULL OR side IN ('FRONT', 'BACK')");

        Schema::create('kyc_verifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('kyc_profile_id')->constrained()->cascadeOnDelete();
            $table->string('vendor', 40);
            $table->string('vendor_reference')->nullable();
            $table->string('check_type', 12);
            $table->string('result', 8);
            $table->decimal('score', 5, 4)->nullable();
            // Réponse brute du fournisseur, chiffrée si elle contient des données personnelles.
            $table->text('raw_response')->nullable();
            $table->foreignUuid('performed_by')->nullable()->constrained('users');
            $table->timestampTz('created_at');
        });
        Check::enum('kyc_verifications', 'check_type', KycCheckType::class);
        Check::enum('kyc_verifications', 'result', KycCheckResult::class);
        Check::add('kyc_verifications', 'kyc_verifications_score_check', 'score IS NULL OR (score >= 0 AND score <= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_verifications');
        Schema::dropIfExists('kyc_documents');
        Schema::dropIfExists('kyc_profiles');
        Schema::dropIfExists('kyc_levels');
    }
};
