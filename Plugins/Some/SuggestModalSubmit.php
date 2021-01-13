<?php

namespace SlackPlugin\Some;

use DateTime;
use stdClass;

use UKMNorge\Slack\API\Conversations;
use UKMNorge\Slack\API\Response\Plugin\Filter\ViewSubmission;
use UKMNorge\Slack\API\Response\Plugin\Transport\TransportInterface;
use UKMNorge\Slack\App\UKMApp as App;
use UKMNorge\Slack\Block\Context;
use UKMNorge\Slack\Block\Composition\PlainText;
use UKMNorge\Slack\Block\Composition\Markdown;
use UKMNorge\Slack\Block\Section;
use UKMNorge\Slack\Cache\User\Users as LocalUsers;
use UKMNorge\Slack\Payload\Modal;

use SlackPlugin\Some\Messages\WayForward;

use UKMNorge\Some\Forslag\Ide;
use UKMNorge\Some\Forslag\Write as WriteIde;
use UKMNorge\Some\Kanaler\Kanaler;
use UKMNorge\Some\Slack\Template;
use UKMNorge\Some\Log\Event;



/**
 * Konverter data fra SuggestModal til en Some-idé
 */
class SuggestModalSubmit extends ViewSubmission {
    const ASYNC = false;

    public function condition( TransportInterface $transport ) {
        return $transport->getView()->getCallbackId() == 'modal_some_suggest';
    }

    public function process( TransportInterface $transport ){

        $transport = $this->lagreIde( $transport );
        $transport = $this->varsleSomeansvarlig( $transport, $template_data);
        
        $data = new stdClass();
        $data->response_action = 'update';
        $data->view = $this->getUserFeedbackTemplate( $transport );
        $transport->setResponse($data);

        // Return response for possible later modification
        return $transport;
    }

    /**
     * Varsle someansvarlig, og få en avklaring på veien videre
     *
     * @param TransportInterface
     * @return TransportInterface
     */
    public function varsleSomeansvarlig( $transport ) {
        App::getBotTokenFromTeamId( SLACK_UKMMEDIA_TEAM_ID );

        $submitdata = $transport->getView()->collectSubmittedData();

        // Admins kan velge å sende det til andre admins
        if( isset( $submitdata['wayforward_user'] ) ) {
            $user = LocalUsers::getByHandlebar(SLACK_UKMMEDIA_TEAM_ID, $submitdata['wayforward_user']);
        } else {
            $user = LocalUsers::getByHandlebar(SLACK_UKMMEDIA_TEAM_ID, '@stinegranly');
        }
        #$user = LocalUsers::getByHandlebar(SLACK_UKMMEDIA_TEAM_ID, '@mariusmandal'); // DEV-MODE

        // VARSLE ANSVARLIG
        $conversation = Conversations::startWithUser($user->getSlackId());
        $message = WayForward::getMessage( $transport, $conversation->channel->id);        
        $result = App::botPost('chat.postMessage',(array)$message->export());
        $transport->setAdditionalData('varslet', $user);

        Event::create(
            Ide::class,
            $transport->getAdditionalData('ide')->getId(),
            'message_wayforward',
            $transport->getTeamId(),
            $transport->getUserId(),
            '@'.$user->getName() .' ble varslet og tar forslaget videre.',
            (array) $result
        );

        return $transport;
    }

    /**
     * Lagre ideen i UKM-systemet
     *
     * @return Ide
     */
    public function lagreIde( TransportInterface $transport ) {
        $submitdata = $transport->getView()->collectSubmittedData();

        $ide = WriteIde::create(
            $transport->getTeamId(),
            $transport->getUserId(),
            $submitdata['status_text']
        );

        $ide->setHva($submitdata['status_type']);

        // Dato
        if( isset($submitdata['publish_time'])) {
            $datodata = $submitdata['publish_time']->getValue();
            $ide->setPubliseringsdato( new DateTime( $datodata ) );
        } else {
            $datodata = 'i dag';
        }

        // Kanaler
        foreach( explode(',', $submitdata['channels']->getValue() ) as $kanal_id ) {
            if(is_null($kanal_id) || empty($kanal_id)) {
                continue;
            }
            $kanal = Kanaler::getById( $kanal_id );
            if( is_null($kanal)) {
                continue;
            }
            $ide->getKanaler()->add( $kanal );
        }

        // Ansvarlig
        if( isset( $submitdata['wayforward_user'] ) ) {
            $user = LocalUsers::getByHandlebar(SLACK_UKMMEDIA_TEAM_ID, $submitdata['wayforward_user']);
        } else {
            $user = LocalUsers::getByHandlebar(SLACK_UKMMEDIA_TEAM_ID, '@stinegranly');
        }
        #$user = LocalUsers::getByHandlebar(SLACK_UKMMEDIA_TEAM_ID, '@mariusmandal'); // DEV-MODE

        error_log('CHECK_USER:'. var_export($user, true));
        $ide->setAnsvarlig($user);
        
        WriteIde::save($ide);
        
        $transport->setAdditionalData('ide', $ide);

        Event::create(
            Ide::class,
            $ide->getId(),
            'created',
            $transport->getTeamId(),
            $transport->getUserId(),
            'opprettet forslaget med ønsket publisering '. $datodata .'.',
            $ide->__toArray()
        );

        return $transport;
    }

    /**
     * Hent tilbakemeldingstemplate
     *
     * @return stdClass view
     */
    public function getUserFeedbackTemplate( TransportInterface $transport ) {
        $submitdata = $transport->getView()->collectSubmittedData();

        // VIEW
        $view = new Modal(new PlainText('Veien videre'));
        $view
            ->setClose( new PlainText('OK'))
            ->setCallbackId('modal_some_suggest_summary');

        // INTRO
        $view->getBlocks()->add(
            new Section( 
                new Markdown(
                    ':tada: Akkurat nå trenger du ikke å gjøre noe. Vi varsler de som skal hjelpe deg, så får du en melding når vi vet mer.'
                )
            )
        );

        $summary = Template::getStatusSuggestionPreview(
            $view,
            $transport->getAdditionalData('ide')
        );

        $varslet = $transport->getAdditionalData('varslet');
        $view->getBlocks()->add(
            new Context(
                [
                    new Markdown(
                        "Vi har nå varslet <". $varslet->getSlackLink() .'|'. $varslet->getNameOrHandlebar() .">, som tar det videre."
                    )
                ]
            )
        );

        return $view->export();
    }
}