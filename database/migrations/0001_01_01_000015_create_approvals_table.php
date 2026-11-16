<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('process_id')->constrained('processes')->cascadeOnDelete();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->json('approved_payload_json');
            $table->timestamp('approved_at');
            $table->timestamps();

            $table->unique('process_id');
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
