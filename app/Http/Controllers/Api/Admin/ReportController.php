<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminService;
use App\Services\ExportService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AdminService $adminService,
        protected ExportService $exportService
    ) {}

    /**
     * Get dashboard statistics.
     */
    public function dashboard(): JsonResponse
    {
        $stats = $this->adminService->dashboardStats();

        return $this->success($stats, 'Dashboard statistics retrieved');
    }

    /**
     * Get per-material reports.
     */
    public function materials(): JsonResponse
    {
        $reports = $this->adminService->materialReports();

        return $this->success($reports, 'Material reports retrieved');
    }

    /**
     * Get per-user performance reports.
     */
    public function users(): JsonResponse
    {
        $reports = $this->adminService->userReports();

        return $this->success($reports, 'User reports retrieved');
    }

    /**
     * Get error analysis (most missed questions).
     */
    public function errors(): JsonResponse
    {
        $reports = $this->adminService->errorReports();

        return $this->success($reports, 'Error reports retrieved');
    }

    /**
     * Export all reports as Excel file.
     */
    public function export()
    {
        return $this->exportService->exportReports();
    }
}
