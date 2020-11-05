<?php

include 'madeline.php';
use danog\MadelineProto\Logger;

set_time_limit(10000);
error_reporting(E_ALL);
$BASE_PATH = __DIR__ . '/../../..';
$NOW = time();
$MEDIA_PATH = '/wp-content/uploads/telepa/'.$NOW.'/';
const MAX_FILE_SIZE = 150000000;

$MANELINE_SETTINGS = [
    'logger' => [
        'logger_level' => Logger::ERROR,
        //'logger_level' => Logger::VERBOSE,
    ]
];

$MadelineProto = new \danog\MadelineProto\API('session.madeline', $MANELINE_SETTINGS);
$MadelineProto->start();
//$MadelineProto->async(false);
$res = $MadelineProto->channels->joinChannel(['channel' => '@imeni_menya']);
//print('RES: '); var_dump($res); print("\n");
$messages = $MadelineProto->messages->getHistory(['peer' => '@imeni_menya', 'offset_id' => 0, 'offset_date' => 0, 'add_offset' => 0, 'limit' => 10, 'max_id' => 0, 'min_id' => 0  ]);
print('MESSAGES: '.count($messages['messages'])); print("\n");
//var_dump($messages);print("\n");
