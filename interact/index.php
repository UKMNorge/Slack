<?php

namespace UKMNorge\SlackApp;

use UKMNorge\Slack\API\Response\Interaction;
use UKMNorge\Slack\API\Response\Plugin\FileManager;

$time_start = microtime(true); 
function logTime($text) {
    global $time_start;
    error_log('TIMER: '. $text .' ('. ((microtime(true) - $time_start)) .')');
}

function closeConnection($content_size) {
    logTime('FLUSH AND CLOSE');
    header("Content-Length: $content_size");
    ob_end_flush(); // Strange behaviour, will not work
    flush();            // Unless both are called !
    fastcgi_finish_request(); // important when using php-fpm!
}

header("Connection: close");
ob_start(); 
ignore_user_abort(true);
logTime('OB START');

require_once('../env.inc.php');
ini_set('display_errors', false);

$filemanager = new FileManager( dirname(__DIR__).'/Plugins/');
$interaction = new Interaction(file_get_contents('php://input'));
$filemanager->registerPluginFilters($interaction);

logTime('## Plugins loaded');

// Process and output response
logTime('PROCESS START');
$interaction->process();
logTime('OUTPUT START');
$interaction->output();
logTime('OUTPUT END');

$content_size = ob_get_length();

// Trigger async requests now that we've answered slack (output)
if( $interaction->hasUnprocessedAsyncFilters() ) {
    closeConnection($content_size);
    
    logTime('OB END');
    logTime('ASYNC START');
    $interaction->processAsync();
    logTime('ASYNC END');
} else {
    closeConnection($content_size);
}
logTime('SCRIPT END');
die(); // probably smart since the request is ended?