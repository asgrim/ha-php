<?php

declare(strict_types=1);

namespace Asgrim\HaPhp\PelletPrice;

use Http\Client\Curl\Client;
use Laminas\Diactoros\Request;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Webmozart\Assert\Assert;

class FetchPelletPrice
{
    /** @var Client */
    private $client;
    /** @var LoggerInterface */
    private $logger;
    /** @var string */
    private $productUrl;
    /** @var string */
    private $xpath;

    public function __construct(ClientInterface $httpClient, LoggerInterface $logger, string $productUrl, string $xpath)
    {
        $this->client = $httpClient;
        $this->logger = $logger;
        $this->productUrl = $productUrl;
        $this->xpath = $xpath;
    }

    public function __invoke(): ?string
    {
        $productHtmlResponse = $this->client->sendRequest(new Request(
            $this->productUrl,
            'GET'
        ));

        Assert::same(200, $productHtmlResponse->getStatusCode());

        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML((string) $productHtmlResponse->getBody());
        libxml_use_internal_errors(false);

        $xpath = new \DOMXPath($doc);
        $elements = $xpath->query($this->xpath);

        if (! $elements->length) {
            $this->logger->warning(sprintf('Price xpath "%s" did not find any elements on %s', $this->xpath, $this->productUrl));
            return null;
        }

        if ($elements->length > 1) {
            $this->logger->warning(sprintf('Price xpath "%s" found %d elements on %s', $this->xpath, $elements->length, $this->productUrl));
            return null;
        }

        return str_replace("£", "", (string) $elements->item(0)->textContent);
    }
}
