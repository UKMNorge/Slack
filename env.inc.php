<?php

namespace UKMNorge\SlackApp;

use UKMNorge\Slack\API\Response\Plugin\Transport\TransportInterface;
use UKMNorge\Slack\App\UKMApp as App;
use UKMNorge\Slack\Block\Composition\Markdown;
use UKMNorge\Slack\Block\Composition\PlainText;
use UKMNorge\Slack\Block\Composition\Text;
use UKMNorge\Slack\Block\Composition\Option;
use UKMNorge\Slack\Block\Element\Select;
use UKMNorge\Slack\Block\Section;
use UKMNorge\Slack\Cache\User\Users;

require_once('UKMconfig.inc.php');
require_once('UKM/Autoloader.php');

foreach( ['SLACK_CLIENT_ID', 'SLACK_CLIENT_SECRET'] as $constant ) {
    if( !defined($constant) ) {
        die('One or more App ID constants are missing.');
    }
}

class Some {
    static $users;
    const ADMINS = ['mariusmandal','stinegranly','inapjevne'];

    public static function isAdmin(String $team_id, String $slack_id ) {
        foreach( static::getUsers($team_id)->getAll() as $admin ) {
            if( $admin->getSlackId() == $slack_id ) {
                return true;
            }
        }
        return false;
    }

    public static function getUsers( String $team_id ) {
        if( is_null(static::$users)) {
            static::$users = Users::getByHandleBars($team_id, static::ADMINS);
        }
        error_log('getUsers()'. var_export(static::$users,true));
        return static::$users;
    }

    public static function getAdmin( String $team_id, String $handlebar ) {
        foreach( static::getUsers( $team_id )->getAll() as $admin ) {
            if( $admin->getName() == ltrim($handlebar,'@') ) {
                return $admin;
            }
        }
        throw new Exception('Fant ikke bruker '. $handlebar);
    }

    /**
     * Hent alle brukere som kan benyttes
     * 
     * @return Section
     */
    public static function getUserSelect( TransportInterface $transport, Text $tekst, String $id ) {
        $users = new Users( $transport->getData()->team->id );
        $userOptions = [];
        foreach( $users->getAll() as $user ) {
            if( !$user->isActive() ) {
                continue;
            }
            $user_name = empty($user->getRealName()) ? $user->getName() : $user->getRealName();
            $userOptions[] = new Option(
                Select::class,
                new PlainText($user_name),
                strval($user->getSlackId())
            );
        }
        $selectUsers = new Section($tekst);
        $selectUsers->setAccessory(
            new Select(
                $id,
                $userOptions,
                new PlainText('Velg bruker')
            )
        );
        return $selectUsers;
    }
}




App::initFromAppDetails( SLACK_CLIENT_ID, SLACK_CLIENT_SECRET, SLACK_SIGNING_SECRET);

/*
App::init();

use UKMNorge\Trello\Trello;
use UKMNorge\Slack\API\API;
use UKMNorge\Slack\Kjop\APP;

// INIT TRELLO
Trello::setId(
	TRELLO_APP_KEY,
	TRELLO_APP_TOKEN
);
Trello::setBoardId(TRELLO_BOARD_INNKJOP);


API::init();
*/