<?php

namespace SlackPlugin\Some;

use UKMNorge\Slack\App\UKMApp as App;
use UKMNorge\Slack\API\View;
use UKMNorge\Slack\API\Response\Plugin\Filter\BlockAction;
use UKMNorge\Slack\API\Response\Plugin\Transport\TransportInterface;
use UKMNorge\Slack\Block\Composition\Markdown;
use UKMNorge\Slack\Block\Composition\Option;
use UKMNorge\Slack\Block\Composition\PlainText;
use UKMNorge\Slack\Block\Element\Input as InputElement;
use UKMNorge\Slack\Block\Element\Radio;
use UKMNorge\Slack\Block\Context;
use UKMNorge\Slack\Block\Divider;
use UKMNorge\Slack\Block\Input;
use UKMNorge\Slack\Block\Section;
use UKMNorge\Slack\Payload\Modal;

use UKMNorge\Some\Forslag\Ide;
use UKMNorge\Some\Kanaler\Kanal;
use UKMNorge\Some\Slack\Template;

/**
 * Åpne modal for sosiale medier-status
 */
class TextEdit extends BlockAction
{
    const ASYNC = false;

    public function condition(TransportInterface $transport)
    {
        return $transport->getActionId() == 'some_text_edit';
    }

    public function process(TransportInterface $transport)
    {
        App::getBotTokenFromTeamId( $transport->getTeamId() );

        $transport = JobDispatch::getIdsFromTransport($transport);
        $ide = $transport->getAdditionalData('ide');
        $kanal = $transport->getAdditionalData('kanal');

        $view = new View($transport->getTriggerId(), $this->getTemplate($transport, $ide, $kanal));
        $result = $view->open();

        // Return response for possible later modification
        return $transport;
    }

    public function getTemplate(TransportInterface $transport, Ide $ide, Kanal $kanal)
    {
        $tekst = $ide->getTekster()->getForKanal($kanal);

        // VIEW
        $view = new Modal(new PlainText('Skriv some-status'));
        $view
            ->setSubmit(new PlainText('Send'))
            ->setClose(new PlainText('Avbryt'))
            ->setCallbackId('some_text_save')
            ->setPrivateMetadata([
                'ide' => $ide->getId(),
                'kanal' => $kanal->getId(),
                'tekst' => $tekst->getId()
            ]);

        $view->getBlocks()->add(
            new Section(
                new Markdown(
                    'Det du skriver her skal publiseres på ' . $kanal->getNavn() . '.'
                )
            )
        );

        // SELVE TEKSTEN
        $status_input_element = new InputElement('status_text');
        $status_input_element->setInitialValue(strval($tekst->getTekst()));
        $status_input = new Input(
            new PlainText('Skriv teksten som skal brukes'),
            $status_input_element
        );
        $status_input->setHint(
            new PlainText(
                ' '
            )
        )
            ->setMultiline(true);
        $view->getBlocks()->add($status_input);

        // NOTATER
        $notater_input_element = new InputElement('status_notater');
        $notater_input_element->setInitialValue(strval($tekst->getNotater()));
        $notater_input = new Input(
            new PlainText('Notater'),
            $notater_input_element
        );
        $notater_input->setHint(
            new PlainText(
                'Skriv ned hvor bilder er lagret, hvordan dette er tenkt gjort osv.'
            )
        )
            ->setMultiline(true);
        $view->getBlocks()->add($notater_input);


        $option_kladd = new Option(
            Radio::class,
            new PlainText('Kladd'),
            'kladd'
        );
        $option_ferdig = new Option(
            Radio::class,
            new PlainText('Lagt til publisering'),
            'ferdig'
        );

        // STATUS
        $radio = new Radio(
            'text_status',
            [
                $option_kladd,
                $option_ferdig
            ]
        );
        $radio->setInitialOption(
            $tekst->erFerdig() ? $option_ferdig : $option_kladd
        );
        $radioSection = new Section(
            new Markdown('*Status*')
        );
        $radioSection->setAccessory($radio);

        $view->getBlocks()->add(
            $radioSection
        );
        if ($tekst->erKladd()) {
            $melding =
                'Vi varsler automatisk <'. $ide->getEier()->getSlackLink() .'|'. $ide->getEier()->getNameOrHandlebar() .'>';
            if( $ide->getEier()->getSlackId() != $ide->getAnsvarlig()->getSlackId() ) {
                $melding .= ' og <@' . $ide->getAnsvarlig()->getSlackId() . '>';
            }
            $view->getBlocks()->add(
                new Context([
                    new Markdown(
                        'Ikke velg "' . $option_ferdig->getText()->getText() . '" '.
                        'før du har lagt statusen klar til publisering på '. $kanal->getNavn().'. '.
                        $melding . ' om at den er lagt klar.'
                    )
                ])
            );
        }

        // INFO OM FORSLAGET
        $view->getBlocks()->add(
            new Divider()
        );
        $view->getBlocks()->add(
            new Section(
                new Markdown('*Informasjon fra <'. $ide->getEier()->getSlackLink() .'|'. $ide->getEier()->getNameOrHandlebar() .'>, som kom med forslaget:*')
            )
        );
        Template::getStatusSuggestionPreview(
            $view,
            $ide
        );

        return $view->export();
    }
}
