<?php

use UKM\Slack\Response;
use UKM\Slack\KjopResponse;
use UKM\Slack\OptionGroup;
use UKM\Slack\Option;
use UKM\Slack\SelectAction;
use UKM\Slack\API\API;
use UKM\Slack\API\Users;
//use Exception;

require_once('../trello.php');
require_once('../slack/autoload.php');

/**
 * MAP VERDIER FRA STRING TIL VARIABLER
**/
#$preg = "/(([0-9]+)([\wæøåÆØÅ]+\b) )?([\d\wæøåÆØÅ ]*) fra ([\d\wæøåÆØÅ]*) til ([\d\wæøåÆØÅ]*)\s?(\<.*\>)?/";
$preg = "/([0-9\wæøåÆØÅ]+) fra ([\d\wæøåÆØÅ]*) til ([\d\wæøåÆØÅ]*)\s?(\<.*\>)?/";
$message = [];
preg_match( $preg, $_POST['text'], $message );

#$_ANTALL	= $message[2] .' '. $message[3];
$_HVA		= $message[1];
$_FRA		= $message[2];
$_TIL		= $message[3];
$_URL		= $message[4];

if( sizeof( $message ) == 0 ) {
	$response = new Response('ephemeral');
	$response->setText(
		':cry: Beklager, men det mangler litt for mye info. '.
		'Husk å skrive hva du skal ha, fra hvor og til hvor.'.
		"\r\n".
		'F.eks: 3stk bananer _fra_ Rema _til_ kontoret'
	);

	header('Content-Type: application/json');
	echo $response->renderToJSON();
	die();
}

/**
 * PREPARE FROM - BUTIKKEN ALTSÅ
**/
$response = new KjopResponse();
#$response->setAntall( $_ANTALL );
$response->setNavn( $_HVA );
$response->setFra( $_FRA );

/**
 * LOOP ALLE KJENTE BUTIKKER PÅ LETING 
 * ETTER RIKTIG ELLER LIKNENDE BUTIKKER
**/
try {
	// Hent riktig butikk
	$fra_liste = trello::getListByName( $_FRA );
	$response->setFra( $fra_liste->name );
	$response->setText( $response->getNavn() . ' er lagt til på lista!' );
} catch( Exception $e ) {
	$response->setText( $response->getNavn() . ' er lagt til på lista, *men vi trenger litt mer info om butikken.*' );

	// Hent standard-butikk og legg til som card
	$fra_liste = trello::getListByName('Ukjent butikk');

	$optionGroupCreate = new OptionGroup(
		'opprett',
		'Opprett butikken'
	);
	$optionGroupCreate->addOption(
		new Option(
			'new',
			'Opprett `'. $_FRA .'`',
			'new-'. urlencode($_FRA)
		)
	);

	$optionGroupLike = false;
	$optionGroupAll = false;
	// Loop alle lister i trello-boardet
	foreach( trello::getLists() as $liste ) {
		$percent = 0;
		similar_text( strtolower( $_FRA ), strtolower( $liste->name ), $percent );
		
		// LIGNER på det som er skrevet inn
		if( $percent > 50 ) {
			
			// Første som ligner - legg til overskrift
			if( false === $optionGroupLike ) {
				$optionGroupLike = new OptionGroup(
					'lignende',
					'Butikker som ligner på `'. $_FRA .'`'
				);
			}
			$group = 'optionGroupLike';
		}
		// LIGNER IKKE på det som er skrevet inn
		else {
			if( false === $optionGroupAll ) {
				$optionGroupAll = new OptionGroup(
					'alle',
					'Alle registrerte butikker'
				);
			}
			$group = 'optionGroupAll';
		}
		// LEGG TIL butikken i riktig gruppe
		$$group->addOption(
			new Option(
				$liste->id,
				$liste->name,
				$liste->id
			)
		);
	}
	
	$fra_action = new SelectAction(
		'fra',
		'Kjøpes fra'
	);
	$fra_action->addOptionGroup( $optionGroupCreate );
	if( false !== $optionGroupLike ) {
		$fra_action->addOptionGroup( $optionGroupLike );
	}
	if( false !== $optionGroupAll ) {
		$fra_action->addOptionGroup( $optionGroupAll );
	}

	$response->addAction( $fra_action );
}


$response->setTil( $_TIL );
if( !empty( $_URL ) ) {
	$response->setBeskrivelse( $_URL );
}


/**
 * INFO OM BRUKEREN
**/
Users::init('xoxp-8200366342-8200401907-392864114020-af6b60eb8c201eede01f0ea6e326af53');
$user = Users::profileGet( $_POST['user_id'] );

/**
 * OPPRETT KORTET
**/
$beskrivelse = 
	substr( $_URL, 1, strlen( $_URL )-2) .
	"\r\n\r\n". 'LEVERES TIL: '. $_TIL .
	"\r\n\r\n". 'BESTILT AV: '. $user->real_name_normalized;
	
$card_id = trello::createCard(
	$fra_liste->id,
	$_ANTALL .' '. $_HVA,
	$beskrivelse
);
// Oppdater responsen
$response->getAttachment( 'kjop' )->setCallbackId( 'kjop_fra_'. $card_id );
$response->getAttachment( 'kjop' )->setFallback( $response->getNavn() );
/**
 * OUTPUT STUFF
**/
header('Content-Type: application/json');
echo $response->renderToJSON();

die();