<?php

namespace SlackPlugin\Some\Messages;

use UKMNorge\Slack\API\Response\Plugin\Transport\TransportInterface;
use UKMNorge\Slack\Block\Context;
use UKMNorge\Slack\Block\Divider;
use UKMNorge\Slack\Block\Section;
use UKMNorge\Slack\Block\Composition\Markdown;
use UKMNorge\Slack\Payload\Message;

use UKMNorge\Slack\Cache\User\Users;

class WayForwardSubmitterFeedback
{
    public static function getMessage(TransportInterface $transport, String $channel_id)
    {
        $submitdata = $transport->getView()->collectSubmittedData();
        $metadata = json_decode($transport->getData()->view->private_metadata);
        $ide = $transport->getAdditionalData('ide');

        $intro_tekst = '*Vi har tatt status-forslaget ditt videre.*';

        $message = new Message(
            $channel_id,
            new Markdown($intro_tekst)
        );

        $intro_pluss = new Section(new Markdown($intro_tekst));

        // Vi har endret noe siden innsending
        if (isset($metadata->modified_channels) || isset($metadata->modified_publish_time)) {
            if (isset($metadata->modified_channels)) {
                $endret_tekst = 'Kanalen er endret til ';
                $siste = $ide->getKanaler()->getAntall() - 1;
                $i = 0;
                foreach ($ide->getKanaler()->getAll() as $kanal) {
                    $i++;
                    $endret_tekst .= strtolower($kanal->getNavn()) . ($i == $siste ? ' og ' : ', ');
                }
                $endret_tekst = rtrim($endret_tekst, ', ');
            }

            if (isset($metadata->modified_publish_time)) {
                if (isset($metadata->modified_channels)) {
                    $endret_tekst .= ", og publiseringsdato er satt til ";
                } else {
                    $endret_tekst = 'Vi har endret publiseringsdato til ';
                }
                $endret_tekst .= $ide->getPubliseringsdato()->format('d.m') . '.';
            }

            $intro_pluss = new Section(
                new Markdown($intro_tekst ."\n". $endret_tekst)
            );

            
        }
        
        // Start meldingen        
        $message->getBlocks()->add(
            $intro_pluss
        );
        // Context
        $message->getBlocks()->add( 
            new Context([
                new Markdown('<'. $ide->getLink() . '>')
            ])
        );

        // Kontaktpersoner
        $message->getBlocks()->add(
            new Section(
                new Markdown("*Dine kontaktpersoner er nå:*\n")
            )
        );
        foreach ($ide->getKanaler()->getAll() as $kanal) {
            // Velger admin ingen andre, blir jobben admin sin (snooze = loose?)
            if (isset($submitdata['user_' . $kanal->getId()])) {
                $user_id = $submitdata['user_' . $kanal->getId()]->getValue();
                $team_id = $transport->getTeamId();  // TODO: team_id må inn i submitdata
            } else {
                $team_id = $transport->getTeamId(); 
                $user_id = $transport->getData()->user->id;
            }

            $user = Users::getBySlackId($team_id, $user_id);

            $message->getBlocks()->add(
                new Section(
                    new Markdown($kanal->getNavn() . ": <" . $user->getSlackLink() .'|'. $user->getNameOrHandlebar() .'>')
                )
            );
        }
        $message->getBlocks()->add(
            new Section(
                new Markdown("Kanaler og publiseringsdato: <" . $user->getSlackLink() .'|'. $user->getNameOrHandlebar() .'>')
            )
        );

        return $message;
    }
}
