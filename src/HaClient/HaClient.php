<?php

declare(strict_types=1);

namespace Asgrim\HaPhp\HaClient;

use Http\Client\Curl\Client;
use JsonException;
use Laminas\Diactoros\Request;
use Laminas\Diactoros\Uri;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Webmozart\Assert\Assert;

class HaClient
{
    /** @var Client */
    private $client;
    /** @var string */
    private $haUrl;
    /** @var string */
    private $haToken;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(ClientInterface $httpClient, string $haUrl, string $haToken, LoggerInterface $logger)
    {
        $this->client = $httpClient;
        $this->haUrl = $haUrl;
        $this->haToken = $haToken;
        $this->logger = $logger;
    }

    private function requestFactory(string $method, string $path, ?string $postContent = null) : RequestInterface
    {
        $request = (new Request())
            ->withHeader('Authorization', 'Bearer ' . getenv('HA_TOKEN'))
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withMethod($method)
            ->withUri(new Uri(getenv('HA_URL') . $path));

        if ($postContent !== null) {
            $request->getBody()->write($postContent);
        }

        return $request;
    }

    /**
     * @param array<string,string> $attributes
     * @return array<string,mixed>
     * @throws JsonException
     * @throws \Psr\Http\Client\ClientExceptionInterface
     */
    public function setState(string $entity, string $state, array $attributes = []): array
    {
        Assert::allString(array_keys($attributes));
        Assert::allString($attributes);

        $this->logger->info(sprintf('Setting state of %s to %s', $entity, $state));

        $response = $this->client->sendRequest($this->requestFactory(
            'POST',
            '/api/states/' . $entity,
            json_encode(['state' => $state, 'attributes' => $attributes], JSON_THROW_ON_ERROR)
        ));

        Assert::greaterThanEq($response->getStatusCode(), 200);
        Assert::lessThanEq($response->getStatusCode(), 299);

        return json_decode($response->getBody()->__toString(), true, 512, JSON_THROW_ON_ERROR);
    }
}
