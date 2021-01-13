<?php

namespace SlackPlugin\Some\Messages;

use UKMNorge\Slack\API\Response\Plugin\Transport\TransportInterface;
use UKMNorge\Slack\Block\Composition\Markdown;
use UKMNorge\Slack\Block\Context;
use UKMNorge\Slack\Block\Section;
use UKMNorge\Slack\Payload\Message;

class TextPublished
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
                ' har lagt status for `' . $kanal->getNavn() . '` i publiseringsplanen :tada:'
        );

        // Initier melding
        $message = new Message($channel_id, $text);

        // Tekst som blokk
        $message->getBlocks()->add(
            new Section($text)
        );

        // Lenke til forslaget
        $message->getBlocks()->add(
            new Context(
                [
                    new Markdown('Forslag: <' . $ide->getLink() . '>')
                ]
            )
        );

        return $message;
    }
}
