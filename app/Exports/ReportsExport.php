<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ReportsExport implements WithMultipleSheets
{
    public function __construct(
        protected array $data
    ) {}

    public function sheets(): array
    {
        return [
            'Material Reports' => new Sheets\MaterialReportSheet($this->data['materials']),
            'User Reports' => new Sheets\UserReportSheet($this->data['users']),
            'Error Analysis' => new Sheets\ErrorReportSheet($this->data['errors']),
        ];
    }
}
