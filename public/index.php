<?php

declare(strict_types=1);

$pdo = new PDO(getenv('DSN'));

$ndays = 7;

if (array_key_exists('days', $_GET)) {
    $d = (int) $_GET['days'];
    if ($d > 0 && $d < 365) {
        $ndays = $d;
    }
}

echo ' <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet"> ';
echo "<style>html { font-family: 'Roboto', sans-serif; }</style>";
echo "<h3>$ndays days history</h3>";

$since = new DateTimeImmutable($ndays . ' days ago');

$s = $pdo->prepare("SELECT state, created FROM states WHERE entity_id = 'binary_sensor.internet_connectivity_8_8_8_8' AND created >= :since ORDER BY created ASC");
$s->execute([
    'since' => $since->format('Y-m-d H:i:s'),
]);
$results = $s->fetchAll(PDO::FETCH_ASSOC);

$state = 'off';
$stateChangeTime = null;
$stateChanges = [];

array_walk(
    $results,
    static function (array $item) use (&$state, &$stateChanges, &$stateChangeTime) : void {
        if ($item['state'] !== $state) {
            $time = new DateTimeImmutable($item['created']);

            $downFor = null;
            if ($stateChangeTime !== null) {
                $downFor = $time->diff($stateChangeTime);
            }

            $stateChanges[] = ['state' => $item['state'], 'time' => $time, 'downFor' => $downFor];
            $state = $item['state'];
            $stateChangeTime = $time;
        }
    }
);

echo "<style>.state-off { color: red; } .state-on { color: darkgreen; }</style>";
echo "<ul><li>";
echo implode('</li><li>', array_map(static function ($item) {
    return sprintf(
        '%s - <span class="state-%s">%s</span> %s %s',
        $item['time']->format('d/m/Y H:i:s'),
        $item['state'],
        $item['state'] === 'on' ? 'Connected' : 'Disconnected',
        $item['downFor'] === null
            ? ''
            : (
                $item['state'] === 'off' ? ' - up for' : ' - down for'
            ),
        $item['downFor'] === null
            ? ''
            : $item['downFor']->format('%ad %Hh %Im %Ss')
    );
}, $stateChanges));
echo "</li></ul>";


echo '<p style="color: grey;">Query parameters: days=[int] details=true</p>';

if (array_key_exists('details', $_GET) && $_GET['details'] === 'true') {
    echo implode('<br />', array_map(static function (array $item): string {
        return sprintf(
            '%s - %s',
            $item['created'],
            $item['state']
        );
    }, $results));
}
