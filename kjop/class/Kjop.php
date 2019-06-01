<?php

namespace UKMNorge\Slack\Kjop;

use UKMNorge\Slack\Response;
use \Exception;

class APP
{
	const TABLE = 'slack_access_token';

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
		} catch (Exception $e) {
			$response = new Response(
				'ephemeral',
				':sob: Beklager, systemet er feil satt opp. Kontakt support@ukm.no'
			);
			$response->renderAndDie();
		}
	}

	public static function setAPITokenFromTeamId( int $team_id)
	{
		$sql = new SQL("SELECT `access_token`
			FROM `#table`
			WHERE `team_id` = '#team'",
			[
				'table' => self::TABLE,
				'team' => $team_id
			]
			);
		$res = $sql->run('field', 'access_token');

		if( !$res ) {
			$response = new Response(
				'ephemeral',
				':sob: Beklager, kan ikke se at teamet ditt er godkjent for bruk av denne appen. Kontakt support@ukm.no'
			);
			$response->renderAndDie();
		}
		
		define('SLACK_ACCESS_TOKEN', $res);

		return true;
	}

	public static function storeAPIAccessToken($data)
	{
		$sql = new SQLins(self::TABLE);
		$sql->add('team_id', $data->team_id);
		$sql->add('team_name', $data->team_name);
		$sql->add('access_token', $data->access_token);
		$sql->add('data', json_encode($data));

		$res = $sql->run();
		 if(!$res) {
			throw new Exception('Kunne ikke lagre data.');
		}
		return true;
	}

	public static function validateConstants($constants)
	{

		foreach ($constants as $constant) {
			if (!defined($constant)) {
				error_log('SETUP: Missing constant  '. $constant);
				throw new Exception(
					'Missing constant  '. $constant,
					181002
				);
			}
		}
	}
}
