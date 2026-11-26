<?php

declare(strict_types=1);

namespace App\Http\Report\Services;

use App\Http\Report\Repositories\ReportRepository;
use Illuminate\Support\Collection;

/**
 * Coordinates sales report requests.
 *
 * @author Dhammadeep
 */
final class ReportService
{
    public function __construct(private readonly ReportRepository $reports) {}

    /**
     * @return Collection<int, object>
     */
    public function topProducts(string $fromDate, string $toDate): Collection
    {
        return $this->reports->topProducts($fromDate, $toDate);
    }
}
