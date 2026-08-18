# WaveSpeed AI Bundle

Symfony-first access to WaveSpeedAI video generation with a live model catalog, schema-derived capabilities, exact preflight pricing, uploads, asynchronous predictions, cost limits, and verified webhooks.

The bundle currently ships an offline snapshot of **483 video-producing models** (captured on 2026-08-18) with every model's description, base price, endpoint, required inputs, and complete JSON Schema. The authenticated API can always return the latest catalog and exact price.

## Install

```bash
composer require errogaht/wavespeed-ai-bundle
```

Register `Errogaht\WaveSpeedAiBundle\WaveSpeedAiBundle` if Symfony Flex does not do it for you.

```yaml
# config/packages/wavespeed_ai.yaml
wavespeed_ai:
    api_key: '%env(WAVESPEED_API_KEY)%'
    # A safe project-wide ceiling. Generator refuses higher estimates before POSTing.
    default_max_cost_usd: 1.00
    webhook_secret: '%env(default::WAVESPEED_WEBHOOK_SECRET)%'
```

Never commit either secret. WaveSpeed inputs and generated CDN URLs are temporary (normally seven days), so persist outputs you need in application-owned storage.

## Find the right model

The catalog is designed for humans and AI agents. It distinguishes task type, accepted text/image/video/audio modalities, multi-reference support, native audio controls, resolution/duration ranges, full input schema, and price floor.

```bash
# Cheapest text-to-video candidates
bin/console wavespeed:models --type=text-to-video --input=text --limit=10

# Models accepting text + images + video references
bin/console wavespeed:models --input=text --input=image --input=video --multi-image

# Detailed model purpose, capabilities and every valid parameter
bin/console wavespeed:model skywork-ai/skyreels-v4/reference-to-video
bin/console wavespeed:model skywork-ai/skyreels-v4/reference-to-video --json
```

`base_price_usd` is only a floor. Duration, resolution, sound, output count, and reference inputs can change billing.

## Exact pricing before generation

```bash
bin/console wavespeed:price \
  pruna-ai/p-video/text-to-video \
  '{"prompt":"A slow camera move over a calm lake","duration":1,"resolution":"720p","save_audio":false}'
```

This command calls `POST /api/v3/model/pricing`; it does not create a prediction and costs nothing.

```php
use Errogaht\WaveSpeedAiBundle\Contract\WaveSpeedClientInterface;

$estimate = $client->price($modelId, $inputs);
echo $estimate->formatted(); // USD 0.020000
```

## Text-to-video

Use the generic immutable request when you already know the model's schema:

```php
use Errogaht\WaveSpeedAiBundle\Generation\Generator;
use Errogaht\WaveSpeedAiBundle\Value\GenerationRequest;

$request = GenerationRequest::create('pruna-ai/p-video/text-to-video', [
    'prompt' => 'Cinematic sunrise over a quiet alpine lake, slow dolly forward',
    'duration' => 1,
    'resolution' => '720p',
    'save_audio' => false,
]);

// Pricing is checked first; nothing is submitted above this per-call ceiling.
$prediction = $generator->submit($request, maxCostUsd: 0.03);
$completed = $client->wait($prediction->id);
$videoUrl = $completed->outputs[0];
```

## Images + videos + prompt

Upload local inputs, select a schema-compatible reference model, then let `ModelInputBuilder` map semantic references onto its actual field names:

```php
use Errogaht\WaveSpeedAiBundle\Model\ModelCatalog;
use Errogaht\WaveSpeedAiBundle\Model\ModelInputBuilder;

$image1 = $client->upload('/private/character-front.png');
$image2 = $client->upload('/private/character-side.png');
$motion = $client->upload('/private/motion-reference.mp4');

$model = $catalog->get('skywork-ai/skyreels-v4/reference-to-video');
$request = (new ModelInputBuilder($model))
    ->prompt('Use the person from Image 1 and Image 2, following the movement in Video 1')
    ->referenceImages([$image1->url, $image2->url])
    ->referenceVideos([$motion->url])
    ->option('duration', 3)
    ->option('resolution', '480p')
    ->option('mode', 'fast')
    ->option('sound', false)
    ->request();

$estimate = $client->price($request->modelId, $request->inputs);
$prediction = $generator->submit($request, maxCostUsd: $estimate->amount);
```

The builder refuses unknown options, missing required fields, too many single-value references, and unrecognized media roles before any billable call. For unusual new schemas, inspect `wavespeed:model --json` and use `GenerationRequest` directly.

## Programmatic model selection

```php
use Errogaht\WaveSpeedAiBundle\Enum\InputModality;
use Errogaht\WaveSpeedAiBundle\Model\ModelCriteria;

$candidates = $selector->recommend(new ModelCriteria(
    inputs: [InputModality::Text, InputModality::Image, InputModality::Video],
    maxBasePriceUsd: 0.50,
    multipleImages: true,
), limit: 20);

foreach ($candidates as $model) {
    printf("%s: %s (from $%.4f)\n", $model->id, $model->description, $model->basePriceUsd);
}
```

The selector ranks by base price only. For a final choice, build candidate inputs and compare live `price()` estimates.

## Async predictions and webhooks

Polling starts at two seconds and backs off to ten seconds, as recommended by WaveSpeed. Terminal failures raise a typed exception. A local timeout does not cancel the provider task; it may still finish and be billed.

```php
$submitted = $client->submit($request->withWebhook('https://app.example.com/webhooks/wavespeed'));
$later = $client->result($submitted->id);
```

Verify the raw request body before decoding or dispatching it:

```php
$valid = $verifier->verify($request->getContent(), [
    'webhook-id' => (string) $request->headers->get('webhook-id'),
    'webhook-timestamp' => (string) $request->headers->get('webhook-timestamp'),
    'webhook-signature' => (string) $request->headers->get('webhook-signature'),
]);
```

The verifier checks the WaveSpeed `v3` HMAC-SHA256 format and a five-minute replay window. Return `2xx` quickly, then process asynchronously. Webhook delivery failures do not cancel inference; reconcile with `result()`.

## Service map

- `WaveSpeedClientInterface`: live models, exact price, submit, result, wait, upload.
- `Generator`: mandatory price preflight plus configurable cost ceiling.
- `ModelCatalog`: offline snapshot for deterministic planning.
- `ModelSelector`: capability and budget filtering.
- `ModelInputBuilder`: semantic reference mapping against a model schema.
- `WebhookVerifier`: signed callback verification.
- `GenerationSubmitted`: event emitted after provider acknowledgement.

## Model snapshot maintenance

```bash
WAVESPEED_API_KEY=... php scripts/update-model-catalog.php
```

The updater reads the official authenticated `GET /api/v3/models` endpoint and retains every known video-producing model. Review the resulting diff because models, schemas, descriptions, and price floors can change independently of bundle code.

## Operational boundaries

- A pricing estimate is not a balance reservation and final provider billing is authoritative.
- Never blindly retry a failed/disconnected submission POST. It may already have created a charged prediction.
- Uploaded inputs and generated output URLs are provider-hosted temporary data; treat prompts and media as sensitive.
- The bundle does not download outputs into permanent storage automatically.
- Model licensing and commercial-use terms belong to each upstream provider; the catalog description is not legal advice.
