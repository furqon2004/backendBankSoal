<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->string('pdf_path')->nullable()->after('content');
            $table->boolean('has_audio')->default(false)->after('pdf_path');
            $table->boolean('media_extracted')->default(false)->after('has_audio');
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn(['pdf_path', 'has_audio', 'media_extracted']);
        });
    }
};
