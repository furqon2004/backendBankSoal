<?php

namespace Database\Seeders;

use App\Models\Material;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Create Admin User ───────────────────────────────────────────
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@banksoal.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        // ── Create Regular Users ────────────────────────────────────────
        $user1 = User::create([
            'name' => 'User Demo',
            'email' => 'user@banksoal.com',
            'password' => 'password',
            'role' => 'user',
        ]);

        User::factory(5)->create(['role' => 'user']);

        // ── Create Sample Material with Questions ───────────────────────
        $material = Material::create([
            'title' => 'Dasar-dasar Pemrograman PHP',
            'content' => 'PHP (Hypertext Preprocessor) adalah bahasa pemrograman server-side yang dirancang untuk pengembangan web. PHP dapat dijalankan di berbagai platform seperti Windows, Linux, dan macOS. PHP mendukung berbagai database seperti MySQL, PostgreSQL, dan SQLite. Variabel dalam PHP dimulai dengan tanda dollar ($). PHP memiliki tipe data seperti string, integer, float, boolean, array, dan object. Fungsi dalam PHP dideklarasikan menggunakan keyword "function". PHP mendukung pemrograman berorientasi objek (OOP) dengan konsep class, object, inheritance, encapsulation, dan polymorphism. Laravel adalah framework PHP yang populer untuk membangun aplikasi web modern.',
            'created_by' => $admin->id,
            'is_active' => true,
        ]);

        // Create sample questions for the material
        $questions = [
            [
                'question_text' => 'Apa kepanjangan dari PHP?',
                'option_a' => 'Personal Home Page',
                'option_b' => 'Hypertext Preprocessor',
                'option_c' => 'Programming Hypertext Protocol',
                'option_d' => 'Pre Hypertext Processor',
                'correct_answer' => 'b',
                'explanation' => 'PHP awalnya singkatan dari Personal Home Page, tetapi sekarang secara resmi merupakan singkatan rekursif dari PHP: Hypertext Preprocessor.',
            ],
            [
                'question_text' => 'Tanda apa yang digunakan untuk memulai variabel di PHP?',
                'option_a' => '@',
                'option_b' => '#',
                'option_c' => '$',
                'option_d' => '&',
                'correct_answer' => 'c',
                'explanation' => 'Dalam PHP, semua variabel dimulai dengan tanda dollar ($), contohnya $nama, $umur.',
            ],
            [
                'question_text' => 'Keyword apa yang digunakan untuk mendeklarasikan fungsi di PHP?',
                'option_a' => 'def',
                'option_b' => 'func',
                'option_c' => 'method',
                'option_d' => 'function',
                'correct_answer' => 'd',
                'explanation' => 'PHP menggunakan keyword "function" untuk mendeklarasikan fungsi, contoh: function namaFungsi() {}',
            ],
            [
                'question_text' => 'Framework PHP apa yang disebutkan dalam materi?',
                'option_a' => 'Django',
                'option_b' => 'Laravel',
                'option_c' => 'Spring',
                'option_d' => 'Express',
                'correct_answer' => 'b',
                'explanation' => 'Laravel adalah framework PHP yang populer untuk membangun aplikasi web modern.',
            ],
            [
                'question_text' => 'Manakah yang BUKAN merupakan tipe data PHP?',
                'option_a' => 'string',
                'option_b' => 'integer',
                'option_c' => 'character',
                'option_d' => 'boolean',
                'correct_answer' => 'c',
                'explanation' => 'PHP tidak memiliki tipe data "character" secara terpisah. PHP memiliki string, integer, float, boolean, array, dan object.',
            ],
        ];

        foreach ($questions as $q) {
            Question::create(array_merge($q, ['material_id' => $material->id]));
        }

        // Create another material with factory questions
        $material2 = Material::create([
            'title' => 'Pengantar Database SQL',
            'content' => 'SQL (Structured Query Language) adalah bahasa yang digunakan untuk mengelola database relasional. Perintah SQL dibagi menjadi DDL (Data Definition Language) seperti CREATE, ALTER, DROP; DML (Data Manipulation Language) seperti SELECT, INSERT, UPDATE, DELETE; dan DCL (Data Control Language) seperti GRANT dan REVOKE. JOIN digunakan untuk menggabungkan data dari dua atau lebih tabel berdasarkan kolom yang terkait. Jenis JOIN meliputi INNER JOIN, LEFT JOIN, RIGHT JOIN, dan CROSS JOIN. Index digunakan untuk mempercepat pencarian data dalam tabel.',
            'created_by' => $admin->id,
            'is_active' => true,
        ]);

        Question::factory(25)->create(['material_id' => $material2->id]);
    }
}
