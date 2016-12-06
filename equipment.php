<?php

define('STEP', 250);

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

function equipment_info($html)
{
    $info = [];

    foreach (Sunra\PhpSimple\HtmlDomParser::str_get_html(strtolower($html))->find('img') as $image) {
        $equipment = Sunra\PhpSimple\HtmlDomParser::str_get_html($image->getAttribute('title'));

        $temp = explode(' ', trim($equipment->find('b', 0)->text()));
        $temp2 = $equipment->find('p');

        $info[] = [
            'id' => retrieve_id(trim($equipment->find('bdo', 0)->text()), '#'),
            'quality' => $temp[0],
            'slot' => $temp[1],
            'parameters' => [parameter_info($temp2[0]->text()), parameter_info($temp2[1]->text())],
        ];
    }

    return json_encode($info);
}

function request_body($begin)
{
    $content = '';

    for ($i = $begin; $i < $begin + STEP; ++$i) {
        $content .= "[equipment]{$i}[/equipment]";
    }

    return $content;
}

$client = new GuzzleHttp\Client();

do {
    $climate->out("Fetching {$server} {$index}...");

    $tries = 0;

    download:

    try {
        $promises = [];

        for ($i = $index; $i < $index + 5 * STEP; $i += STEP) {
            $promises[] = $client
                ->postAsync("https://{$server}.e-sim.org/previewMessage.html", [
                    'form_params' => ['title' => 'abc', 'body' => request_body($i)]
                ])
                ->then(function (GuzzleHttp\Psr7\Response $response) use ($server, $i) {
                    $outputPath = __DIR__."/{$server}/equipments/".floor($i / 100000)."/";

                    if (! is_dir($outputPath)) {
                        mkdir($outputPath, 0777, true);
                    }

                    $filename = sprintf('%s%d-%d.json', $outputPath, $i, $i + STEP - 1);

                    file_put_contents($filename, equipment_info($response->getBody()->getContents()), LOCK_EX);
                });
        }

        GuzzleHttp\Promise\settle($promises)->wait();
    } catch (\Exception $e) {
        $climate->to('error')->red($e->getMessage());

        if (++$tries > 10) {
            $climate->to('error')->red("Fail to fetch {$server} {$index} after 10 times, give up.");

            exit(1);
        }

        sleep(5 + $tries ^ 2);

        $climate->yellow("Refetching {$server} {$index}...");

        goto download;
    }

    sleep(3);

    $index += 5 * STEP;
} while ($index < $endAt);
