<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->char('code', 3)->primary();
            $table->string('name');
            // Nombre de décimales ISO 4217 (XAF = 0, EUR = 2).
            $table->unsignedSmallInteger('minor_units');
            $table->boolean('is_active')->default(false);
            $table->timestampsTz();
        });

        Schema::create('countries', function (Blueprint $table) {
            $table->char('code', 2)->primary();
            $table->string('name');
            $table->char('default_currency', 3);
            $table->string('dial_code', 5);
            $table->boolean('is_send_enabled')->default(false);
            $table->boolean('is_receive_enabled')->default(false);
            $table->timestampsTz();

            $table->foreign('default_currency')->references('code')->on('currencies');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
        Schema::dropIfExists('currencies');
    }
};
