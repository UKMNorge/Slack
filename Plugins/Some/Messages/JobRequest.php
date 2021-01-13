<?php

namespace SlackPlugin\Some\Messages;

use UKMNorge\Slack\API\Response\Plugin\Transport\TransportInterface;
use UKMNorge\Slack\Block\Composition\Markdown;
use UKMNorge\Slack\Block\Composition\PlainText;
use UKMNorge\Slack\Block\Element\Button;
use UKMNorge\Slack\Block\Actions;
use UKMNorge\Slack\Block\Section;
use UKMNorge\Slack\Cache\User\Users;
use UKMNorge\Slack\Payload\Message;

use UKMNorge\Some\Kanaler\Kanal;


class JobRequest {

    public static function getMessage( TransportInterface $transport, String $channel_id, Kanal $kanal ) {
        $ide = $transport->getAdditionalData('ide');
        
        $message = static::getMessageWithoutActions($transport, $channel_id, $kanal, $transport->getData()->user->id);
    
        $ican = new Button(
            'some_text_accept_job',
            new PlainText('I fix')
        );
        $ican->setStyle('primary');
        
        $icannot = new Button(
            'some_text_deny_job',
            new PlainText('Sorry, det går ikke')
        );
        $icannot->setStyle('danger');
        
        $confirm = new Actions(null);
        $confirm->setId('ide_'. $ide->getId() .'|kanal_'. $kanal->getId());
        $confirm->getElements()->set([
            $ican,
            $icannot
            ]);
            
        $message->getBlocks()->add(
            $confirm
        );

        return $message;
    }

    public static function getMessageWithoutActions( TransportInterface $transport, String $channel_id, Kanal $kanal, String $admin_id ) {
        $ide = $transport->getAdditionalData('ide');

        $text = new Markdown(
            '<@'. $admin_id .'> '.
            'ønsker at du fikser en status for `'. $kanal->getNavn() .'`'.
            ' som skal publiseres '. $ide->getPubliseringsdato()->format('d.m')
        );

        $message = new Message(
            $channel_id,
            $text
        );
        $message->getBlocks()->add(
            new Section($text)
        );
        
        return $message;
    }
}