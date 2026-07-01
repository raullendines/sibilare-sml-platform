<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WidgetTemplateResource;
use App\Models\WidgetTemplate;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WidgetTemplateController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        return WidgetTemplateResource::collection(
            WidgetTemplate::query()
                ->with('metric')
                ->where('is_active', true)
                ->orderBy('category')
                ->orderBy('name')
                ->get()
        );
    }
}
