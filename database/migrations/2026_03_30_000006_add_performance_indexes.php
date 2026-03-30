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
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->index('status', 'idx_qa_status');
        });

        Schema::table('answers', function (Blueprint $table) {
            $table->index('is_correct', 'idx_ans_is_correct');
            // Index kombinasi untuk error analysis
            $table->index(['question_id', 'is_correct'], 'idx_ans_qid_correct');
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->index('is_active', 'idx_mt_is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropIndex('idx_qa_status');
        });

        Schema::table('answers', function (Blueprint $table) {
            $table->dropIndex('idx_ans_is_correct');
            $table->dropIndex('idx_ans_qid_correct');
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->dropIndex('idx_mt_is_active');
        });
    }
};
