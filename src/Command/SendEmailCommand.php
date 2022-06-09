<?php

namespace App\Command;

use App\Domain\Common\EmailDto;
use App\Service\EmailService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SendEmailCommand extends Command
{
    protected static $defaultName = 'app:send-email';
    private EmailService $emailService;

    public function __construct(EmailService $emailService)
    {
        parent::__construct();
        $this->emailService = $emailService;
    }

    protected function configure()
    {
        $this->setHelp('Send a email to a user');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $io->title('Demo Title');
            $io->text('Demo text');
            $email = new EmailDto(
                from: $_ENV['SMTP_USER'],
                to: 'johangm90@gmail.com',
                subject: 'Demo subject',
                body: 'Demo body',
            );
            $result = $this->emailService->sendEmail($email);
            if ($result['success']) {
                $io->success($result['msg']);
            } else {
                $io->error($result['msg']);
            }
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}