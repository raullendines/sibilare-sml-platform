<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlatformResource;
use App\Models\Platform;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlatformController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        return PlatformResource::collection(
            Platform::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
        );
    }
}
