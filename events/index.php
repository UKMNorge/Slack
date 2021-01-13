<?php

namespace UKMNorge\SlackApp;

use Exception;
use UKMNorge\Slack\API\Interaction;
use UKMNorge\Slack\App\UKMApp as App;
use UKMNorge\Slack\Plugin\FileManager;

require_once('../env.inc.php');
ini_set('display_errors', true);


$request_body = file_get_contents('php://input');
$data = json_decode($request_body);

error_log('EVENT: ' . var_export($request_body, true));
error_log('POST: ' . var_export($_POST, true));
error_log('DATA: ' . var_export($data, true));

if (empty($request_body)) {
    error_log('INVALID REQUEST');
    header('400 Bad Request');
    echo json_encode(
        [
            'code' => '400',
            'header' => 'bad request',
            'message' => 'Invalid request body'
        ]
    );
    die();
}

App::verifyRequestOrigin($request_body);

header("Content-type: application/json; charset=utf-8");
$output = [];

switch ($data->type) {
    case 'url_verification':
        $output = ['challenge' => $data->challenge];
        break;
}

echo json_encode($output);
die();
