<?php

namespace App\Services;

use App\Exports\ReportsExport;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportService
{
    public function __construct(
        protected AdminService $adminService
    ) {}

    /**
     * Export reports to Excel.
     */
    public function exportReports(): BinaryFileResponse
    {
        $data = [
            'materials' => $this->adminService->materialReports(),
            'users' => $this->adminService->userReports(),
            'errors' => $this->adminService->errorReports(),
        ];

        $filename = 'bank_soal_report_' . date('Y-m-d_His') . '.xlsx';

        return Excel::download(new ReportsExport($data), $filename);
    }
}
