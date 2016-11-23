<?php

function files($path) {
    $handle = opendir($path);

    if (false === $handle) {
        echo "Error: {$path}".PHP_EOL;

        exit(1);
    }

    $files = [];

    while (false !== ($entry = readdir($handle))) {
        if (! in_array($entry, ['.', '..', 'index'], true)) {
            $files[] = $entry;
        }
    }

    closedir($handle);

    return $files;
}

$server = isset($argv[1]) ? $argv[1] : 'secura';

$path = __DIR__."/{$server}/battles";

foreach (files($path) as $entry) {
    foreach (files("{$path}/{$entry}") as $index) {
        $info = json_decode(file_get_contents("{$path}/{$entry}/{$index}/0"), true);

        if (! isset($info['round'])) {
            if (isset($argv[2])) {
                shell_exec("php battle.php {$server} {$index} {$index}");
            } else {
                echo 'Fail: '.$index.PHP_EOL;
            }

            continue;
        }

        for ($i = 1; $i <= $info['round']; ++$i) {
            $passed = true;

            if (! is_file("{$path}/{$entry}/{$index}/{$i}")) {
                $passed = false;
            } else {
                $size = filesize("{$path}/{$entry}/{$index}/{$i}");

                if (false === $size || 0 === $size) {
                    $passed = false;
                }
            }

            if (! $passed) {
                if (isset($argv[2])) {
                    shell_exec("php battle.php {$server} {$index} {$index}");

                    break;
                } else {
                    echo "Fail: {$index} - {$i}".PHP_EOL;
                }
            }

        }
    }
}

