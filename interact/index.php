<?php

use UKM\Slack\KjopResponse;

require_once('../trello.php');
require_once('../slack/autoload.php');

error_log( 'RETUR-DATA' );
error_log( var_export( $_POST, true ) );

$data = json_decode( $_POST['payload'] );

$selected = 'Ukjent butikk';
foreach( $data->actions as $action ) {
	if( $action->name == 'fra' ) {
		$selected_list = $action->selected_options[0]->value;
		break;
	}
}

if( strpos( $selected_list, 'new-' ) === 0 ) {
	$liste = trello::createList( str_replace('new-','',$selected_list) );
	$selected_list = $liste->id;
} else {
	$liste = trello::getListById( $selected_list );
}

$card_id = str_replace('kjop_fra_', '', $data->callback_id);
trello::moveCard( $card_id, $selected_list );

$response = new KjopResponse();

$message = $data->original_message;
foreach( $message->attachments as $attachment ) {
	foreach( $attachment->fields as $field ) {
		switch( $field->title ) {
	
			case KjopResponse::labelAntall():
				$response->setAntall( $field->value );
				break;
	
			case KjopResponse::labelTil():
				$response->setTil( $field->value );
				break;
	
			case KjopResponse::labelBeskrivelse():
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