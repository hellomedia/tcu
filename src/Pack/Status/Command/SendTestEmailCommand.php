<?php

namespace App\Pack\Status\Command;

use App\Pack\Status\Message\TestEmail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:test:email',
    description: 'Send a test email through the queue',
)]
class SendTestEmailCommand extends Command
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // send a messenger message that sends an email in the queue 
        $this->bus->dispatch(new TestEmail(
            recipient: 'nicolas.sauveur@gmail.com',
            subject: 'test messenger queue',
        ));

        $io->success('message sent to the queue');
           
        return Command::SUCCESS;
    }
}
