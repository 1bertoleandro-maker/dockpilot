<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extractions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('model');
            $table->string('prompt_version');
            $table->json('payload_json');
            $table->json('confidence_json')->nullable();
            $table->string('status');
            $table->json('raw_response_meta')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'version']);
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extractions');
    }
};
