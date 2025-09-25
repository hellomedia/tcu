<?php

namespace Pack\Status\Controller;

use App\Controller\BaseController;
use Pack\Status\Entity\TestMessageLog;
use Pack\Status\Exception\MessengerException;
use Pack\Status\Message\TestEmail;
use Pack\Status\Message\TestError;
use Pack\Status\Message\TestMessage;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Manual Checks:
 * /_check/messenger/email 
 * /_check/messenger/email
 * 
 * Automated check:
 * /_check/messenger/consumer
 * Returns 204 if status seems OK
 * Throws (return 500) if issue (message not consumed after X seconds)
 * Usage:
 *  - ping /_check/messenger/consumer in uptime robot
 */
class MessengerStatusController extends BaseController
{
    public function __construct(
        private MessageBusInterface $bus,
        private EntityManager $entityManager,
    )
    {
    }

    #[Route(name: "messenger_check_email", path: "/_check/messenger/email")]
    public function checkMessengerEmail(): Response
    {
        // send a messenger message that sends an email in the queue 
        $this->bus->dispatch(new TestEmail(
            recipient: 'nicolas.sauveur@gmail.com',
            subject: 'test messenger queue',
        ));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(name: "messenger_check_error", path: "/_check/messenger/error")]
    public function checkFailedMessage(): Response
    {
        // send a messenger message that throws an exception in the queue 
        $this->bus->dispatch(new TestError());

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Checks if messages are being consumed
     * 
     * Ping this endpoint for monitoring
     */
    #[Route(name: "messenger_check_consumer", path: "/_check/messenger/consumer")]
    public function checkConsumer(): Response
    {
        $testMessage = new TestMessage();

        // send message to queue
        $this->bus->dispatch($testMessage);

        // wait to see if message is processed
        $this->_waitForMessageProcessing($testMessage);

        // continue if message was processed
        return new Response(content: 'OK', status: Response::HTTP_OK);
    }

    private function _waitForMessageProcessing(TestMessage $testMessage): void
    {
        for ($i = 0; $i < 100; $i++) {
            usleep(100000); // Sleep 100ms

            if ($this->_isMessageProcessed($testMessage)) {
                return;
            }
        }

        // throw exception if message was not processed after 10 seconds
        throw new MessengerException("Message in queue not processed after 10 seconds. Is the queue being consumed ?");
    }

    /**
     * When message is processed, a TestMessageLog entry exists with the corresponding messageId
     */
    private function _isMessageProcessed(TestMessage $testMessage): bool
    {
        $testMessageLog = $this->entityManager->getRepository(TestMessageLog::class)->findOneBy([
            'messageId' => $testMessage->getId()
        ]);

        return $testMessageLog != null;
    }
}
