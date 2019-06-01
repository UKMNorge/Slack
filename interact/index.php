<?php

namespace UKMNorge\Slack\Interact;

use UKMNorge\Slack\Kjop\Response;
use UKMNorge\Slack\Kjop\APP;
use UKMNorge\Trello\Trello;

require_once('../config.inc.php');

$data = json_decode( $_POST['payload'] );
APP::setAPITokenFromTeamId( $data->team->id );

$selected = 'Ukjent butikk';
foreach( $data->actions as $action ) {
	if( $action->name == 'fra' ) {
		$selected_list = $action->selected_options[0]->value;
		break;
	}
}

if( strpos( $selected_list, 'new-' ) === 0 ) {
	$liste = Trello::createList( str_replace('new-','',$selected_list) );
	$selected_list = $liste->id;
} else {
	$liste = Trello::getListById( $selected_list );
}

$card_id = str_replace('kjop_fra_', '', $data->callback_id);
Trello::moveCard( $card_id, $selected_list );

$response = new Response();

$message = $data->original_message;
foreach( $message->attachments as $attachment ) {
	foreach( $attachment->fields as $field ) {
		switch( $field->title ) {	
			case Response::labelTil():
				$response->setTil( $field->value );
				break;
	
			case Response::labelBeskrivelse():
				$response->setBeskrivelse( $field->value );
				break;
		}
	}
	
	$response->setText('Thanks! '. $attachment->fallback .' er lagt til på '. $liste->name .'-listen.');
	$response->setNavn( $attachment->fallback );
	$response->setFra( $liste->name );
}

/**
 * OUTPUT STUFF
**/
header('Content-Type: application/json');
echo $response->renderToJSON();


echo 'Flytt '. $card_id .' til '. $selected;