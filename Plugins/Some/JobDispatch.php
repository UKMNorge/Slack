<?php

namespace SlackPlugin\Some;

use UKMNorge\Slack\App\UKMApp as App;
use UKMNorge\Slack\API\View;
use UKMNorge\Slack\API\Response\Plugin\Filter\BlockAction;
use UKMNorge\Slack\API\Response\Plugin\Transport\TransportInterface;
use UKMNorge\Slack\Block\Composition\Markdown;
use UKMNorge\Slack\Block\Composition\PlainText;
use UKMNorge\Slack\Block\Section;
use UKMNorge\Slack\Payload\Modal;

use UKMNorge\Some\Forslag\Ideer;
use UKMNorge\Some\Forslag\Ide;
use UKMNorge\Some\Kanaler\Kanal;
use UKMNorge\Some\Kanaler\Kanaler;

use UKMNorge\SlackApp\Some;

/**
 * Åpne modal for sosiale medier-status
 */
class JobDispatch extends BlockAction
{
    const ASYNC = false;

    public function condition(TransportInterface $transport)
    {
        return $transport->getData()->actions[0]->action_id == 'some_job_dispatch';
    }

    public function process(TransportInterface $transport)
    {
        App::getBotTokenFromTeamId( $transport->getTeamId() );

        $transport = JobDispatch::getIdsFromTransport($transport);
        $ide = $transport->getAdditionalData('ide');
        $kanal = $transport->getAdditionalData('kanal');

        $view = new View($transport->getData()->trigger_id, $this->getTemplate($transport, $ide, $kanal));
        $result = $view->open();

        // Return response for possible later modification
        return $transport;
    }

    public function getTemplate(TransportInterface $transport, Ide $ide, Kanal $kanal)
    {
        // VIEW
        $view = new Modal(new PlainText('Ny ansvarlig'));
        $view
            ->setSubmit(new PlainText('Send'))
            ->setClose(new PlainText('Avbryt'))
            ->setCallbackId('some_job_dispatched')
            ->setPrivateMetadata([
                'ide' => $ide->getId(),
                'kanal' => $kanal->getId()
            ]);

        $view->getBlocks()->add(
            new Section(
                new Markdown('Hvem skal fikse status for ' . $kanal->getNavn() . '?')
            )
        );

        // INTRO
        $view->getBlocks()->add(
            Some::getUserSelect(
                $transport,
                new PlainText('Send oppgaven videre til: '),
                'user'
            )
        );

        return $view->export();
    }

    public static function getIdsFromTransport(TransportInterface $transport)
    {
        // Hent objektID
        $iddata = explode('|', $transport->getData()->actions[0]->block_id);
        $ide_id = str_replace('ide_', '', $iddata[0]);
        $kanal_id = str_replace('kanal_', '', $iddata[1]);

        // Finn og send med idé
        $ide = Ideer::getById(intval($ide_id));
        $transport->setAdditionalData('ide', $ide);

        // Finn og send med kanal
        $kanal = Kanaler::getById($kanal_id);
        $transport->setAdditionalData('kanal', $kanal);

        return $transport;
    }
}
