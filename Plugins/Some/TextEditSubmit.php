<?php

namespace SlackPlugin\Some;

use SlackPlugin\Some\Messages\JobAccepted;
use SlackPlugin\Some\Messages\TextPublished;
use stdClass;
use UKMNorge\Slack\API\Conversations;
use UKMNorge\Slack\API\Response\Plugin\Filter\ViewSubmission;
use UKMNorge\Slack\API\Response\Plugin\Transport\TransportInterface;
use UKMNorge\Slack\App\UKMApp as App;
use UKMNorge\Slack\Block\Composition\PlainText;
use UKMNorge\Slack\Block\Composition\Markdown;
use UKMNorge\Slack\Block\Section;
use UKMNorge\Slack\Payload\Modal;

use UKMNorge\Some\Forslag\Ide;
use UKMNorge\Some\Forslag\Ideer;
use UKMNorge\Some\Kanaler\Kanaler;
use UKMNorge\Some\Log\Event;
use UKMNorge\Some\Tekst\Write as WriteTekst;

/**
 * Åpne modal for sosiale medier-status
 */
class TextEditSubmit extends ViewSubmission
{
    const ASYNC = false;

    public function condition(TransportInterface $transport)
    {
        return $transport->getCallbackId() == 'some_text_save';
    }

    public function process(TransportInterface $transport)
    {
        App::getBotTokenFromTeamId( $transport->getTeamId() );
        
        // Hent objekter og send med i transporten
        $ide = Ideer::getById( intval($transport->getMetadata('ide') ));
        $kanal = Kanaler::getById( $transport->getMetadata('kanal'));
        $tekst = $ide->getTekster()->getForKanal($kanal);
        $transport->setAdditionalData('ide', $ide);
        $transport->setAdditionalData('kanal', $kanal);

        // Hent submitdata og oppdater objektet
        $submitdata = $transport->getView()->collectSubmittedData();

        // Selve teksten
        if( isset($submitdata['status_text'])) {
            $oppdatert_tekst = $tekst->getTekst() != $submitdata['status_text'];
            $tekst->setTekst($submitdata['status_text']);
        }

        // Notater
        if( isset($submitdata['status_notater'])) {
            $oppdatert_notater = $tekst->getTekst() != $submitdata['status_notater'];
            $tekst->setNotater($submitdata['status_notater']);
        }

        // Publiseringsstatus
        if( isset($submitdata['text_status'])) {
            $oppdatert_status = $tekst->getStatus() != $submitdata['text_status'];
            $tekst->setStatus($submitdata['text_status']);
        }
        
        WriteTekst::save($tekst);

        // Logg oppdatert tekst / oppdaterte notater
        if( $oppdatert_tekst || $oppdatert_notater ) {
            Event::create(
                Ide::class,
                $ide->getId(),
                'tekst_oppdatert',
                $transport->getTeamId(),
                $transport->getUserId(),
                'oppdaterte tekst og/eller notater for ' . $kanal->getNavn(),
                (array) $tekst
            );
        }

        // Logg oppdatert publiseringsstatus
        if( $oppdatert_status ) {
            Event::create(
                Ide::class,
                $ide->getId(),
                'tekst_oppdatert_status',
                $transport->getTeamId(),
                $transport->getUserId(),
                ($tekst->erFerdig() ? 
                    'la status for '. $kanal->getNavn() .' til i publiseringsplanen' :
                    'fjernet status for '. $kanal->getNavn() .' fra publiseringsplanen'
                ),
                (array) $tekst
            );

            // Hvis den nå er ferdig (og ikke var det før), varsle eier og some-ansvarlig
            if( $tekst->erFerdig() ) {
                $this->varsle($transport);
            }
        }

        // Send oppdatert tekst med i transporten
        $transport->setAdditionalData('tekst', $tekst);

        // Set (siste) modal
        $data = new stdClass();
        $data->response_action = 'update';
        $data->view = $this->getModalTemplate( $transport );
        $transport->setResponse($data);

        // Return response for possible later modification
        return $transport;
    }

    public function varsle( TransportInterface $transport ) {
        // Send melding til den som sendte inn forslaget
        $conversation = Conversations::startWithUser($transport->getAdditionalData('ide')->getAnsvarlig()->getSlackId());
        $message = TextPublished::getMessage($transport, $conversation->channel->id);
        $result = App::botPost('chat.postMessage', (array) $message->export());

        // Send melding til den som sendte inn forslaget
        # Bytter botToken (midlertidig)
        App::getBotTokenFromTeamId( $transport->getAdditionalData('ide')->getAnsvarlig()->getTeamId() );
        $conversation = Conversations::startWithUser($transport->getAdditionalData('ide')->getAnsvarlig()->getSlackId());
        $message = TextPublished::getMessage($transport, $conversation->channel->id);
        $result = App::botPost('chat.postMessage', (array) $message->export());
        # Bytter tilbake til transport bot token (current team)
        App::getBotTokenFromTeamId( $transport->getTeamId() );
    }

    public function getModalTemplate( TransportInterface $transport )
    {
        $tekst = $transport->getAdditionalData('tekst');
        $ide = $transport->getAdditionalData('ide');

        if( $tekst->erFerdig() ) {
            $header = 'Bra jobba! 🙌';
            $body = 'Vi har nå varslet '.
                '<@'. $ide->getAnsvarlig()->getSlackId() .'> '.
                (
                    $ide->getAnsvarlig()->getSlackId() != $ide->getEier()->getSlackId() ?
                    'og <'. $ide->getEier()->getSlackLink() .'|'. $ide->getEier()->getNameOrHandlebar() .'> ' : ''
                ).
                'om at du har lagt teksten til publisering';
        } else {
            $header = 'Ses snart? 🐣';
            $body = 'Statusen ligger fortsatt og venter på deg som kladd. '.
                'For å fullføre statusen trykker du bare på '.
                '`'. JobAccepted::getEditButtonText(). '`-knappen igjen';
        }
        // VIEW
        $view = new Modal(new PlainText($header));
        $view
            ->setClose(new PlainText('Lukk'))
            ->setCallbackId('some_text_done');
        // INTRO
        $view->getBlocks()->add(
            new Section(
                new Markdown(
                    $body
                )
            )
        );
        return $view->export();
    }
}