<?php

namespace UKMNorge\Slack\Kjop;

use UKMNorge\Trello\Trello;
use UKMNorge\Slack\API\API;
use UKMNorge\Slack\Kjop\APP;

require_once('UKMconfig.inc.php');
require_once('UKM/Trello/Trello.php');
require_once('UKM/Slack/autoload.php');
require_once('kjop/class/autoload.php');

APP::init();

// INIT TRELLO
Trello::setId(
	TRELLO_APP_KEY,
	TRELLO_APP_TOKEN
);
Trello::setBoardId(TRELLO_BOARD_INNKJOP);

// INIT SLACK
API::init(SLACK_ACCESS_TOKEN);