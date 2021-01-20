<?php

namespace SlackPlugin\Zoom;

use stdClass;

use UKMNorge\Slack\API\Response\Plugin\Filter\ViewSubmission;
use UKMNorge\Slack\API\Response\Plugin\Transport\TransportInterface;
use UKMNorge\Slack\Block\Composition\PlainText;
use UKMNorge\Slack\Block\Composition\Markdown;
use UKMNorge\Slack\Block\Section;
use UKMNorge\Slack\Cache\Channel\Channels;
use UKMNorge\Slack\Payload\Modal;
use UKMNorge\Slack\Block\Divider;
use UKMNorge\Slack\Payload\Message;

use UKMNorge\Slack\App\UKMApp as App;

/**
 * Konverter data fra SuggestModal til en Some-idé
 */
class RouletteStart extends ViewSubmission {
    const ASYNC = false;

    /**
     * Sjekk om dette scriptet skal kjøres
     * 
     * (hvis callback_id fra slack == 'zoom_roulette_start')
     * Denne peker tilbake til SlackPlugin\Zoom\ZoomRouletteInit::getTemplate() og
     * $view->setCallbackId()
     * 
     * @param TransportInterface
     * @return bool
     */
    public function condition( TransportInterface $transport ) {
        return $transport->getView()->getCallbackId() == 'zoom_roulette_start';
    }

    /**
     * Behandle forespørselen
     * 
     * condition() har nå returnert true, som vil si at forespørselen skal
     * behandles av dette scriptet.
     * 
     * @param TransportInterface
     * @return TransportInterface
     */
    public function process( TransportInterface $transport ){        
        $data = new stdClass();
        // Modalen som sendte inn denne forespørselen skal oppdateres
        $data->response_action = 'update';
        $this->sendListToChannel($transport);
        // Sett nytt innhold
        $data->view = $this->getSuccessTemplate( $transport );
        $transport->setResponse($data);

        // Return response for possible later modification
        return $transport;
    }


    /**
     * Hent tilbakemeldingstemplate
     *
     * @return stdClass view
     */
    public function getSuccessTemplate( TransportInterface $transport ) {
        $submitdata = $transport->getView()->collectSubmittedData();

        $kanal = $submitdata['zoom_roulette_channel']->getValue();
        $kanal = "<#" . $kanal . ">";
        $users = $submitdata['zoom_roulette_users'];

        // Opprett view (nytt innhold i modal)
        $view = new Modal(new PlainText('Da er vi i gang!'));
        $view
            ->setClose( new PlainText('OK'))
            ->setCallbackId('zoom_roulette_close');

        $blocks = [];

        
        // Introduksjon
        $blocks[] = new Section( 
            new Markdown(
                'Listen over gruppene er sendt i kanalen: '. $kanal .''
            )
        );

        // // Debug brukere
        // $blocks[] = new Section(
        //     new Markdown(
        //         var_export($users, true)
        //     )
        // );

        // Legg til alle blocks
        $view->getBlocks()->set($blocks);

        return $view->export();
    }

    /**
     * Send listen til kanalen
     * 
     */
    public function sendListToChannel( TransportInterface $transport ) {
        $submitdata = $transport->getView()->collectSubmittedData();
        
        $team_id = $transport->getTeamId();
        $kanal_id = $submitdata['zoom_roulette_channel']->getValue();
        $user_ids = $submitdata['zoom_roulette_users']->getValue();
        $user_ids = explode(",", $user_ids);
        
        $kanal = Channels::getBySlackId($team_id, $kanal_id);

        App::getBotTokenFromTeamId($team_id);

        shuffle($user_ids);
        $finalPairs = $this->generatePairs($user_ids);
        $rouletteListe = $this->printList($finalPairs);

        $header = 'Klar for ny runde med zoom-roulette? 👫: \n\n *Her er gruppene:*';

        // Opprett meldingsobjektet
        // Teksten du legger til først, er teksten som vises i notifications 
        // (og må derfor alltid være PlainText-objekt)
        $message = new Message(
            $kanal->getSlackId(),
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

        // Send meldingen        
        return App::botPost('chat.postMessage', (array) $message->export());
    
    }

    function generatePairs($users){
        $pairs = [];
        error_log("161:" . var_export($users, true));
        while(count($users) > 0) {
            $randomPair = (count($users) % 2 != 0) ? array_splice($users,0,3) : array_splice($users,0,2);
            error_log("164:" . var_export($users, true));
            array_push($pairs,$randomPair);
        }

        return $pairs;
    }

    function printList($finalPairs) {
        $rouletteListe = '';
        $keys = array_keys($finalPairs);
        for($i = 0; $i < count($finalPairs); $i++) {
            $rouletteListe .= '• ';
            foreach( $finalPairs[$keys[$i]] as $user ) {
                $rouletteListe .= "<@" . $user . '> ';
            }
            $rouletteListe .= '\n';
        }
        return $rouletteListe;
    }
}