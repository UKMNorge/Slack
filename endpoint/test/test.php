<?php

ini_set('display_errors',true);

use UKMNorge\Slack\App\UKMApp as App;
use UKMNorge\Slack\API\Response\Plugin\FileManager;
use UKMNorge\Slack\Block\Composition\Markdown;
use UKMNorge\Slack\Block\Composition\PlainText;
use UKMNorge\Slack\Block\Section;
use UKMNorge\Slack\Cache\Channel\Channels;
use UKMNorge\Slack\Cache\User\Users;
use UKMNorge\Slack\Payload\Message;

require_once('../../env.inc.php');
$filemanager = new FileManager( dirname(dirname(__DIR__)).'/Plugins/');

App::getBotTokenFromTeamId(SLACK_UKMNORGE_TEAM_ID);

// Kanalen vi skal sende til
$channel = Channels::getByName(SLACK_UKMNORGE_TEAM_ID, '#test');

// Handlebars for oss på kontoret
$users = [
    '@mariusmandal',
    '@jardar',
    '@stine',
    '@tom.andreas.kristense',
    '@torstein',
    '@kimd9740',
    '@kushtrimaliu',
    '@camilla.tangen'
];

$user = Users::getByHandlebar(SLACK_UKMNORGE_TEAM_ID, '@mariusmandal');

$header = 'Her kommer en test-melding til deg  '.
    '<@'. $user->getSlackId() .'>! 🎉 ';

// Opprett meldingsobjektet
// Teksten du legger til først, er teksten som vises i notifications 
// (og må derfor alltid være PlainText-objekt)
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

// Send meldingen
$result = App::botPost('chat.postMessage', (array) $message->export());