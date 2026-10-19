<?php

namespace App\Llm;

use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\PendingRequest;
use Prism\Prism\Providers\Ollama\Ollama as PrismOllama;
use Psr\Http\Message\RequestInterface;

/**
 * Prism's Ollama provider forwards `think` for text requests but not for
 * structured ones, so a thinking model burns its whole token budget reasoning
 * before it writes any JSON. This adds the flag to every chat request.
 */
class Ollama extends PrismOllama
{
    public function __construct(
        string $apiKey,
        string $url,
        private readonly ?bool $think,
    ) {
        parent::__construct($apiKey, $url);
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<mixed>  $retry
     */
    #[\Override]
    protected function client(array $options = [], array $retry = [], ?string $baseUrl = null): PendingRequest
    {
        $client = parent::client($options, $retry, $baseUrl);

        if ($this->think === null) {
            return $client;
        }

        return $client->withRequestMiddleware($this->addThinkFlag(...));
    }

    private function addThinkFlag(RequestInterface $request): RequestInterface
    {
        if (! str_ends_with($request->getUri()->getPath(), '/api/chat')) {
            return $request;
        }

        $payload = json_decode((string) $request->getBody(), true);

        if (! is_array($payload) || array_key_exists('think', $payload)) {
            return $request;
        }

        $payload['think'] = $this->think;

        return $request->withBody(Utils::streamFor(json_encode($payload)));
    }
}
