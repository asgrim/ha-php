<?php /** @noinspection UnusedFunctionResultInspection */
declare(strict_types=1);

namespace Asgrim\HaPhp;

use Asgrim\HaPhp\HaClient\HaClient;
use Asgrim\HaPhp\PelletPrice\FetchPelletPrice;
use Asgrim\HaPhp\YaleClient\YaleClient;
use Http\Client\Curl\Client;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

require_once __DIR__ . '/../vendor/autoload.php';

$application = new Application();

$application->add(new class extends Command {
    protected static $defaultName = 'app:test';

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $logger = new Logger('ha-php');
        $logger->pushHandler(new ErrorLogHandler(
            ErrorLogHandler::OPERATING_SYSTEM,
            getenv('LOG_LEVEL') !== false
                ? getenv('LOG_LEVEL')
                : LogLevel::DEBUG
        ));

        $httpClient = new Client();

        $yale = new YaleClient(
            $logger,
            $httpClient,
            getenv('YALE_USERNAME'),
            getenv('YALE_PASSWORD')
        );

        $homeAssistant = new HaClient(
            $httpClient,
            getenv('HA_TOKEN'),
            getenv('HA_URL'),
            $logger
        );

        $wanPortCheck = new WanPortCheck(
            $logger,
            getenv('GATEWAY_IP'),
            json_decode(getenv('WAN_GATEWAYS'), true, 512, JSON_THROW_ON_ERROR),
            (int) getenv('TRACE_HOPS')
        );

        $fetchPelletPrice = new FetchPelletPrice(
            $httpClient,
            $logger,
            getenv('PELLET_PRODUCT_URL'),
            getenv('PELLET_SELECTOR')
        );

        $pelletRequestFrequency = (int)getenv('PELLET_REQUEST_FREQUENCY_SECS');
        if ($pelletRequestFrequency <= 0) {
            $pelletRequestFrequency = 21600;
        }

        $interval = (int)getenv('INTERVAL');
        if ($interval <= 0) {
            $interval = 60;
        }

        $lastPelletPriceCheck = 0;

        while(true) {
            try {
                $yaleStatus = $yale->deviceStatuses();
                array_walk(
                    $yaleStatus,
                    static function (array $deviceStatus) use ($homeAssistant, $yale) {
                        $statusCodes = $yale->parseDeviceStatuses($deviceStatus);
                        $sensorName = 'sensor.asgrim_yale_sensor_' . preg_replace('/\W+/', '', str_replace(' ', '_', strtolower($deviceStatus['name'])));
                        $homeAssistant->setState(
                            $sensorName,
                            reset($statusCodes),
                            [
                                'type' => $deviceStatus['type'],
                                'name' => $deviceStatus['name'],
                                'status_codes' => implode(',', $statusCodes),
                            ]
                        );
                    }
                );

                $wanPortValue = $wanPortCheck();
                $logger->info('WAN port value: ' . $wanPortValue);
                $homeAssistant->setState(
                    'sensor.asgrim_wan_port',
                    $wanPortValue,
                    []
                );

                $now = time();
                $timeSinceLastPelletPriceCheck = $now - $lastPelletPriceCheck;
                if ($timeSinceLastPelletPriceCheck > $pelletRequestFrequency) {
                    $lastPelletPriceCheck = $now;
                    $pelletPrice = $fetchPelletPrice();
                    $logger->info('Current pellet price: ' . $pelletPrice);
                    $homeAssistant->setState(
                        'sensor.asgrim_pellet_price',
                        $pelletPrice,
                        [
                            'name' => 'Biomass pellet price',
                            'device_class' => 'monetary',
                            'unit_of_measurement' => '£',
                            'icon_template' => 'mdi:pine-tree-fire',
                        ]
                    );
                } else {
                    $logger->debug(sprintf('Skipping pellet price check, last check was %d seconds ago', $timeSinceLastPelletPriceCheck));
                }
            } catch (\Throwable $t) {
                $logger->critical('Uncaught exception: ' . $t->getMessage(), ['exception' => $t]);
            } finally {
                $logger->info('Interval done, sleeping.');
                sleep($interval);
            }
        }

        return Command::SUCCESS;
    }
});

$application->setDefaultCommand('app:test');

$application->run();
