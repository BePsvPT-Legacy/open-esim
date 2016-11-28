<?php

require __DIR__.'/boot.php';

$client = new GuzzleHttp\Client();

do {
    $climate->out("Fetching {$server} {$index}...");

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
        $climate->to('error')->red($e->getMessage());

        if (++$tries > 10) {
            $climate->to('error')->red("Fail to fetch {$server} {$index} after 10 times, give up.");

            exit(1);
        }
        
        sleep(5);

        $climate->yellow("Refetching {$server} {$index}...");

        goto download;
    }

    sleep(3);
} while (++$index < $endAt);
