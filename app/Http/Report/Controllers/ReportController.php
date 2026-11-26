<?php

declare(strict_types=1);

namespace App\Http\Report\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Report\Requests\TopProductsReportRequest;
use App\Http\Report\Resources\TopProductResource;
use App\Http\Report\Services\ReportService;
use Illuminate\Http\JsonResponse;

/**
 * HTTP endpoints for administrative sales reports.
 *
 * @author Dhammadeep
 */
final class ReportController
{
    public function __construct(private readonly ReportService $reports) {}

    public function topProducts(TopProductsReportRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $products = $this->reports->topProducts($filters['from_date'], $filters['to_date']);

        return ResponseHelper::respond(
            code: 200,
            data: ['products' => TopProductResource::collection($products)->resolve($request)],
            message: 'Top products report generated successfully.',
        );
    }
}
