<?php

namespace SlackPlugin\Okonomi;

use Exception;
use UKMNorge\Database\SQL\Insert;
use UKMNorge\Database\SQL\Query;
use UKMNorge\Slack\API\Chat;
use UKMNorge\Slack\API\Response\Plugin\Filter\BlockAction;
use UKMNorge\Slack\API\Response\Plugin\Transport\TransportInterface;
use UKMNorge\Slack\App\UKMApp as App;
use UKMNorge\Slack\Block\Composition\Markdown;
use UKMNorge\Slack\Block\Composition\PlainText;
use UKMNorge\Slack\Block\Context;
use UKMNorge\Slack\Block\Section;
use UKMNorge\Slack\Cache\Channel\Channels;
use UKMNorge\Slack\Cache\User\User;
use UKMNorge\Slack\Cache\User\Users;
use UKMNorge\Slack\Payload\Message;

class MastercardLevert extends BlockAction
{
    const ASYNC = false;
    const SCRIPT_EXECUTE_HANDLEBAR = '@jardar';
    const SCRIPT_DELIVERY_HANDLEBAR = '@torstein';
    const OKONOMI_CHANNEL = '#økonomi';

    /**
     * Hent alle brukere som skal involveres
     * 
     * @return Array[ String $handlebar => String $folder ]
     */
    public static function getUsers() {
        return [
            '@marius' => 'Marius',
            '@jardar' => 'Internett',
            '@stine' => 'Stine',
            '@tom.andreas.kristense' => 'Tom Andreas',
            '@torstein' => 'Torstein'
        ];
    }

    public function condition(TransportInterface $transport)
    {
        return $transport->getData()->actions[0]->action_id == 'mastercard_done';
    }

    public function process(TransportInterface $transport)
    {
        App::getBotTokenFromTeamId($transport->getTeamId());
        
        # Hent periode
        $periode = str_replace('mastercard_periode|', '', $transport->getData()->actions[0]->block_id);

        # Lagre innsending
        list($nummer, $emoji, $melding) = static::saveResponse($transport, $periode);

        # Finn brukeren
        $user = Users::getBySlackId(SLACK_UKMNORGE_TEAM_ID, $transport->getUserId());

        # Oppdater brukerens melding (tar bort knappen)
        static::notifyUser($transport, $periode, $nummer, $emoji, $melding);

        # Oppdater gjengen (sender melding til felleskanalen)
        static::notifyOkonomiChannel($transport, $periode, $nummer, $user, $emoji);
        
        # Hvis alle har levert, be noen om å merge scriptet
        if( $nummer >= sizeof( static::getUsers()) ) {
            static::notifyScriptExecuterToMergePdf($transport);
        }
    
        return $transport;
    }

    /**
     * Oppdater brukerens melding
     * (og fjern innsendingsknappen)
     * 
     * @param TransportInterface $transport
     * @param String $periode
     * @param Int $nummer
     * @param String $emoji
     * @param String $melding
     * @return void
     */
    public static function notifyUser(TransportInterface $transport, String $periode, Int $nummer, String $emoji, String $melding) {
        
        // Opprett meldingsobjekt
        $message = new Message(
            $transport->getData()->container->channel_id,
            new PlainText($emoji .' '. $melding)
        );

        // Timstamp gjør at meldingen oppdateres
        $message->setTimestamp($transport->getData()->container->message_ts);

        // Legg til meldingen som markdown
        $message->getBlocks()->add(
            new Section( 
                new Markdown($emoji .' '. $melding)
            )
        );
        
        // Legg til context
        $message->getBlocks()->add(
            new Context([
                new PlainText('Loggskjema for mastercard ' . $periode . ' levert.')
            ])
        );

        // Send til slack
        $result = Chat::update($message);
    }

