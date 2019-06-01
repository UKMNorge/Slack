<?php

namespace UKMNorge\Slack\Kjop;

use UKMNorge\Slack\Response as GenericResponse;
use UKMNorge\Slack\Kjop\APP;
use UKMNorge\Trello\Trello;
use \Exception;

require_once('../config.inc.php');

APP::setAPITokenFromTeamId( $_POST['team_id'] );

// INIT REQUEST
try {
	$response = Request::parse();
} catch (Exception $e) {
	$response = new GenericResponse(
		'ephemeral',
		':cry: Beklager, men det mangler litt for mye info. ' .
			'Husk å skrive hva du skal ha, fra hvor og til hvor.' .
			"\r\n" .
			'F.eks: 3stk bananer _fra_ Rema _til_ kontoret'
	);
	$response->renderAndDie();
}

// FIND FROM PARAMETER LIST EQUIVALENT
try {
	Request::findList($response);
} catch (Exception $e) {
	Request::showListAlternatives($response);
}

// FETCH SLACK USER DATA
$user_name = Request::getUserName();

// URL
$url = $response->getBeskrivelse();

// CREATE TRELLO CARD
$card_id = Trello::createCard(
	Request::getListId(),
	$response->getNavn(),
	$response->getBeskrivelse() .
		"\r\n\r\n" . 'LEVERES TIL: ' . $response->getTil() .
		"\r\n\r\n" . 'BESTILT AV: ' . $user_name
);

// UPDATE RESPONSE
$response->getAttachment('kjop')->setCallbackId('kjop_fra_' . $card_id);
$response->getAttachment('kjop')->setFallback($response->getNavn());

// ADD CUSTOM FIELD DATA: LEVERES
Trello::setCustomField( $card_id, TRELLO_CUSTOM_LEVERES, 'text', $response->getTil());

Trello::attachUrl( $card_id, $url);


// OUTPUT TO SLACK CONVERSATION
header('Content-Type: application/json');
echo $response->renderToJSON();

die();