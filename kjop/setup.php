<?php

namespace UKMNorge\Slack\Kjop;

use UKMNorge\Trello\Trello;
use Exception;

require_once('../config.inc.php');

try {
	APP::validateConstants([
		'TRELLO_CUSTOM_LEVERES'
	]);
} catch (Exception $e) { 
	echo '<h1>FEIL: '. $e->getMessage().'</h1>';

	echo '<h2>BOARD</h2>';
	echo '<pre>';
		var_dump( Trello::getBoard( TRELLO_BOARD_INNKJOP ) );
	echo '</pre>';

	echo '<h2>BOARD CUSTOM FIELDS</h2>';
	echo '<pre>';
		var_dump( Trello::getBoardCustomFields( TRELLO_BOARD_INNKJOP ) );
	echo '</pre>';

	die();
}
var_dump( Trello::getBoardCustomFields( TRELLO_BOARD_INNKJOP ) );

echo 'All OK';