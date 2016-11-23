<?php

require 'vendor/autoload.php';

// https://www.cscpro.org/secura/battle/3485.json
// https://secura.e-sim.org/apiFights.html?battleId=1&roundId=15

$server = isset($argv[1]) ? $argv[1] : 'secura';

$final = isset($argv[2]) ? intval($argv[2]) : 33000;

$indexPath = __DIR__."/{$server}/battles/index";

$client = new GuzzleHttp\Client();

$step = 15;

do {
    $index = intval(trim(file_get_contents($indexPath)));

    if (isset($argv[3])) {
        $index = intval($argv[3]);

        unset($argv[3]);
    }

    file_put_contents($indexPath, $index + $step, LOCK_EX);

    echo "Fetching {$server} {$index}...".PHP_EOL;

    $tries = 0;

download:

    try {
        $promises = [];

        for ($i = 0; $i < $step; ++$i) {
            $promises[$i] = $client->getAsync("https://www.cscpro.org/{$server}/battle/".($index + $i).'.json');
        }

        $results = GuzzleHttp\Promise\unwrap($promises);

        for ($i = 0; $i < $step; ++$i) {
            file_put_contents(
                __DIR__."/{$server}/battles/".floor(($index + $i) / 1000).'/'.($index + $i).'/0', 
                trim($results[$i]->getBody()->getContents())
            );
        }
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
} while (($index + 1) < $final);

