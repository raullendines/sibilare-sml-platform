<?php

namespace App\Domain\Extraction\Contracts;

use App\Domain\Extraction\Data\NormalizedPlatformPost;
use App\Models\ApifyAgent;
use App\Models\ExtractionConfig;
use App\Models\ExtractionJob;

interface ApifyAgentStrategy
{
    /**
     * @return array<string, mixed>
     */
    public function buildInput(ExtractionConfig $config, ExtractionJob $job, ApifyAgent $agent): array;

    /**
     * @param  array<string, mixed>  $item
     */
    public function normalize(array $item): ?NormalizedPlatformPost;
}
