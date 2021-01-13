<?php

namespace UKMNorge\SlackApp;

use UKMNorge\Slack\App\UKMApp as App;
use Exception;

require_once('env.inc.php');

echo '<p>'. App::getButton() .'</p>';