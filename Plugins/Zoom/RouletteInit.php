<?php

namespace SlackPlugin\Zoom;

use UKMNorge\Slack\App\UKMApp as App;
use UKMNorge\Slack\API\Response\Plugin\Filter\Trigger;
use UKMNorge\Slack\API\Response\Plugin\Transport\TransportInterface;
use UKMNorge\Slack\API\View;
use UKMNorge\Slack\Block\Composition\PlainText;
use UKMNorge\Slack\Block\Composition\Markdown;
use UKMNorge\Slack\Block\Element\MultiSelectUsers;
use UKMNorge\Slack\Block\Element\SelectConversations;
use UKMNorge\Slack\Block\Section;
use UKMNorge\Slack\Payload\Modal;

/**
 * Åpne modal for sosiale medier-status
 */
class RouletteInit extends Trigger
{
    const ASYNC = true;

    // SJEKK AT CALLBACK-ID GJELDER DENNE TRIGGEREN
    public function condition(TransportInterface $transport)
    {
        error_log('COND: ' . $transport->getData()->callback_id . ' == zoom_roulette_init');
        return $transport->getData()->callback_id == 'zoom_roulette_init';
    }

    // BEHANDLE FORESPØRSELEN (HVIS condition() returnerer true)
    public function process(TransportInterface $transport)
    {
        App::getBotTokenFromTeamId($transport->getTeamId());

        $view = new View($transport->getData()->trigger_id, $this->getTemplate($transport));
        // Sender kommandoen til slack-api
        $result = $view->open();

        // Return response for possibel later modification
        return $transport;
    }

    // SETT OPP MODAL-TEMPLATE
    public function getTemplate(TransportInterface $transport)
    {
        // VIEW
        $view = new Modal(new PlainText('Zoom-roulette'));
        $view
            ->setSubmit(new PlainText('Start'))
            ->setClose(new PlainText('Avbryt'))
            ->setCallbackId('zoom_roulette_start');

        // Introduksjon
        $intro = new Section(
            new Markdown(
                '*Legg til deltakere og kanal for å starte en zoom-roulette*'
            )
        );

        // Velg mennesker
        $mennesker = new Section(
            new PlainText('Deltakere:')
        );
        $mennesker->setAccessory(
            new MultiSelectUsers(
                'zoom_roulette_users',
                new PlainText('Hvem skal være med?')
            )
        );

        // Velg kanal
        $kanal = new Section(
            new PlainText('Kanal:')
        );
        $kanal->setAccessory(
            new SelectConversations(
                'zoom_roulette_channel',
                new PlainText('Hvor skal lista sendes til?')
            )
        );

        // Legg til alle blocks
        $view->getBlocks()->set(
            [
                $intro,
                $mennesker,
                $kanal
            ]
        );

        return $view->export();
    }
}