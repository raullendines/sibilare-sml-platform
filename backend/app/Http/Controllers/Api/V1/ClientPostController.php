<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Client;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientPostController extends Controller
{
    public function index(Request $request, Client $client): AnonymousResourceCollection
    {
        return PostResource::collection(
            $client->posts()
                ->with(['brand', 'platformPost.platform'])
                ->when($request->query('brand_id'), fn ($query, $brandId) => $query->where('brand_id', $brandId))
                ->latest('created_at')
                ->paginate(50)
        );
    }

    public function show(Client $client, Post $post): PostResource
    {
        abort_unless($post->client_id === $client->id, 404);

        return new PostResource($post->load(['brand', 'platformPost.platform']));
    }
}
