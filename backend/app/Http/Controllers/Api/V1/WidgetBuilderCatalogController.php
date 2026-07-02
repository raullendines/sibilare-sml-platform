<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Dashboards\Actions\BuildWidgetBuilderCatalog;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class WidgetBuilderCatalogController extends Controller
{
    public function __invoke(BuildWidgetBuilderCatalog $buildWidgetBuilderCatalog): JsonResponse
    {
        return response()->json([
            'data' => $buildWidgetBuilderCatalog->handle(),
        ]);
    }
}
