<?php

declare(strict_types=1);

namespace Asgrim\HaPhp;

use Psr\Log\LoggerInterface;

final class WanPortCheck
{
    /** @var LoggerInterface */
    private $logger;
    /** @var string */
    private $gatewayIp;
    /** @var array<string,string> */
    private $wanGateways;
    /** @var int */
    private $traceHops;

    public function __construct(LoggerInterface $logger, string $gatewayIp, array $wanGateways, int $traceHops)
    {
        $this->logger = $logger;
        $this->gatewayIp = $gatewayIp;
        $this->wanGateways = $wanGateways;
        $this->traceHops = $traceHops > 0 ? $traceHops : 4;
    }

    public function __invoke(): string
    {
        exec('traceroute -m' . $this->traceHops . ' 8.8.8.8 2>&1', $output, $result);

        if ($result !== 0) {
            throw new \RuntimeException('Failed to do trace: ' . implode($output));
        }

        array_shift($output);

        $parsedHops = array_map(
            static function (string $line): ?string {
                if (! preg_match('/^\s+\d\s+.+?\s\((.+?)\)/', $line, $m)) {
                    return null;
                }

                return $m[1];
            },
            $output
        );

        $this->logger->debug('Traceroute: ' . implode(' > ', $parsedHops));

        $foundGateway = false;
        foreach ($parsedHops as $hop)
        {
            if (!$foundGateway && $hop !== $this->gatewayIp) {
                continue;
            }

            $foundGateway = true;

            if ($hop !== $this->gatewayIp) {
                return array_key_exists($hop, $this->wanGateways)
                    ? sprintf('%s (%s)', $this->wanGateways[$hop], $hop)
                    : $hop;
            }
        }

        return 'unknown';
    }
}