    /**
     * Varsle økonomi-kanalen om at noen har levert skjema
     * 
     * @param TransportInterface
     * @param String $periode
     * @param Int $nummer
     * @param User $user
     * @param String $emoji
     * @return TransportInterface
     */
    public static function notifyOkonomiChannel(TransportInterface $transport, String $periode, Int $nummer, User $user, String $emoji) {
        ## Varsle de andre
        $channel = Channels::getByName(SLACK_UKMNORGE_TEAM_ID, static::OKONOMI_CHANNEL);
        
        $header = 
            ($nummer < 4 ? $emoji : '') .
            ' <@'. $user->getSlackId() .'> har levert loggskjema for '. $periode;

        $message = new Message(
            $channel->getSlackId(),
            new PlainText($header)
        );

        $message->getBlocks()->add(
            new Section(
                new Markdown($header)
            )
        );
        $message->getBlocks()->add(
            new Context([
                new Markdown( $nummer .' av '. sizeof( static::getUsers() ).' har levert.')
            ])
        );

        $result = App::botPost('chat.postMessage', (array) $message->export());

        return $transport;
    }

    /**
     * Varsle script executer om at PDF-filer er klar til merge
     * 
     * @param TransportInterface $transport
     */
    public static function notifyScriptExecuterToMergePdf(TransportInterface $transport ) {
        ## Varsle den som skal kjøre scriptet
        $channel = Channels::getByName(SLACK_UKMNORGE_TEAM_ID, static::OKONOMI_CHANNEL);
        
        $header = 'Mastercard-kvitteringer er klar for merge '.
            '<@'. static::getScriptExecuter()->getSlackId() .'>.';
        
        // Opprett melding
        $message = new Message(
            $channel->getSlackId(),
            new PlainText($header)
        );

        // Legg til melding som markdown
        $message->getBlocks()->add(
            new Section(
                new Markdown($header)
            )
        );

        // Legg til beskrivelse
        $message->getBlocks()->add(
            new Context([
                new Markdown(
                    '>>>'.
                    '*For å kjøre scriptet:* \n'.
                    '1. Gå til '. static::getDropboxPath() .' StatementToExcel \n'.
                    '2. Kjør `python3 ferdig.py`'
                )
            ])
        );

        $result = App::botPost('chat.postMessage', (array) $message->export());

        return $transport;
    }

    /**
     * Get dropbox path
     * 
     * @return String $dropbox_path
     */
    public static function getDropboxPath() {
        return 'Dropbox > Drift (kontor) -> Økonomi > Mastercard-kvitteringer -> ';
    }

    /**
     * Hent ansvarlig bruker for script execution
     * 
     * @return User $user
     */
    public static function getScriptExecuter() {
        return Users::getByHandlebar(SLACK_UKMNORGE_TEAM_ID, static::SCRIPT_EXECUTE_HANDLEBAR);
    }

    /**
     * Hent ansvarlig bruker for innsending
     * 
     * @return User $user
     */
    public static function getDeliveryManager() {
        return Users::getByHandlebar(SLACK_UKMNORGE_TEAM_ID, static::SCRIPT_DELIVERY_HANDLEBAR);
    }

    /**
     * Lagre innsending
     * 
     * @param TransportInterface $transport
     * @param String $periode
     * @return [Int $nummer, String $emoji, String $melding]
     */
    public static function saveResponse(TransportInterface $transport, String $periode) {
        ## OPPDATER DATABASEN
        $query = new Insert('ukmnorge_okonomi_mastercard');
        $query->add('periode', $periode);
        $query->add('user_id', $transport->getUserId());
        try {
            $res = $query->run();
        } catch ( Exception $e ) {
            if( $e->getCode() == 901001 ) {
                error_log($query->debug());
            }
        }

        $count = new Query(
            "SELECT COUNT(`id`)
            FROM `ukmnorge_okonomi_mastercard`
            WHERE `periode` = '#periode'",
            [
                'periode' => $periode
            ]
        );
        $nummer = (int) $count->getField();

        switch ($nummer) {
            case 1:
                $melding = 'Gratulerer, du ble først ferdig!';
                $emoji = '🥇';
                break;
            case 2:
                $melding = 'Hoi, det var nesten førsteplassen!';
                $emoji = '🥈';
                break;
            case 3:
                $melding = 'Pallplassering!';
                $emoji = '🥉';
                break;
            default:
                $melding = 'Ikke alle kan vinne hver gang. Du ble nummer '. $nummer;
                $emoji = '🐢';
                break;
        }

        return [
            $nummer,
            $emoji,
            $melding
        ];
    }
}
