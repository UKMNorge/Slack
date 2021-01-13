<?php

namespace SlackPlugin\Some;

use UKMNorge\Slack\App\UKMApp as App;
use UKMNorge\Slack\API\View;
use UKMNorge\Slack\API\Response\Plugin\Filter\Trigger;
use UKMNorge\Slack\API\Response\Plugin\Transport\TransportInterface;
use UKMNorge\Slack\Block\Composition\PlainText;
use UKMNorge\Slack\Block\Composition\Markdown;
use UKMNorge\Slack\Block\Composition\Option;
use UKMNorge\Slack\Block\Context;
use UKMNorge\Slack\Block\Divider;
use UKMNorge\Slack\Block\Input;
use UKMNorge\Slack\Block\Element\Input as InputElement;
use UKMNorge\Slack\Block\Element\Datepicker;
use UKMNorge\Slack\Block\Element\Select;
use UKMNorge\Slack\Block\Section;
use UKMNorge\Slack\Payload\Modal;
use UKMNorge\Slack\Payload\PayloadInterface;

use UKMNorge\SlackApp\Some;

use UKMNorge\Some\Slack\Template;

/**
 * Åpne modal for sosiale medier-status
*/
class SuggestModal extends Trigger {
    const ASYNC = true;

    private $is_admin;

    public function condition( TransportInterface $transport ) {
        error_log('COND: '. $transport->getData()->callback_id .' == some_status_suggest');
        return $transport->getData()->callback_id == 'some_status_suggest';
    }

    public function process( TransportInterface $transport ) {
        App::getBotTokenFromTeamId( $transport->getTeamId() );
        
        $view = new View($transport->getData()->trigger_id, $this->getTemplate($transport));
        $result = $view->open();

        // Return response for possibel later modification
        return $transport;
    }

    public function getTemplate( TransportInterface $transport) {
        // VIEW
        $view = new Modal(new PlainText('Hva vil du dele?'));
        $view
            ->setSubmit( new PlainText('Send forespørsel'))
            ->setClose( new PlainText('Avbryt'))
            ->setCallbackId('modal_some_suggest');
        $blocks = [];

        // INTRO
        $intro = new Section( 
            new Markdown(
                '*Ikke vær redd for å fylle ut feil, vi er her for å hjelpe 🧙* '.
                '\n\n '.
                'Når du fyller ut skjemaet, kobler vi på de som hjelper deg videre. '.
                'Når alle er fornøyde, legger vi det i publiseringsplanen. '.
                '\n\n'.
                'Sjekk gjerne våre <https://some.ukm.no/|tips og triks når vi ønsker å dele noe>'
            )
        );
        $blocks[] = $intro;

        // HVA SOM DELES
        $dele = new Input(
            new PlainText('Hva vil du dele?'),
            new InputElement('status_type')
        );
        $dele->setHint( 
            new PlainText('F.eks. bilde fra norgescupen, historie fra kunstworkshop, bilde/film for å spre et arrangement.')
        );
        $blocks[] = $dele;

        // ØNSKET TEKST
        $tekst = new Input(
            new PlainText('Skriv teksten du ønsker'),
            new InputElement('status_text')
        );
        $tekst->setHint(
            new PlainText(
                'Er du usikker på hvordan du skal ordlegge deg? '.
                'Da kan du skrive hva du ønsker å oppnå, '. 
                'hva du ønsker å fortelle, eller hvorfor du vil dele det.'
            )
        )
        ->setMultiline(true);
        $blocks[] = $tekst;

        // KANALER
        $blocks[] = Template::getKanalSelector(
            new Markdown('*Kanaler*\n\nHvilke kanaler tror du passer?')
        );

        $blocks[] = new Context(
        [
            new Markdown(
                'Ulike kanaler passer til ulike budskap. '. 
                'Se gjerne <https://media.ukm.no/some/kanaler/|kanalguiden> '. 
                'vår for tips om hva du bør tenke på.'
                )
        ] 
        );
        $blocks[] = new Section(
            new Markdown('*Publisering*')
        );


        $publisering = new Section(
            new PlainText('Når ønsker du publisering?')
        );
        $datepicker = new Datepicker('publish_time');
        $datepicker
            ->setInitialDate(date('Y-m-d'))
            ->setPlaceholder(new PlainText('Velg dato'));
        $publisering->setAccessory($datepicker);
        $blocks[] = $publisering;
        
        // Legg til alle blocks
        $view->getBlocks()->set($blocks);
        
        // Legg til admin-stuff
        if( Some::isAdmin( $transport->getData()->team->id, $transport->getData()->user->id ) ) { 
            $view = $this->addAdminOptions( $view, $transport );
        }
        
        return $view->export();
    }
    
    
    public function addAdminOptions( PayloadInterface $view, TransportInterface $transport ) {
        $userOptions = [];
        $team_id = $transport->getData()->team->id;
        $stine = Some::getAdmin( $team_id, '@stinegranly');

        foreach( Some::getUsers( $team_id )->getAll() as $user ) {
            $userOptions[] = new Option(
                Select::class,
                new PlainText( !empty($user->getRealName()) ? $user->getRealName() : $user->getName()),
                strval($user->getName())
            );
        }

        $divider = new Divider();
        
        $hint = new Context([
                new PlainText('Du får opp dette valget fordi du er some-admin 😎')
        ]);

        $info = new Section(
            new Markdown("*Veien videre*")
        );

        $selectUsers = new Section(
            new PlainText('Hvem skal avgjøre videre fremdrift?')
        );
        
        $select = new Select(
            'wayforward_user',
            $userOptions,
            new PlainText('Velg bruker')
        );
        $select->setInitialOption(
            new Option(
                Select::class,
                new PlainText($stine->getRealName()),
                $stine->getName()
            )
        );
        $selectUsers->setAccessory($select);

        $view->getBlocks()->add( $divider );
        $view->getBlocks()->add( $info );
        $view->getBlocks()->add( $hint );
        $view->getBlocks()->add( $selectUsers );

        return $view;
    }
}