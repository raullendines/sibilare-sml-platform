<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Projects\Actions\SaveProject;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientProjectController extends Controller
{
    public function index(Client $client): AnonymousResourceCollection
    {
        return ProjectResource::collection(
            $client->projects()
                ->with('brands')
                ->withCount(['dashboards', 'extractionConfigs'])
                ->orderBy('name')
                ->get()
        );
    }

    public function store(StoreProjectRequest $request, Client $client, SaveProject $saveProject): ProjectResource
    {
        return new ProjectResource($saveProject->handle($client, $request->validated()));
    }

    public function show(Client $client, Project $project): ProjectResource
    {
        $this->ensureBelongsToClient($project, $client);

        return new ProjectResource($project->load('brands')->loadCount(['dashboards', 'extractionConfigs']));
    }

    public function update(
        UpdateProjectRequest $request,
        Client $client,
        Project $project,
        SaveProject $saveProject,
    ): ProjectResource {
        $this->ensureBelongsToClient($project, $client);

        return new ProjectResource($saveProject->handle($client, $request->validated(), $project));
    }

    private function ensureBelongsToClient(Project $project, Client $client): void
    {
        abort_unless($project->client_id === $client->id, 404);
    }
}
