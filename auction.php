<?php

require __DIR__.'/boot.php';

function retrieve_id($string, $needle = '=')
{
    return intval(substr($string, strpos($string, $needle) + strlen($needle)));
}

function parameter_info($content)
{
    $info = explode(' by ', substr($content, 2));

    return [
        'type' => str_replace(' ', '_', $info[0]),
        'value' => floatval($info[1]),
        'percentage' => str_contains($info[1], '%'),
    ];
}

function history_info($html)
{
    $rows = Sunra\PhpSimple\HtmlDomParser::str_get_html($html)->find('table', 1)->find('tr');

    array_shift($rows);

    $info = [];

    foreach ($rows as $row) {
        $info[] = [
            'bidder_id' => retrieve_id($row->children(0)->find('a', 0)->attr['href']),
            'price' => floatval($row->children(1)->find('b', 0)->text()),
            'time' => \Carbon\Carbon::parse(trim($row->children(2)->text()))->setTimezone('GMT')->toDateTimeString(),
            'timezone' => 'GMT',
        ];
    }

    usort($info, function ($a, $b) {
        return $a['time'] <=> $b['time'];
    });

    return $info;
}

function auction_info($html)
{
    $dom = Sunra\PhpSimple\HtmlDomParser::str_get_html($html)->find('table tr', 1);

    $item = strtolower($dom->children(2)->text());

    if (str_contains($item, 'company')) {
        $temp = explode(' ', trim(strstr($item, '<', true)));

        $item = [
            'type' => 'company',
            'quality' => $temp[0],
            'product' => $temp[1],
        ];
    } elseif (str_contains($item, '#')) {
        $temp = explode(PHP_EOL, trim($item));
        $temp2 = explode(' ', trim($temp[1]));

        $item = [
            'type' => 'equipment',
            'equipment_id' => retrieve_id($temp[0], '#'),
            'quality' => $temp2[0],
            'slot' => $temp2[1],
            'parameters' => [parameter_info($temp[2]), parameter_info($temp[3])],
        ];
    } else {
        $temp = explode('_-_', str_replace(' ', '_', trim($item)));

        $item = [
            'type' => 'special_item',
            'name' => $temp[0],
            'period' => $temp[1] ?? null,
        ];
    }

    $noBid = trim($dom->children(1)->text()) === 'None';

    $info = [
        'seller_id' => retrieve_id($dom->children(0)->find('a', 0)->attr['href']),
        'bidder_id' => $noBid ? null : retrieve_id($dom->children(1)->find('a', 0)->attr['href']),
        'item' => $item,
        'price' => floatval($dom->children(3)->find('b', 0)->text()),
        'bidders' => intval($dom->children(4)->find('b', 0)->text()),
        'history' => $noBid ? [] : history_info($html),
    ];

    return json_encode($info);
}

$client = new GuzzleHttp\Client();

do {
    $climate->out("Fetching {$server} {$index}...");

    $tries = 0;

download:

    try {
        $promises = [];

        for ($i = $index; $i < $index + 5; ++$i) {
            $promises[$i] = $client->getAsync("https://{$server}.e-sim.org/auction.html?id={$i}");
        }

        $results = GuzzleHttp\Promise\unwrap($promises);

        foreach ($results as $key => $result) {
            $outputPath = __DIR__."/{$server}/auctions/".floor($key / 10000)."/";

            if (! is_dir($outputPath)) {
                mkdir($outputPath, 0777, true);
            }

            file_put_contents($outputPath.$key.'.json', auction_info($result->getBody()->getContents()), LOCK_EX);
        }
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

    $index += 5;
} while ($index < $endAt);
