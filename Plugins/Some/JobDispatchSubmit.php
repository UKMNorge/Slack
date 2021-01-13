<?php

namespace SlackPlugin\Some;

use DateTime;
use Exception;
use stdClass;

use UKMNorge\Slack\API\Conversations;
use UKMNorge\Slack\API\Response\Plugin\Filter\ViewSubmission;
use UKMNorge\Slack\API\Response\Plugin\Transport\TransportInterface;
use UKMNorge\Slack\App\UKMApp as App;
use UKMNorge\Slack\Block\Composition\PlainText;
use UKMNorge\Slack\Block\Composition\Markdown;
use UKMNorge\Slack\Block\Section;
use UKMNorge\Slack\Cache\User\Users;
use UKMNorge\Slack\Payload\Modal;

use SlackPlugin\Some\Messages\JobRequest;

use UKMNorge\Some\Forslag\Ide;
use UKMNorge\Some\Forslag\Ideer;
use UKMNorge\Some\Kanaler\Kanaler;
use UKMNorge\Some\Tekst\Write as WriteTekst;

use UKMNorge\Some\Log\Event;


/**
 * Lagrer tekst-forslag fra some-ansvarlig, og varsler kanal-ansvarlige
 */
class JobDispatchSubmit extends ViewSubmission
{
    const ASYNC = false;

    public function condition(TransportInterface $transport)
    {
        return $transport->getView()->getCallbackId() == 'some_job_dispatched';
    }

    public function process(TransportInterface $transport)
    {

        App::getBotTokenFromTeamId( $transport->getTeamId() );

        // Last inn data
        $submitdata = $transport->getView()->collectSubmittedData();
        $metadata = json_decode($transport->getData()->view->private_metadata);

        // Hent ideen
        $ide = Ideer::getById(intval($metadata->ide));
        $transport->setAdditionalData('ide', $ide);

        // Hent kanalen
        $kanal = Kanaler::getById( $metadata->kanal );

         // Velger admin ingen andre, blir jobben admin sin (snooze = loose?)
         $user_id = isset($submitdata['user']) ? 
            $submitdata['user']->getValue() :
            $transport->getUserId();
        $team_id = $transport->getTeamId(); // TODO: team_id må inn i submitdata!
        #$user_id = 'U085WBTSP'; // DEV-MODE: Marius
        $user = Users::getBySlackId($team_id, $user_id);

        // Hent eller opprett tekst for kanalen
        try {
            $tekst = false;
            error_log(var_export($ide, true));
            error_log(var_export($kanal, true));
            foreach( $ide->getTekster()->getAll() as $kanal_tekst ) {
                error_log('TEST: '. $kanal_tekst->getKanalId());
                if( $kanal_tekst->getKanalId() == $kanal->getId() ) {
                    $tekst = $kanal_tekst;
                }
            }
            if( $tekst ) {
                $tekst->setEier($user);
                WriteTekst::save($tekst);
                Event::create(
                    Ide::class,
                    $ide->getId(),
                    'tekst_omfordelt',
                    $transport->getTeamId(),
                    $transport->getUserId(),
                    'ga ansvaret for ' . $kanal->getNavn() . ' til @' . $user->getName() . '.',
                    (array) $tekst
                );
            } else {
                throw new Exception('Fant ikke tekst for gitt kanal');
            }
        } catch( Exception $e ) {
            $tekst = WriteTekst::opprettForIde(
                $ide,
                $kanal,
                $user,
                ''
            );
            Event::create(
                Ide::class,
                $ide->getId(),
                'tekst_opprettet',
                $transport->getTeamId(),
                $transport->getUserId(),
                'ga ansvaret for ' . $kanal->getNavn() . ' til @' . $user->getName() . '.',
                (array) $tekst
            );
        }

        // Varsle den ansvarlige
        $conversation = Conversations::startWithUser($user->getSlackId());
        $message = JobRequest::getMessage($transport, $conversation->channel->id, $kanal);
        $result = App::botPost('chat.postMessage', (array) $message->export());
    

        // Set (siste) modal
        $data = new stdClass();
        $data->response_action = 'update';
        $data->view = $this->getTemplate( $transport );
        $transport->setResponse($data);


        // Return response for possible later modification
        return $transport;
    }

    public function getTemplate( TransportInterface $transport )
    {
#        $submitdata = $transport->getView()->collectSubmittedData();

        // VIEW
        $view = new Modal(new PlainText('Det var alt! :tada:'));
        $view
            ->setClose(new PlainText('OK'))
            ->setCallbackId('some_job_dispatched_success');
        // INTRO
        $view->getBlocks()->add(
            new Section(
                new Markdown(
                    "Vi har nå varslet den som skal ta det videre."
                )
            )
        );
        return $view->export();
    }
}
