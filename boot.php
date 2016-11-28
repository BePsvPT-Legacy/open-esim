<?php

require_once __DIR__.'/vendor/autoload.php';

$climate = new League\CLImate\CLImate;

$server = $climate->green()->radio('Target server:', ['primera', 'secura', 'suna'])->prompt();

if (! defined('NO_INDEX')) {
    $index = intval($climate->green()->input('Begin at:')->prompt());

    $endAt = intval($climate->green()->input('End at:')->prompt());
}
