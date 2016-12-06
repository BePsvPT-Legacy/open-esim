<?php

define('NO_INDEX', true);

require_once __DIR__.'/boot.php';

$filesystem = new Illuminate\Filesystem\Filesystem;

foreach ($filesystem->directories(__DIR__."/{$server}/battles") as $directory) {
    foreach ($filesystem->directories($directory) as $battle) {
        $info = json_decode(file_get_contents("{$battle}/0"), true);

        if (! isset($info['round'])) {
            $climate->to('error')->red('Fail: '.$battle);

            continue;
        }

        for ($i = 1; $i <= $info['round']; ++$i) {
            $passed = true;

            if (! $filesystem->isFile("{$battle}/{$i}")) {
                $passed = false;
            } else {
                $size = $filesystem->size("{$battle}/{$i}");

                if (false === $size || 0 === $size) {
                    $passed = false;
                }
            }

            if (! $passed) {
                $climate->to('error')->red("Fail: {$battle}/{$i}");
            }
        }
    }
}

