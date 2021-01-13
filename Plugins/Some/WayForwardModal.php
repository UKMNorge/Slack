<?php

namespace SlackPlugin\Some;

use UKMNorge\Slack\App\UKMApp as App;
use UKMNorge\Slack\API\View;
use UKMNorge\Slack\API\Response\Plugin\Filter\BlockAction;
use UKMNorge\Slack\API\Response\Plugin\Transport\TransportInterface;
use UKMNorge\Slack\Block\Composition\PlainText;
use UKMNorge\Slack\Block\Composition\Markdown;
use UKMNorge\Slack\Block\Context;
use UKMNorge\Slack\Block\Element\Datepicker;
use UKMNorge\Slack\Block\Section;
use UKMNorge\Slack\Payload\Modal;

use UKMNorge\Some\Forslag\Ideer;
use UKMNorge\Some\Forslag\Ide;
use UKMNorge\Some\Slack\Template;

/**
 * Åpne modal for sosiale medier-status
*/
class WayForwardModal extends BlockAction {
    const ASYNC = false;

    public function condition( TransportInterface $transport ) {
        error_log(var_export($transport,true));
        error_log('COND: '. $transport->getData()->actions[0]->action_id .' == some_suggest_way_forward');
        return $transport->getData()->actions[0]->action_id == 'some_suggest_way_forward';
    }

    public function process( TransportInterface $transport ) {
        App::getBotTokenFromTeamId( $transport->getTeamId() );

        $ide_id = str_replace('ide_','',$transport->getData()->actions[0]->block_id);
        $ide = Ideer::getById( intval($ide_id));

        $view = new View($transport->getData()->trigger_id, $this->getTemplate( $ide ));
        $result = $view->open();

        // Return response for possible later modification
        return $transport;
    }

    public function getTemplate( Ide $ide ) {
        // VIEW
        $view = new Modal(new PlainText('Hva gjør vi nå?'));
        $view
            ->setSubmit( new PlainText('Videre'))
            ->setClose( new PlainText('Avbryt'))
            ->setCallbackId('modal_some_wayforward')
            ->setPrivateMetadata( ['ide' => $ide->getId()] );

        // INTRO
        $view->getBlocks()->add(
            new Section( 
                new Markdown(
                    'Det er du som bestemmer veien videre, og ingenting skjer før du har svart på dette (sorry). '
                )
            )
        );

        // ACTIONS
        $view->getBlocks()->add(
            Template::getKanalSelector(
                new Markdown(
                    '*Velg hvilke kanaler vi skal jobbe videre med*'
                ),
                Template::getKanalSelectorInitialOptions( $ide )
            )
        );

        $publisering = new Section(
            new Markdown('*Når vil du publisere?*')
        );
        $datepicker = new Datepicker('publish_time');
        $datepicker
            ->setInitialDate($ide->getPubliseringsdato()->format('Y-m-d'))
            ->setPlaceholder(new PlainText('Velg dato'));
        $publisering->setAccessory($datepicker);

        $view->getBlocks()->add(
            $publisering
        );

        $view->getBlocks()->add(
            new Context([
                new Markdown(
                    '<'. $ide->getEier()->getSlackLink() .'|'. $ide->getEier()->getNameOrHandlebar() .'> ønsker i utgangspunktet '. 
                    $ide->getPubliseringsdato()->format('d.m').
                    ' men du står fritt til å korrigere. '.
                    'Vi varsler automatisk '. explode(' ', $ide->getEier()->getRealName())[0] .'.'
                )
            ])
        );

        return $view->export();
    }
}