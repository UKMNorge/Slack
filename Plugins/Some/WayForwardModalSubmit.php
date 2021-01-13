<?php

namespace SlackPlugin\Some;

use DateTime;
use stdClass;

use UKMNorge\Slack\API\Response\Plugin\Filter\ViewSubmission;
use UKMNorge\Slack\API\Response\Plugin\Transport\TransportInterface;
use UKMNorge\Slack\Block\Composition\PlainText;
use UKMNorge\Slack\Block\Composition\Markdown;
use UKMNorge\Slack\Block\Element\Input as InputElement;
use UKMNorge\Slack\Block\Divider;
use UKMNorge\Slack\Block\Section;
use UKMNorge\Slack\Block\Input;
use UKMNorge\Slack\Payload\Modal;

use UKMNorge\Some\Forslag\Ide;
use UKMNorge\Some\Forslag\Ideer;
use UKMNorge\Some\Forslag\Write as WriteIde;
use UKMNorge\Some\Kanaler\Kanaler;
use UKMNorge\Some\Log\Event;
use UKMNorge\Some\Slack\Template;

use UKMNorge\SlackApp\Some;



/**
 * Konverter data fra SuggestModal til en Some-idé
 */
class WayForwardModalSubmit extends ViewSubmission {
    const ASYNC = false;

    public function condition( TransportInterface $transport ) {
        return $transport->getView()->getCallbackId() == 'modal_some_wayforward';
    }

    public function process( TransportInterface $transport ){
        // Last inn metadata
        $metadata = json_decode($transport->getData()->view->private_metadata);
        
        // Oppdater ideen
        $ide = Ideer::getById( intval($metadata->ide) );
        $transport->setAdditionalData('ide', $ide);
        $transport = $this->lagreIde( $transport );
        
        // Opprett view
        $data = new stdClass();
        $data->response_action = 'update';
        $data->view = $this->getTemplate( $transport );
        $transport->setResponse($data);

        // Return response for possible later modification
        return $transport;
    }

    /**
     * Lagre ideen i UKM-systemet
     *
     * @return Ide
     */
    public function lagreIde( TransportInterface $transport ) {
        $submitdata = $transport->getView()->collectSubmittedData();
        $ide = $transport->getAdditionalData('ide');

        // Some-ansvarlig har endret kanaler
        if( isset($submitdata['channels'])) {
            $transport->setAdditionalData('modified_channels', true);
            error_log('Kanal-select: korrigerer');
            $ide = $this->korrigerKanaler(
                $ide,
                explode(',', $submitdata['channels']->getValue()),
                $transport
            );
        } else {
            error_log('Kanal-select: ingen endringer');
        }
        
        // Some-ansvarlig har endret publiseringsdato
        if( isset($submitdata['publish_time'])) {
            error_log('Publiseringsdato: korrigerer');
            $transport->setAdditionalData('modified_publish_time', true);
            $ide = $this->korrigerPubliseringsdato(
                $ide,
                $submitdata['publish_time']->getValue(),
                $transport
            );
        } else {
            error_log('Publiseringsdato: ingen endringer');
        }

        WriteIde::save($ide);
        
        $transport->setAdditionalData('ide', $ide);

        return $transport;
    }

    /**
     * Korriger hvilke kanaler vi jobber videre med
     *
     * @return Ide
    */
    private function korrigerKanaler( Ide $ide, Array $valgte_kanaler, TransportInterface $transport ) {
        $kanal_tekst = '';
        // Kanaler
        foreach( $valgte_kanaler as $kanal_id ) {
            if(is_null($kanal_id) || empty($kanal_id)) {
                continue;
            }
            $kanal = Kanaler::getById( $kanal_id );
            
            if( !$ide->getKanaler()->har($kanal)) {
                error_log('Legg til kanal '. $kanal->getId());
                $ide->getKanaler()->add( $kanal );
            }

            $kanal_tekst .= $kanal->getNavn() .', ';
        }

        foreach( $ide->getKanaler()->getAll() as $kanal ) {
            if( !in_array($kanal->getId(), $valgte_kanaler)) {
                error_log('Fjern kanal '. $kanal_id);
                $ide->getKanaler()->remove($kanal);
            }
        }

        $kanal_tekst = rtrim($kanal_tekst, ', ');

        Event::create(
            Ide::class,
            $ide->getId(),
            'kanaler_oppdatert',
            $transport->getData()->team->id,
            $transport->getData()->user->id,
            'oppdaterte kanaler til '. $kanal_tekst .'.',
            $ide->getKanaler()->__toArray()
        );

        return $ide;
    }

    /**
     * Korriger ønsket publiseringsdato
     *
     * @return Ide
     */
    public function korrigerPubliseringsdato( Ide $ide, String $dato, TransportInterface $transport  ) {
        $ide->setPubliseringsdato( new DateTime( $dato ) );

        Event::create(
            Ide::class,
            $ide->getId(),
            'publisering_oppdatert',
            $transport->getData()->team->id,
            $transport->getData()->user->id,
            'oppdaterte publiseringsdato til '. $dato .'.',
            ['dato' => $dato]
        );

        return $ide;
    }

    /**
     * Hent modal-template
     *
     * @return Modal
     */
    public function getTemplate( TransportInterface $transport ) {
        $ide = $transport->getAdditionalData('ide');
        // VIEW
        $metadata = [
            'ide' => $ide->getId(),
        ];
        if( $transport->getAdditionalData('modified_channels')) {
            $metadata['modified_channels'] = true;
        }
        if( $transport->getAdditionalData('modified_publish_time')) {
            $metadata['modified_publish_time'] = true;
        }

        $view = new Modal(new PlainText('Fordel kanal-ansvar'));
        $view
            ->setSubmit( new PlainText('Send'))
            ->setClose( new PlainText('Avbryt'))
            ->setCallbackId('modal_some_wayforward_tekster')
            ->setPrivateMetadata( $metadata );

        // INTRO
        $view->getBlocks()->add(
            new Section( 
                new Markdown(
                    '*Dette er infoen vi har så langt*'
                )
            )
        );

        // PREVIEW
        $view->getBlocks()->add(
            new Divider()
        );        
        Template::getStatusSuggestionPreview(
            $view,
            $ide
        );

        foreach( $ide->getKanaler()->getAll() as $kanal ) {
            $view->getBlocks()->add(
                new Divider()
            );

            $view->getBlocks()->add(
                new Section(
                    new Markdown('*'. $kanal->getEmoji() .'\t'. $kanal->getNavn() .'*')
                )
            );

            $view->getBlocks()->add(
                Some::getUserSelect(
                    $transport,
                    new Markdown('Hvem sikrer at det blir laget ferdig?'),
                    'user_'. $kanal->getId()
                )
            );

            // ØNSKET TEKST
            $tekst = new Input(
                new PlainText('Skriv teksten du ønsker'),
                new InputElement('status_text_'. $kanal->getId())
            );
            $tekst->setHint(
                new PlainText(
                    'Hvis det er noen andre enn deg som skal følge opp '. 
                    $kanal->getNavn() .' kan du velge å hoppe over dette feltet, eller å bruke '.
                    'det til å skrive en kommentar til den du videresender oppgaven til.'
                )
            )
            ->setMultiline(true)
            ->setOptional(true);
            $view->getBlocks()->add( $tekst );
        }

        return $view->export();
    }
}