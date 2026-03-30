<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ErrorReportSheet implements FromArray, WithHeadings, WithTitle, WithStyles
{
    public function __construct(
        protected array $data
    ) {}

    public function array(): array
    {
        return array_map(function ($item) {
            return [
                $item->id ?? $item['id'] ?? '',
                $item->material_title ?? $item['material_title'] ?? '',
                $item->question_text ?? $item['question_text'] ?? '',
                $item->correct_answer ?? $item['correct_answer'] ?? '',
                $item->total_answered ?? $item['total_answered'] ?? 0,
                $item->incorrect_count ?? $item['incorrect_count'] ?? 0,
                ($item->error_rate ?? $item['error_rate'] ?? 0) . '%',
                $item->explanation ?? $item['explanation'] ?? '',
            ];
        }, $this->data);
    }

    public function headings(): array
    {
        return ['ID', 'Material', 'Question', 'Correct Answer', 'Total Answered', 'Incorrect Count', 'Error Rate', 'Explanation'];
    }

    public function title(): string
    {
        return 'Error Analysis';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
