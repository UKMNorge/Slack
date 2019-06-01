<?php

namespace UKMNorge\Slack\Kjop;
use UKMNorge\Slack\Response;
use \Exception;

class APP
{
	// Die if one or more constants is missing
	public static function init()
	{
		try {
			self::validateConstants(
				[
					'TRELLO_APP_KEY',
					'TRELLO_APP_TOKEN',
					'TRELLO_BOARD_INNKJOP',
					'SLACK_CLIENT_ID'
				]
			);
		} catch( Exception $e ) {
			$response = new Response(
				'ephemeral',
				':sob: Beklager, systemet er feil satt opp. Kontakt support@ukm.no'
			);
			$response->renderAndDie();
		}
	}

	public static function validateConstants($constants)
	{

		foreach ($constants as $constant) {
			if (!defined($constant)) {
				error_log('SETUP: Missing constant '. $constant);
				throw new Exception(
					'Missing constant '. $constant,
					181002
				);
			}
		}
	}
}