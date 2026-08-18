<?php

declare(strict_types=1);

namespace Errogaht\WaveSpeedAiBundle\Http;

use Errogaht\WaveSpeedAiBundle\Contract\SleeperInterface;
use Errogaht\WaveSpeedAiBundle\Contract\WaveSpeedClientInterface;
use Errogaht\WaveSpeedAiBundle\Exception\AuthenticationException;
use Errogaht\WaveSpeedAiBundle\Exception\InvalidRequestException;
use Errogaht\WaveSpeedAiBundle\Exception\PollingTimeoutException;
use Errogaht\WaveSpeedAiBundle\Exception\PredictionFailedException;
use Errogaht\WaveSpeedAiBundle\Exception\RateLimitException;
use Errogaht\WaveSpeedAiBundle\Exception\TransportException;
use Errogaht\WaveSpeedAiBundle\Model\ModelDefinition;
use Errogaht\WaveSpeedAiBundle\Value\GenerationRequest;
use Errogaht\WaveSpeedAiBundle\Value\Prediction;
use Errogaht\WaveSpeedAiBundle\Value\PricingEstimate;
use Errogaht\WaveSpeedAiBundle\Value\UploadedMedia;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Official v3 API transport with conservative billing and polling behavior.
 *
 * Submission POSTs are intentionally never retried here: a lost response can
 * still represent an accepted and charged prediction. Result GETs are safe for
 * host-level retry policies because they cannot create new inference work.
 */
final class WaveSpeedClient implements WaveSpeedClientInterface
{
    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ResponseMapper $mapper,
        private readonly SleeperInterface $sleeper,
        private readonly LoggerInterface $logger,
        private readonly array $config,
    ) {
    }

    public function models(bool $videoOnly = false): array
    {
        $payload = $this->request('GET', '/models');
        $rows = \is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $models = [];
        foreach ($rows as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $model = ModelDefinition::fromApi($row);
            if (!$videoOnly || $model->generatesVideo()) {
                $models[] = $model;
            }
        }

        return $models;
    }

    public function price(string $modelId, array $inputs): PricingEstimate
    {
        $payload = $this->request('POST', '/model/pricing', ['json' => ['model_id' => $modelId, 'inputs' => $inputs]]);
        $data = \is_array($payload['data'] ?? null) ? $payload['data'] : [];
        if (!is_numeric($data['unit_price'] ?? null)) {
            throw new TransportException('WaveSpeed pricing response did not contain unit_price.');
        }

        return new PricingEstimate(
            \is_string($data['model_id'] ?? null) ? $data['model_id'] : $modelId,
            (float) $data['unit_price'],
            \is_string($data['currency'] ?? null) ? $data['currency'] : 'USD',
        );
    }

    public function submit(GenerationRequest $request): Prediction
    {
        $path = '/'.ltrim($request->modelId, '/');
        if (null !== $request->webhookUrl) {
            $path .= '?'.http_build_query(['webhook' => $request->webhookUrl]);
        }
        $prediction = $this->mapper->prediction($this->request('POST', $path, ['json' => $request->inputs]));
        $this->logger->info('WaveSpeed prediction submitted.', ['prediction_id' => $prediction->id, 'model' => $request->modelId]);

        return $prediction;
    }

    public function result(string $predictionId): Prediction
    {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $predictionId)) {
            throw new InvalidRequestException('Invalid WaveSpeed prediction ID.');
        }

        return $this->mapper->prediction($this->request('GET', '/predictions/'.$predictionId.'/result'));
    }

    public function wait(string $predictionId, ?float $timeoutSeconds = null): Prediction
    {
        $timeout = $timeoutSeconds ?? (float) $this->config['polling_timeout'];
        $started = microtime(true);
        $interval = (float) $this->config['poll_interval'];
        while (true) {
            $prediction = $this->result($predictionId);
            if ($prediction->status->isTerminal()) {
                if (!$prediction->status->isSuccessful()) {
                    throw new PredictionFailedException(\sprintf('WaveSpeed prediction %s ended with status %s%s.', $prediction->id, $prediction->status->value, null !== $prediction->error ? ': '.$prediction->error : ''));
                }

                return $prediction;
            }
            if (microtime(true) - $started >= $timeout) {
                throw new PollingTimeoutException(\sprintf('WaveSpeed prediction %s did not finish within %.1f seconds; it may still be running.', $predictionId, $timeout));
            }
            $this->sleeper->sleep($interval);
            $interval = min((float) $this->config['max_poll_interval'], max($interval, $interval * 1.5));
        }
    }

    public function upload(string $path): UploadedMedia
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidRequestException(\sprintf('Media file "%s" is not readable.', $path));
        }
        $size = filesize($path);
        if (false === $size || $size > 200_000_000) {
            throw new InvalidRequestException('WaveSpeed uploads must not exceed 200 MB.');
        }
        $form = new FormDataPart(['file' => DataPart::fromPath($path)]);
        $payload = $this->request('POST', '/media/upload/binary', [
            'headers' => $form->getPreparedHeaders()->toArray(),
            'body' => $form->bodyToIterable(),
            'timeout' => (float) $this->config['upload_timeout'],
        ]);
        $data = \is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $url = $data['download_url'] ?? $data['url'] ?? null;
        if (!\is_string($url)) {
            throw new TransportException('WaveSpeed upload response did not contain a media URL.');
        }

        return new UploadedMedia(
            \is_string($data['type'] ?? null) ? $data['type'] : 'unknown',
            $url,
            \is_string($data['filename'] ?? null) ? $data['filename'] : basename($path),
            is_numeric($data['size'] ?? null) ? (int) $data['size'] : $size,
        );
    }

    /** @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $options = []): array
    {
        $headers = (array) ($options['headers'] ?? []);
        $headers['Authorization'] = 'Bearer '.$this->config['api_key'];
        $options['headers'] = $headers;
        $options['timeout'] ??= (float) $this->config['request_timeout'];
        try {
            $response = $this->httpClient->request($method, rtrim((string) $this->config['base_url'], '/').'/'.ltrim($path, '/'), $options);
            $status = $response->getStatusCode();
            $payload = $response->toArray(false);
        } catch (TransportExceptionInterface $exception) {
            throw new TransportException('WaveSpeed transport failed: '.$exception->getMessage(), 0, $exception);
        } catch (DecodingExceptionInterface|\JsonException $exception) {
            throw new TransportException('WaveSpeed returned invalid JSON.', 0, $exception);
        }
        if (401 === $status || 403 === $status) {
            throw new AuthenticationException('WaveSpeed rejected the configured API credentials or account.');
        }
        if (429 === $status) {
            throw new RateLimitException('WaveSpeed rate limit was exceeded.');
        }
        if ($status >= 400 || 200 !== (int) ($payload['code'] ?? 200)) {
            $message = \is_string($payload['message'] ?? null) ? $payload['message'] : 'WaveSpeed API request failed.';
            throw new TransportException(\sprintf('%s (HTTP %d)', $message, $status));
        }

        return $payload;
    }
}
