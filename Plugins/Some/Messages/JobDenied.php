<?php

namespace SlackPlugin\Some\Messages;

use UKMNorge\Slack\API\Response\Plugin\Transport\TransportInterface;
use UKMNorge\Slack\Block\Composition\Markdown;
use UKMNorge\Slack\Block\Composition\PlainText;
use UKMNorge\Slack\Block\Element\Button;
use UKMNorge\Slack\Block\Actions;
use UKMNorge\Slack\Block\Context;
use UKMNorge\Slack\Block\Section;
use UKMNorge\Slack\Payload\Message;

use UKMNorge\Some\Kanaler\Kanal;


class JobDenied
{

    /**
     * Hent melding som skal sendes til some-ansvarlig
     * 
     * @return Message
     */
    public static function getMessage(TransportInterface $transport, String $channel_id)
    {
        $ide = $transport->getAdditionalData('ide');
        $kanal = $transport->getAdditionalData('kanal');

        $text = new Markdown(
            '<@' . $transport->getData()->user->id . '> ' .
                ' kan ikke fikse status for  `' . $kanal->getNavn() . '` :disappointed:'
        );

        $message = new Message(
            $channel_id,
            $text
        );

        $message->getBlocks()->add(
            new Section($text)
        );
        $message->getBlocks()->add(
            new Context(
                [
                    new Markdown('Forslag: <' . $ide->getLink() . '>')
                ]
            )
        );

        $actions = new Actions(null);
        $actions->setId('ide_' . $ide->getId() . '|kanal_' . $kanal->getId());
        $actions->getElements()->set([
            new Button(
                'some_job_dispatch',
                new PlainText('Send til noen andre')
            )
        ]);
        $message->getBlocks()->add($actions);

        return $message;
    }

    /**
     * Hent oppdatert tekst (uten i fix / i cant fix-knapper)
     * 
     * @return Message
     */
    public static function getUpdateMessage(TransportInterface $transport, String $channel_id, String $admin_id)
    {
        $kanal = $transport->getAdditionalData('kanal');

        $message = JobRequest::getMessageWithoutActions($transport, $channel_id, $kanal, $admin_id);
        $message->setTimestamp($transport->getData()->container->message_ts);
        $message->setAsUser(true);
        $message->getBlocks()->add(
            new Context([
                new PlainText('Du svarte at du ikke kan gjøre det. No worries, vi prøver igjen senere! :relaxed: ')
            ])
        );

        return $message;
    }
}
