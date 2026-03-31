<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->tinyInteger('section')->unsigned()->nullable()->after('material_id')
                  ->comment('1-7: section number');
            $table->string('section_label')->nullable()->after('section');
            $table->string('image_url')->nullable()->after('explanation')
                  ->comment('Extracted image URL for section 1');
            $table->string('audio_url')->nullable()->after('image_url')
                  ->comment('Extracted audio URL for section 6');
            $table->tinyInteger('item_number')->unsigned()->nullable()->after('audio_url')
                  ->comment('Order within the section');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['section', 'section_label', 'image_url', 'audio_url', 'item_number']);
        });
    }
};
