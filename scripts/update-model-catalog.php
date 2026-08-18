<?php

declare(strict_types=1);

/**
 * Refreshes the reviewed offline catalog from WaveSpeed's authenticated API.
 * The key stays in the environment and is never printed or persisted.
 */

$apiKey = getenv('WAVESPEED_API_KEY');
if (false === $apiKey || '' === $apiKey) {
    fwrite(STDERR, "Set WAVESPEED_API_KEY in the environment.\n");
    exit(1);
}

$context = stream_context_create(['http' => [
    'method' => 'GET',
    'header' => "Authorization: Bearer {$apiKey}\r\nAccept: application/json\r\n",
    'timeout' => 90,
    'ignore_errors' => true,
]]);
$json = file_get_contents('https://api.wavespeed.ai/api/v3/models', false, $context);
if (false === $json) {
    fwrite(STDERR, "Could not download the WaveSpeed model catalog.\n");
    exit(1);
}
$payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
if (200 !== ($payload['code'] ?? null) || !is_array($payload['data'] ?? null)) {
    fwrite(STDERR, "WaveSpeed returned an unexpected model response.\n");
    exit(1);
}

$types = [
    'text-to-video', 'image-to-video', 'video-to-video', 'video-extend',
    'motion-control', 'digital-human', 'audio-to-video', 'portrait-transfer', 'video-effects',
];
$models = array_values(array_filter($payload['data'], static function (mixed $row) use ($types): bool {
    if (!is_array($row)) {
        return false;
    }
    if (in_array($row['type'] ?? null, $types, true)) {
        return true;
    }

    return 'lora-support' === ($row['type'] ?? null)
        && str_contains(strtolower((string) ($row['model_id'] ?? '').' '.(string) ($row['description'] ?? '')), 'video');
}));
usort($models, static fn (array $left, array $right): int => [$left['type'] ?? '', $left['model_id'] ?? ''] <=> [$right['type'] ?? '', $right['model_id'] ?? '']);

$target = dirname(__DIR__).'/resources/video-models.json';
$encoded = json_encode($models, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
if (false === file_put_contents($target, $encoded, LOCK_EX)) {
    fwrite(STDERR, "Could not write {$target}.\n");
    exit(1);
}
fwrite(STDOUT, sprintf("Updated %s with %d video-producing models.\n", $target, count($models)));
