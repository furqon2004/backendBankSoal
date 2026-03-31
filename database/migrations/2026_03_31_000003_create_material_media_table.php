<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained('materials')->cascadeOnDelete();
            $table->enum('type', ['image', 'audio']);
            $table->string('file_path');
            $table->string('file_url');
            $table->unsignedSmallInteger('page_number')->nullable()
                  ->comment('PDF page this media was extracted from');
            $table->unsignedTinyInteger('order')->default(0)
                  ->comment('Ordering within its type');
            $table->timestamps();

            $table->index(['material_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_media');
    }
};
