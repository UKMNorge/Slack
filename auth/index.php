<?php

namespace UKMNorge\Slack\Kjop;

use UKMNorge\Slack\API\OAuth;
use UKMNorge\Slack\Kjop\APP;
use Exception;

require_once('../config.inc.php');

// IF WE GOT A CODE, TRY AUTH
if( isset( $_GET['code'] ) ) {
	try {
		$approval = OAuth::access( $_GET['code'] );
		$store = APP::storeAPIAccessToken($approval);
		die('Suksess!');
	} catch( Exception $e ) {
		echo '<h1>Beklager!</h1>'. $e->getMessage();
	}
}

// IF ANYTHING BUT SUCCESS, SHOW BUTTON
echo '<p>'. Oauth::getButton() .'</p>';