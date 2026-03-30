<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UserReportSheet implements FromArray, WithHeadings, WithTitle, WithStyles
{
    public function __construct(
        protected array $data
    ) {}

    public function array(): array
    {
        return array_map(function ($item) {
            return [
                $item['id'],
                $item['name'],
                $item['email'],
                $item['total_attempts'],
                $item['average_score'],
                $item['highest_score'],
                $item['lowest_score'],
            ];
        }, $this->data);
    }

    public function headings(): array
    {
        return ['ID', 'Name', 'Email', 'Total Attempts', 'Avg Score', 'Highest Score', 'Lowest Score'];
    }

    public function title(): string
    {
        return 'User Reports';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
