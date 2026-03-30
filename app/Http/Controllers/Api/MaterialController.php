<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MaterialDetailResource;
use App\Http\Resources\MaterialResource;
use App\Services\MaterialService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class MaterialController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected MaterialService $materialService
    ) {}

    /**
     * List all active materials for users.
     */
    public function index(): JsonResponse
    {
        $materials = $this->materialService->list();

        return $this->success(
            MaterialResource::collection($materials)->response()->getData(true),
            'Materials retrieved successfully'
        );
    }

    /**
     * Get a single material with its questions.
     */
    public function show(int $id): JsonResponse
    {
        $material = $this->materialService->find($id);

        return $this->success(
            new MaterialDetailResource($material),
            'Material retrieved successfully'
        );
    }
}
