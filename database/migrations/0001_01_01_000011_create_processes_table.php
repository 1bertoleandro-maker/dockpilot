<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('reference');
            $table->string('type');
            $table->string('status');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'reference']);
            $table->index('company_id');
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processes');
    }
};
