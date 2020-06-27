<?php

declare(strict_types=1);

namespace Asgrim\HaPhp\YaleClient;

use Laminas\Diactoros\Request;
use Laminas\Diactoros\Uri;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Webmozart\Assert\Assert;

/**
 * Based on domwillcode/yale-smart-alarm-client
 *
 * @link https://github.com/domwillcode/yale-smart-alarm-client/blob/master/yalesmartalarmclient/client.py
 */
class YaleClient
{
    private const HOST = 'https://mob.yalehomesystem.co.uk/yapi';
    private const ENDPOINT_TOKEN = '/o/token/';
    private const ENDPOINT_SERVICE = '/services/';
    private const ENDPOINT_GET_MODE = '/api/panel/mode/';
    private const ENDPOINT_SET_MODE = '/api/panel/mode/';
    private const ENDPOINT_DEVICES_STATUS = '/api/panel/device_status/';
    private const YALE_AUTH_TOKEN = 'VnVWWDZYVjlXSUNzVHJhcUVpdVNCUHBwZ3ZPakxUeXNsRU1LUHBjdTpkd3RPbE15WEtENUJ5ZW1GWHV0am55eGhrc0U3V0ZFY2p0dFcyOXRaSWNuWHlSWHFsWVBEZ1BSZE1xczF4R3VwVTlxa1o4UE5ubGlQanY5Z2hBZFFtMHpsM0h4V3dlS0ZBcGZzakpMcW1GMm1HR1lXRlpad01MRkw3MGR0bmNndQ==';
    private const YALE_AUTHENTICATION_REFRESH_TOKEN = 'refresh_token';
    private const YALE_AUTHENTICATION_ACCESS_TOKEN = 'access_token';
    private const REQUEST_PARAM_AREA = 'area';
    private const REQUEST_PARAM_MODE = 'mode';

    /** @var ClientInterface */
    private $httpClient;
    /** @var string */
    private $username;
    /** @var string */
    private $password;
    /** @var int */
    private $areaId;
    /** @var string|null */
    private $refreshToken;
    /** @var string|null */
    private $accessToken;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        LoggerInterface $logger,
        ClientInterface $httpClient,
        string $username,
        string $password,
        int $areaId = 1
    ) {
        $this->logger = $logger;
        $this->httpClient = $httpClient;
        $this->username = $username;
        $this->password = $password;
        $this->areaId = $areaId;
    }

    private function authorise()
    {
        if ($this->refreshToken !== null) {
            $payload = [
                'grant_type' => 'refresh_token',
                'refresh_token' => $this->refreshToken,
            ];
        } else {
            $payload = [
                'grant_type' => 'password',
                'username' => $this->username,
                'password' => $this->password,
            ];
        }

        $request = (new Request())
            ->withUri(new Uri(self::HOST . self::ENDPOINT_TOKEN))
            ->withHeader('Authorization', 'Basic ' . self::YALE_AUTH_TOKEN)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withMethod('POST');

        $request->getBody()->write(http_build_query($payload));

        $response = $this->httpClient->sendRequest($request);

        $this->logger->debug('Yale response: ' . $response->getBody());

        $responseData = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        if (array_key_exists('error', $responseData)) {
            if ($this->refreshToken !== null) {
                $this->refreshToken = null;
                return $this->authorise();
            }

            throw new RuntimeException(sprintf("Failed to authenticate: %s", json_encode($responseData, JSON_THROW_ON_ERROR)));
        }

        Assert::keyExists($responseData, self::YALE_AUTHENTICATION_REFRESH_TOKEN);
        Assert::keyExists($responseData, self::YALE_AUTHENTICATION_ACCESS_TOKEN);

        $this->refreshToken = $responseData[self::YALE_AUTHENTICATION_REFRESH_TOKEN];
        $this->accessToken = $responseData[self::YALE_AUTHENTICATION_ACCESS_TOKEN];
    }

    private function sendRequestWithAuthenticationRetry(RequestInterface $request, bool $retrying = false)
    {
        $response = $this->httpClient->sendRequest(
            $request
                ->withHeader('Authorization', 'Bearer ' . $this->accessToken)
        );

        $this->logger->debug('Yale response: ' . $response->getBody());

        if ($response->getStatusCode() === 403 && !$retrying) {
            $this->authorise();
            return $this->sendRequestWithAuthenticationRetry($request, true);
        }

        Assert::greaterThanEq($response->getStatusCode(), 200);
        Assert::lessThanEq($response->getStatusCode(), 299);

        return $response;
    }

    private function getAuthenticated(string $endpoint, bool $retrying = false)
    {
        $response = $this->sendRequestWithAuthenticationRetry(
            (new Request())
                ->withUri(new Uri(self::HOST . $endpoint))
                ->withMethod('GET')
        );

        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function deviceStatuses()
    {
        return $this->getAuthenticated(self::ENDPOINT_DEVICES_STATUS)['data'];
    }

    public function parseDeviceStatuses(array $item)
    {
        $statuses = array_unique(array_values(array_filter(array_map(
            static function (string $key) use ($item) : ?string {
                return $item[$key] === '' ? null : $item[$key];
            },
            ['status1', 'status2', 'status_switch', 'status_power', 'status_temp', 'status_humi', 'status_dim_level', 'status_lux', 'status_hue', 'status_saturation']
        ))));
        if (count($statuses) === 0) {
            $statuses[] = 'normal';
        }

        return array_map(
            function (string $yaleStatus) : string {
                switch ($yaleStatus) {
                    case 'device_status.dc_close':
                        return 'closed';
                    case 'device_status.dc_open':
                        return 'open';
                    case 'device_status.low_battery':
                        return 'low_battery';
                    case 'device_status.off':
                        return 'off';
                    case 'device_status.on':
                        return 'on';
                    case 'normal':
                        return 'normal';
                    default:
                        $this->logger->info("Unknown device status found: " . $yaleStatus);
                        return $yaleStatus;
                }
            },
            $statuses
        );
    }
}
