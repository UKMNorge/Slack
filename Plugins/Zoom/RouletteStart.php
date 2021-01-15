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

        $kanal = $submitdata['zoom_roulette_channel'];
        $users = $submitdata['zoom_roulette_users'];

        // Opprett view (nytt innhold i modal)
        $view = new Modal(new PlainText('Jippi'));
        $view
            ->setClose( new PlainText('OK'))
            ->setCallbackId('zoom_roulette_close');

        $blocks = [];

        // Introduksjon
        $blocks[] = new Section( 
            new Markdown(
                'Listen er sendt til '. $kanal .''
            )
        );

        // Debug brukere
        $blocks[] = new Section(
            new Markdown(
                var_export($users, true)
            )
        );

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
        $kanal_id = $submitdata['zoom_roulette_channel'];
        $user_ids = $submitdata['zoom_roulette_users'];
        
        $kanal = Channels::getBySlackId($team_id, $kanal_id);
        // SEND TO CHANNEL

        return $transport;
    }
}