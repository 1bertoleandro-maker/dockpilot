<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('client_id')
                ->nullable()
                ->after('company_id')
                ->constrained('clients')
                ->nullOnDelete();

            $table->index(['company_id', 'client_id']);
        });

        Schema::create('process_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('process_id')->constrained('processes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('type'); // observation | request | reply
            $table->string('visibility'); // internal | client
            $table->text('body');
            $table->timestamps();

            $table->index(['company_id', 'process_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_messages');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
        });
    }
};
