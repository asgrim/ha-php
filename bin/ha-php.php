<?php /** @noinspection UnusedFunctionResultInspection */
declare(strict_types=1);

namespace Asgrim\HaPhp;

use Asgrim\HaPhp\HaClient\HaClient;
use Asgrim\HaPhp\YaleClient\YaleClient;
use Http\Client\Curl\Client;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
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
        $logger->pushHandler(new ErrorLogHandler());

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

        $interval = (int)getenv('INTERVAL');
        if ($interval <= 0) {
            $interval = 60;
        }

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
            } catch (\Throwable $t) {
                $logger->critical('Uncaught exception: ' . $t->getMessage(), ['exception' => $t]);
            } finally {
                sleep($interval);
            }
        }

        return Command::SUCCESS;
    }
});

$application->setDefaultCommand('app:test');

$application->run();
