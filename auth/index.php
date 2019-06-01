<?php

namespace UKMNorge\Slack\Kjop;

use UKMNorge\Slack\API\OAuth;
use SQLins;

use Exception;

require_once('../config.inc.php');
require_once('UKM/sql.class.php');

if( isset( $_GET['code'] ) ) {
	try {
		$approval = OAuth::access( $_GET['code'] );

		$sql = new SQLins('slack_access_token');
		$sql->add('team_id', $approval->team_id);
		$sql->add('team_name', $approval->team_name);
		$sql->add('access_token', $approval->access_token);
		$sql->add('data', json_encode( $approval ));
		
		$res = $sql->run();
		if( !$res ) {
			throw new Exception('Kunne ikke lagre data.');
		} else {
			echo 'Suksess!';
			die();
		}
	} catch( Exception $e ) {
		echo '<h1>Beklager!</h1>'. $e->getMessage();
	}
}


echo '<p>'.
	'<a href="'.
	'https://slack.com/oauth/authorize?scope=incoming-webhook,commands'.
	'&client_id='. SLACK_CLIENT_ID .
	'&redirect_uri='. OAuth::getRedirectUrl() .
	'">'.
	'<img alt="Add to Slack" height="40" width="139" src="https://platform.slack-edge.com/img/add_to_slack.png" '.
		' srcset="https://platform.slack-edge.com/img/add_to_slack.png 1x, https://platform.slack-edge.com/img/add_to_slack@2x.png 2x" />'.
	'</a>'.
	'</p>';