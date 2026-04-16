<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oracle_fhir_tokens', function (Blueprint $table) {
            $table->id();

            $table->string('client_id', 255);
			$table->string('tenant_id', 255);
            $table->string('flow', 50);
            $table->string('owner_key', 255);

            $table->longText('access_token')->nullable();
            $table->longText('refresh_token')->nullable();
            $table->unsignedBigInteger('expires_at')->nullable();

            $table->text('scope')->nullable();
            $table->string('token_type', 50)->nullable();
            $table->string('patient_id', 255)->nullable();

            $table->timestamps();

            $table->unique(['client_id', 'flow', 'owner_key'], 'oracle_fhir_tokens_unique');
            $table->index(['client_id', 'flow'], 'oracle_fhir_tokens_client_flow');
            $table->index('expires_at', 'oracle_fhir_tokens_expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oracle_fhir_tokens');
    }
};
