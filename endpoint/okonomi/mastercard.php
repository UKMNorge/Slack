<?php

use UKMNorge\Slack\API\Response\Plugin\FileManager;

use SlackPlugin\Okonomi\MastercardLevert;
use UKMNorge\Slack\API\Conversations;
use UKMNorge\Slack\App\UKMApp as App;
use UKMNorge\Slack\Cache\User\Users as LocalUsers;
use UKMNorge\Slack\Block\Actions;
use UKMNorge\Slack\Block\Composition\Markdown;
use UKMNorge\Slack\Block\Composition\PlainText;
use UKMNorge\Slack\Block\Element\Button;
use UKMNorge\Slack\Block\Section;
use UKMNorge\Slack\Payload\Message;


require_once('../../env.inc.php');
$filemanager = new FileManager( dirname(dirname(__DIR__)).'/Plugins/');

App::getBotTokenFromTeamId(SLACK_UKMNORGE_TEAM_ID);

$header = 'På tide å dokumentere pengebruken! 💸';

$periode = $_GET['periode'];
$periodedata = explode('-', $_GET['periode']);
$ar = $periodedata[0];
$maned = $periodedata[1];

$users = MastercardLevert::getUsers();

foreach( $users as $handlebar => $folder ) {
    $user = LocalUsers::getByHandlebar(SLACK_UKMNORGE_TEAM_ID, $handlebar);
    $conversation = Conversations::startWithUser($user->getSlackId());

    $text = new Markdown(
        '*'. $header .'* \n'.
        '<@'. MastercardLevert::getScriptExecuter()->getSlackId() .'> har laget logg-skjema for '. $_GET['periode'] .', '.
        ' og dette er klart for utfylling.'.
        '\n\n'.
        '*Dette må du gjøre:* \n'.
        '>>>'.
        '1. Fyll ut loggskjema ditt\n'.
        ' `'. MastercardLevert::getDropboxPath(). $ar .' -> '. $folder .' -> '. $periode .'`\n'.
        '2. Lagre alle kvitteringer i mappen (i PDF eller PNG-format)\n'.
        '3. Lagre loggskjema som PDF\n'.
        '4. Trykk på denne knappen nedenfor 👇🏼'
    );

    $message = new Message(
        $conversation->channel->id,
        new PlainText($header)
    );
    $message->getBlocks()->add(
        new Section($text)
    );

    $ican = new Button(
        'mastercard_done',
        new PlainText('denne knappen (🤯)')
    );
    $ican->setStyle('primary');


    $confirm = new Actions(null);
    $confirm->setId('mastercard_periode|'. str_replace('-','_',$_GET['periode']));
    $confirm->getElements()->set([
        $ican
        ]);
        
    $message->getBlocks()->add(
        $confirm
    );

    $result = App::botPost('chat.postMessage', (array) $message->export());
}