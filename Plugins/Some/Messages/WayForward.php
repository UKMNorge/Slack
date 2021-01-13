<?php

namespace SlackPlugin\Some\Messages;

use UKMNorge\Slack\API\Response\Plugin\Transport\TransportInterface;
use UKMNorge\Slack\Block\Composition\Markdown;
use UKMNorge\Slack\Block\Composition\PlainText;
use UKMNorge\Slack\Block\Element\Button;
use UKMNorge\Slack\Block\Section;
use UKMNorge\Slack\Cache\User\Users;
use UKMNorge\Slack\Payload\Message;
use UKMNorge\Some\Slack\Template;

class WayForward {

    public static function getMessage( TransportInterface $transport, String $channel_id ) {
        $kanaler = '';
        foreach( $transport->getAdditionalData('ide')->getKanaler()->getAll() as $kanal ) {
            $kanaler .= '`'. $kanal->getNavn() .'`, ';
        }
        $kanaler = rtrim($kanaler, ', ');

        $user = Users::getBySlackId( $transport->getTeamId(), $transport->getData()->user->id );
        
        $message = new Message(
            $channel_id,
            new Markdown(
                '<'. $user->getSlackLink() .'|'. $user->getNameOrHandlebar() .'> vil poste en status på '. $kanaler
            )
        );

        $message->getBlocks()->add(
            new Section(
                new Markdown(
                    '*<'. $user->getSlackLink() .'|'. $user->getNameOrHandlebar() .'> vil poste en status på '. $kanaler .' *'
                )
            )
        );

        $message = Template::getStatusSuggestionPreview($message, $transport->getAdditionalData('ide'));

        $buttonSection = new Section(
            new PlainText(' ')
        );
        $buttonSection->setId('ide_'. $transport->getAdditionalData('ide')->getId());
        $buttonSection->setAccessory(
            new Button(
                'some_suggest_way_forward',
                new PlainText('Avgjør veien videre')
            )
        );

        $message->getBlocks()->add(
            $buttonSection
        );

        return $message;
    }
}