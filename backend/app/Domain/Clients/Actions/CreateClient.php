<?php

namespace App\Domain\Clients\Actions;

use App\Models\Client;
use App\Models\Platform;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CreateClient
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Client
    {
        $data['slug'] ??= Str::slug((string) $data['name']);
        $data['status'] ??= 'onboarding';
        $data['default_locale'] ??= 'es-ES';
        $data['timezone'] ??= 'Europe/Madrid';

        return DB::transaction(function () use ($data): Client {
            $client = Client::create($data);

            if (Schema::hasTable('client_subscriptions')) {
                $client->subscription()->create([
                    'default_data_frequency' => 'weekly',
                    'default_retroactive_days' => 3,
                    'default_max_posts_per_period' => 100,
                    'competitor_analysis_enabled' => true,
                    'ai_chatbot_enabled' => false,
                    'ai_pattern_detection_enabled' => false,
                    'client_presentations_enabled' => false,
                    'billing_cycle' => 'monthly',
                ]);
            }

            if (Schema::hasTable('client_platforms') && Schema::hasTable('platforms')) {
                Platform::query()
                    ->where('is_active', true)
                    ->get(['id'])
                    ->each(fn (Platform $platform) => $client->platforms()->create([
                        'platform_id' => $platform->id,
                        'enabled' => true,
                        'enabled_at' => now(),
                    ]));
            }

            return $client;
        });
    }
}
