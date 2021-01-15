<?php

ini_set('display_errors',true);

use UKMNorge\Slack\App\UKMApp as App;
use UKMNorge\Slack\API\Response\Plugin\FileManager;
use UKMNorge\Slack\Block\Composition\Markdown;
use UKMNorge\Slack\Block\Composition\PlainText;
use UKMNorge\Slack\Block\Divider;
use UKMNorge\Slack\Block\Section;
use UKMNorge\Slack\Cache\Channel\Channels;
use UKMNorge\Slack\Cache\User\Users;
use UKMNorge\Slack\Payload\Message;

require_once('../../env.inc.php');
$filemanager = new FileManager( dirname(dirname(__DIR__)).'/Plugins/');

App::getBotTokenFromTeamId(SLACK_UKMNORGE_TEAM_ID);

// Kanalen vi skal sende til
$channel = Channels::getByName(SLACK_UKMNORGE_TEAM_ID, '#test');

function addUser($newUser) {
    array_push($users,$newUser);
}

// Handlebars for oss på kontoret
function getUserList(){
    $userList = [
        '@mariusmandal',
        '@jardar',
        '@stine',
        '@tom.andreas.kristense',
        '@torstein',
        '@kimd9740',
        '@kushtrimaliu',
        '@camilla.tangen'
    ];

    return $userList;
}

$users = getUserList();

function getRandomNumber($users){return rand(0, count($users)-1);}

function shuffleList($users){
    $randomUserList = [];
    
    while(count($users) > 0) {
        $randomNumber = getRandomNumber($users);
        $randomizeArray = array_splice($users,$randomNumber,1);
        $randomUser = $randomizeArray[0];
        array_push($randomUserList,$randomUser);
    }

    return $randomUserList;
}

function generatePairs($users){
    $pairs = [];

    while(count($users) > 0) {
        $randomPair = (count($users) % 2 != 0) ? array_splice($users,0,3) : array_splice($users,0,2);
        array_push($pairs,$randomPair);
    }

    return $pairs;
}

$randomList = shuffleList($users);
$finalPairs = generatePairs($randomList);

function printList($finalPairs) {
    $rouletteListe = '';
    $keys = array_keys($finalPairs);
    for($i = 0; $i < count($finalPairs); $i++) {
        $rouletteListe .= '• ';
        foreach( $finalPairs[$keys[$i]] as $user ) {
            $rouletteListe .= $user . ' ';
        }
        $rouletteListe .= '\n';
    }
    return $rouletteListe;
}

$rouletteListe = printList($finalPairs);

$header = 'Klar for ny runde med zoom-roulette? 👫: \n\n *Her er gruppene:*';

// Opprett meldingsobjektet
// Teksten du legger til først, er teksten som vises i notifications 
// (og må derfor alltid være PlainText-objekt)
$message = new Message(
    $channel->getSlackId(),
    new PlainText($header)
);

$message->getBlocks()->add(
    new Section(
        new Markdown(
            $header
        )
    )
);

$message->getBlocks()->add(
    new Divider()
);

$message->getBlocks()->add(
    new Section(
        new Markdown(
            $rouletteListe
        )
    )
);

$message->getBlocks()->add(
    new Divider()
);

// Send meldingen
$result = App::botPost('chat.postMessage', (array) $message->export());