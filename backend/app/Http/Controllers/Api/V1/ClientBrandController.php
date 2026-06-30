<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBrandRequest;
use App\Http\Requests\UpdateBrandRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use App\Models\Client;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientBrandController extends Controller
{
    public function index(Client $client): AnonymousResourceCollection
    {
        return BrandResource::collection(
            $client->brands()
                ->orderBy('brand_type')
                ->orderBy('name')
                ->paginate(50)
        );
    }

    public function store(StoreBrandRequest $request, Client $client): BrandResource
    {
        return new BrandResource(
            $client->brands()->create([
                ...$request->validated(),
                'is_active' => $request->boolean('is_active', true),
            ])
        );
    }

    public function show(Client $client, Brand $brand): BrandResource
    {
        $this->ensureBrandBelongsToClient($brand, $client);

        return new BrandResource($brand);
    }

    public function update(UpdateBrandRequest $request, Client $client, Brand $brand): BrandResource
    {
        $this->ensureBrandBelongsToClient($brand, $client);

        $brand->fill($request->validated());
        $brand->save();

        return new BrandResource($brand->refresh());
    }

    private function ensureBrandBelongsToClient(Brand $brand, Client $client): void
    {
        abort_unless($brand->client_id === $client->id, 404);
    }
}
