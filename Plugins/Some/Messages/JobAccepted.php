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


class JobAccepted
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
                ' fikser status for  `' . $kanal->getNavn() . '`'
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
        $ide = $transport->getAdditionalData('ide');

        $message = JobRequest::getMessageWithoutActions($transport, $channel_id, $kanal, $admin_id);

        error_log('TIMESTAMP: '. var_export($transport->getData()->container->message_ts,true));

        $message->setTimestamp($transport->getData()->container->message_ts);
        $message->setAsUser(true);

        $message->getBlocks()->add(
            new Context([
                new PlainText('Du svarte at du fikser denne :raised_hands:')
            ])
        );

        $modalButton = new Actions(null);
        $modalButton->setId('ide_' . $ide->getId() . '|kanal_' . $kanal->getId());
        $modalButton->getElements()->set([
            new Button(
                'some_text_edit',
                new PlainText(static::getEditButtonText())
            )
        ]);

        $message->getBlocks()->add(
            $modalButton
        );

        return $message;
    }

    public static function getEditButtonText() {
        return 'Rediger tekst';
    }
}
