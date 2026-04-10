<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('oracle_users', function (Blueprint $table) {
            $table->id();

            $table->string('AppAudience', 100)->nullable();
            $table->string('UserID', 100)->nullable()->index();
            $table->string('ClientID', 100)->nullable()->index();
            $table->string('NPClientID', 100)->nullable();

            $table->text('Token')->nullable();

            $table->timestamp('DateRegistered')->nullable();
            $table->string('SessionHash', 255)->nullable()->unique();
            $table->timestamp('SessionEXP')->nullable();

            // Optional: add soft deletes if you ever want to "archive" users
            // $table->softDeletes();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oracle_users');
    }
};