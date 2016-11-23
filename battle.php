<?php

require 'vendor/autoload.php';

// https://www.cscpro.org/secura/battle/3485.json
// https://secura.e-sim.org/apiFights.html?battleId=1&roundId=15

$server = isset($argv[1]) ? $argv[1] : 'secura';

$final = isset($argv[2]) ? intval($argv[2]) : 33000;

$indexPath = __DIR__."/{$server}/battles/index";

$client = new GuzzleHttp\Client();

do {
    $index = intval(trim(file_get_contents($indexPath)));

    if (isset($argv[3])) {
        $index = intval($argv[3]);

        unset($argv[3]);
    }

    file_put_contents($indexPath, $index + 1, LOCK_EX);

    echo "Fetching {$server} {$index}...".PHP_EOL;

    $tries = 0;

download:

    try {
        $battle = json_decode($client->get("https://www.cscpro.org/{$server}/battle/{$index}.json")->getBody()->getContents(), true);

        $promises = [];

        for ($i = 1; $i <= $battle['round']; ++$i) {
            $promises[$i] = $client->getAsync("https://{$server}.e-sim.org/apiFights.html?battleId={$index}&roundId={$i}");
        }

        $results = GuzzleHttp\Promise\unwrap($promises);

        $outputPath = __DIR__."/{$server}/battles/".floor($index/1000)."/{$index}/";

        if (! is_dir($outputPath)) {
            mkdir($outputPath, 0777, true);
        }

        for ($i = 1; $i <= $battle['round']; ++$i) {
            file_put_contents($outputPath.$i, trim($results[$i]->getBody()->getContents()));
        }

        file_put_contents("{$outputPath}0", json_encode($battle));
    } catch (\Exception $e) {
        echo $e->getMessage().PHP_EOL;

        if (++$tries > 10) {
            echo "Fail to fetch {$server} {$index} after 10 times, give up.".PHP_EOL;

            exit(1);
        }
        
        sleep(5);

        echo "Refetching {$server} {$index}...".PHP_EOL;

        goto download;
    }

    sleep(3);
} while ($index++ < $final);

