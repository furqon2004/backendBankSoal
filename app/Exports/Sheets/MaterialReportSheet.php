<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MaterialReportSheet implements FromArray, WithHeadings, WithTitle, WithStyles
{
    public function __construct(
        protected array $data
    ) {}

    public function array(): array
    {
        return array_map(function ($item) {
            return [
                $item['id'],
                $item['title'],
                $item['created_by'],
                $item['questions_count'],
                $item['total_attempts'],
                $item['average_score'],
                $item['is_active'] ? 'Active' : 'Inactive',
                $item['created_at'],
            ];
        }, $this->data);
    }

    public function headings(): array
    {
        return ['ID', 'Title', 'Created By', 'Questions', 'Total Attempts', 'Avg Score', 'Status', 'Created At'];
    }

    public function title(): string
    {
        return 'Material Reports';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
