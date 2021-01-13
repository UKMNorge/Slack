<?php

namespace SlackPlugin\Some;

use stdClass;

use UKMNorge\Slack\API\Response\Plugin\Filter\ViewSubmission;
use UKMNorge\Slack\API\Response\Plugin\Transport\TransportInterface;
use UKMNorge\Slack\Block\Composition\PlainText;
use UKMNorge\Slack\Block\Composition\Markdown;
use UKMNorge\Slack\Block\Section;
use UKMNorge\Slack\Payload\Modal;

/**
 * Takker some-ansvarlig for informasjon
 */
class WayForwardTeksterModalSubmitThanks extends ViewSubmission
{
    const ASYNC = false;

    public function condition(TransportInterface $transport)
    {
        return $transport->getView()->getCallbackId() == 'modal_some_wayforward_tekster';
    }

    public function process(TransportInterface $transport)
    {
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
        $view = new Modal(new PlainText('Det var alt! :tada:'));
        $view
            ->setClose(new PlainText('OK'))
            ->setCallbackId('modal_some_suggest_summary');
        $view->getBlocks()->add(
            new Section(
                new Markdown(
                    "Vi har nå varslet de som skal ta det videre."
                )
            )
        );
        return $view->export();
    }
}
