<?php

use UKMNorge\Slack\API\Response\Plugin\FileManager;

use SlackPlugin\Okonomi\MastercardLevert;
use UKMNorge\Slack\App\UKMApp as App;
use UKMNorge\Slack\Block\Composition\Markdown;
use UKMNorge\Slack\Block\Composition\PlainText;
use UKMNorge\Slack\Block\Context;
use UKMNorge\Slack\Block\Section;
use UKMNorge\Slack\Cache\Channel\Channels;
use UKMNorge\Slack\Payload\Message;


require_once('../../env.inc.php');
$filemanager = new FileManager( dirname(dirname(__DIR__)).'/Plugins/');

App::getBotTokenFromTeamId(SLACK_UKMNORGE_TEAM_ID);

$header = 'På tide å dokumentere pengebruken! 💸';

$periode = $_GET['periode'];
$periodedata = explode('-', $_GET['periode']);
$ar = $periodedata[0];
$maned = $periodedata[1];

$user = MastercardLevert::getDeliveryManager();
$channel = Channels::getByName(SLACK_UKMNORGE_TEAM_ID, MastercardLevert::OKONOMI_CHANNEL);

        
$header = 'Mastercard-kvitteringer er klar for innsending, '.
    '<@'. $user->getSlackId() .'>! 🎉 ';

// Opprett melding
$message = new Message(
    $channel->getSlackId(),
    new PlainText($header)
);

// Legg til melding som markdown
$message->getBlocks()->add(
    new Section(
        new Markdown(
            $header
        )
    )
);

$message->getBlocks()->add(
    new Context([
        new Markdown(
            '>>>'.
            'Alle har nå levert sine kvitteringer, og PDF ligger klar for innsending i dropbox: \n'.
            '`'. MastercardLevert::getDropboxPath() .' Ferdig -> '. $periode .'`'
        )
    ])
);

$result = App::botPost('chat.postMessage', (array) $message->export());