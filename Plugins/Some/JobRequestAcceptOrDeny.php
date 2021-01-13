<?php

namespace SlackPlugin\Some;

use SlackPlugin\Some\Messages\JobAccepted;
use SlackPlugin\Some\Messages\JobDenied;
use UKMNorge\Slack\API\Chat;
use UKMNorge\Slack\API\Conversations;
use UKMNorge\Slack\API\Response\Plugin\Filter\BlockAction;
use UKMNorge\Slack\API\Response\Plugin\Transport\TransportInterface;
use UKMNorge\Slack\App\UKMApp as App;

use UKMNorge\Some\Log\Event;

class JobRequestAcceptOrDeny extends BlockAction {
    const ASYNC = false;

    public function condition( TransportInterface $transport ) {
        return in_array( $transport->getData()->actions[0]->action_id, ['some_text_accept_job', 'some_text_deny_job']);
    }

    public function process( TransportInterface $transport ){
        App::getBotTokenFromTeamId( $transport->getTeamId() );

        $status = $transport->getData()->actions[0]->action_id == 'some_text_accept_job' ? 'accept' : 'deny';

        $transport = JobDispatch::getIdsFromTransport($transport);
        $ide = $transport->getAdditionalData('ide');
        $kanal = $transport->getAdditionalData('kanal');
        $tekst = $ide->getTekster()->getForKanal($kanal);

        // Varsle some-ansvarlig (den som sendte oppgaven videre)
        $admin_id = substr(
            $transport->getData()->message->text,
            strpos($transport->getData()->message->text, '<@') + 2,
            strpos($transport->getData()->message->text, '>') - 2
        );

        $conversation = Conversations::startWithUser($admin_id);
        if( $status == 'accept' ) {
            $message = JobAccepted::getMessage($transport, $conversation->channel->id);
            $updatedMessage = JobAccepted::getUpdateMessage( $transport, $transport->getData()->container->channel_id, $admin_id );

            Event::create(
                Ide::class,
                $ide->getId(),
                'jobb_godtatt',
                $transport->getTeamId(),
                $transport->getUserId(),
                'tar ansvar for status til ' . $kanal->getNavn() . '.',
                (array) $tekst
            );
        } else {
            $message = JobDenied::getMessage($transport, $conversation->channel->id);
            $updatedMessage = JobDenied::getUpdateMessage( $transport, $transport->getData()->container->channel_id, $admin_id );

            Event::create(
                Ide::class,
                $ide->getId(),
                'jobb_ikke_godtatt',
                $transport->getTeamId(),
                $transport->getUserId(),
                'kunne ikke ta ansvar for status til ' . $kanal->getNavn() . '.',
                (array) $tekst
            );
        }
        $result = Chat::post($message);
        $result = Chat::update($updatedMessage);

        // Return response for possible later modification
        return $transport;
    }
}