<?php

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

ini_set("memory_limit", "128M");

use RydlabTools\DevTools;

$loader = require_once __DIR__ . '/vendor/autoload.php';
$loader->addPsr4('RydlabTools\\', __DIR__ . '/src');


$devTools = new DevTools();
$devTools->connect();

$devTools->Network->enable();
$devTools->Runtime->enable();

$devTools->Page->navigate(['url' => 'https://www.logo.ru/shop/televizory-audio-video/televizori/']);


for($i = 0; $i < 10; $i++) {
    $events = $devTools->waitEvent("Page.loadEventFired", 7);

    $devTools->Runtime->evaluate(
        ['expression' => 'document.getElementsByClassName("justify").scrollIntoView()']
    );

    $data[$i] = $devTools->Page->captureScreenshot(['format' => 'jpeg', 'quality' => 100 ]);
    if( file_put_contents(__DIR__ . '/result/test_' . $i . '.png',base64_decode($data[$i]['result']['data']))) {
        echo 'Saved: ' . __DIR__ . '/result/test_' . $i . '.png' . PHP_EOL;
    }

    $devTools->Runtime->evaluate(
        ['expression' => 'document.querySelector(".pageNav a.next").click()']
    );
}

exit(0);