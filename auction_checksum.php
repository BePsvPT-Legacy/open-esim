<?php

define('NO_INDEX', true);

require_once __DIR__.'/boot.php';

$filesystem = new Illuminate\Filesystem\Filesystem;

$keys = ['seller_id', 'bidder_id', 'item', 'price', 'bidders', 'history'];

foreach ($filesystem->directories(__DIR__."/{$server}/auctions") as $directory) {
    foreach ($filesystem->files($directory) as $auction) {
        $info = json_decode(file_get_contents($auction), true);

        if (! $info) {
            $climate->to('error')->red('Fail: '.$auction);
        } elseif (count(array_diff($keys, array_keys($info))) > 0) {
            $climate->to('error')->red('Fail: '.$auction);
        }
    }
}
