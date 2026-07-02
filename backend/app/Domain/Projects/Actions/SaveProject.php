<?php

namespace App\Domain\Projects\Actions;

use App\Models\Client;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaveProject
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Client $client, array $data, ?Project $project = null): Project
    {
        return DB::transaction(function () use ($client, $data, $project): Project {
            $brandIds = array_key_exists('brand_ids', $data) ? $data['brand_ids'] : null;
            unset($data['brand_ids']);

            if ($project === null) {
                $data['slug'] = $data['slug'] ?? $this->uniqueSlug($client, $data['name']);
                $data['status'] = $data['status'] ?? 'active';
                $project = $client->projects()->create($data);
            } else {
                $project->fill($data);
                $project->save();
            }

            if (is_array($brandIds)) {
                $pivot = collect($brandIds)->mapWithKeys(fn (string $brandId) => [
                    $brandId => ['client_id' => $client->id, 'created_at' => now()],
                ])->all();
                $project->brands()->sync($pivot);
            }

            return $project->refresh()->load(['brands'])->loadCount(['dashboards', 'extractionConfigs']);
        }, 3);
    }

    private function uniqueSlug(Client $client, string $name): string
    {
        $base = Str::slug($name) ?: 'project';
        $slug = $base;
        $suffix = 2;

        while ($client->projects()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
