<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('process_id')->constrained('processes')->cascadeOnDelete();
            $table->string('type');
            $table->string('original_filename');
            $table->string('storage_path');
            $table->string('mime');
            $table->string('checksum')->nullable();
            $table->string('status');
            $table->string('drive_file_id')->nullable();
            $table->string('drive_status')->default('drive_pending');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'process_id', 'type']);
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
