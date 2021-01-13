<?php

namespace UKMNorge\SlackApp;

use UKMNorge\Slack\App\UKMApp as App;
use UKMNorge\Slack\App\Write as WriteApp;
use Exception;

require_once('../env.inc.php');
require_once('UKMconfig.inc.php');

// IF WE GOT A CODE, TRY AUTH
if( isset( $_GET['code'] ) ) {
	try {
		$approval = App::getOAuthAccessToken( $_GET['code'] );
		$store = WriteApp::storeAPIAccessToken($approval);
		die('Suksess!');
	} catch( Exception $e ) {
		echo '<h1>Beklager!</h1>'. $e->getMessage();
	}
}

// IF ANYTHING BUT SUCCESS, SHOW BUTTON
echo '<p>'. App::getButton() .'</p>';