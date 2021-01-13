<?php

namespace SlackPlugin\Some;

use Exception;

use UKMNorge\Slack\API\Conversations;
use UKMNorge\Slack\API\Response\Plugin\Filter\ViewSubmission;
use UKMNorge\Slack\API\Response\Plugin\Transport\TransportInterface;
use UKMNorge\Slack\App\UKMApp as App;
use UKMNorge\Slack\Cache\User\Users;

use SlackPlugin\Some\Messages\WayForwardSubmitterFeedback;
use SlackPlugin\Some\Messages\JobRequest;

use UKMNorge\Some\Forslag\Ide;
use UKMNorge\Some\Forslag\Ideer;
use UKMNorge\Some\Tekst\Write as WriteTekst;

use UKMNorge\Some\Log\Event;


/**
 * Lagrer tekst-forslag fra some-ansvarlig, og varsler kanal-ansvarlige
 */
class WayForwardTeksterModalSubmit extends ViewSubmission
{
    const ASYNC = true;

    public function condition(TransportInterface $transport)
    {
        return $transport->getView()->getCallbackId() == 'modal_some_wayforward_tekster';
    }

    public function process(TransportInterface $transport)
    {

        App::getBotTokenFromTeamId( $transport->getTeamId() );

        // Last inn data
        $submitdata = $transport->getView()->collectSubmittedData();
        $metadata = json_decode($transport->getData()->view->private_metadata);
        $team_id = $transport->getData()->team->id;

        error_log('SUBMITDATA: ' . var_export($submitdata, true));

        // Oppdater ideen
        $ide = Ideer::getById(intval($metadata->ide));
        $transport->setAdditionalData('ide', $ide);


        // Opprett tekst for alle kanaler
        foreach ($ide->getKanaler()->getAll() as $kanal) {
            // Tom tekst er lov, men må være string
            if (isset($submitdata['status_text_' . $kanal->getId()])) {
                $innhold = $submitdata['status_text_' . $kanal->getId()]->getValue();
            } else {
                $innhold = '';
            }

            // Velger admin ingen andre, blir jobben admin sin (snooze = loose?)
            if (isset($submitdata['user_' . $kanal->getId()])) {
                $user_id = $submitdata['user_' . $kanal->getId()]->getValue();
                $team_id = $transport->getTeamId();
            } else {
                $user_id = $transport->getUserId();
                $team_id = $transport->getTeamId();
            }

            #$user_id = 'U085WBTSP'; // DEV-MODE: Marius

            $user = Users::getBySlackId($team_id, $user_id); 

            // Opprett tekst for kanalen
            try {
                $tekst = WriteTekst::opprettForIde(
                    $ide,
                    $kanal,
                    $user,
                    $innhold
                );
            } catch (Exception $e) {
                $tekst = $ide->getTekster()->getForKanal($kanal);
            }

            // Logg
            Event::create(
                Ide::class,
                $ide->getId(),
                'tekst_opprettet',
                $team_id,
                $transport->getData()->user->id,
                'ga ansvaret for ' . $kanal->getNavn() . ' til @' . $user->getName() . '.',
                (array) $tekst
            );

            // Varsle den ansvarlige
            $conversation = Conversations::startWithUser($user->getSlackId());
            $message = JobRequest::getMessage($transport, $conversation->channel->id, $kanal);
            $result = App::botPost('chat.postMessage', (array) $message->export());
        }

        // Send melding til den som sendte inn forslaget
        // OBS: bytter botToken til innsenders team
        App::getBotTokenFromTeamId( $ide->getEier()->getTeamId() );
        $conversation = Conversations::startWithUser($ide->getEier()->getSlackId());
        $message = WayForwardSubmitterFeedback::getMessage($transport, $conversation->channel->id);
        $result = App::botPost('chat.postMessage', (array) $message->export());

        // Return response for possible later modification
        return $transport;
    }
}