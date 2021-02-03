<?php

declare(strict_types=1);

namespace Asgrim\HaPhp;

final class WanPortCheck
{
    /** @var string */
    private $gatewayIp;

    /** @var array<string,string> */
    private $wanGateways;

    public function __construct(string $gatewayIp, array $wanGateways)
    {
        $this->gatewayIp = $gatewayIp;
        $this->wanGateways = $wanGateways;
    }

    public function __invoke(): string
    {
        exec('traceroute -m4 8.8.8.8 2>&1', $output, $result);

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
