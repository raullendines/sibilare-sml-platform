<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Posts\Actions\FilterClientPosts;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListClientPostsRequest;
use App\Http\Resources\PostResource;
use App\Models\Client;
use App\Models\Post;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientPostController extends Controller
{
    public function index(
        ListClientPostsRequest $request,
        Client $client,
        FilterClientPosts $filterClientPosts,
    ): AnonymousResourceCollection {
        $filters = $request->filters();

        return PostResource::collection(
            $filterClientPosts
                ->handle($client, $filters)
                ->paginate($filters['per_page'] ?? 50)
        );
    }

    public function show(Client $client, Post $post): PostResource
    {
        abort_unless($post->client_id === $client->id, 404);

        return new PostResource($post->load(['brand', 'platformPost.platform']));
    }
}
