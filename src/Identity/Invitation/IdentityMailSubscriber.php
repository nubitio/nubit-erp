<?php

declare(strict_types=1);

namespace App\Identity\Invitation;

use App\Identity\Activity\ActivityRecorderInterface;
use App\Identity\Activity\ActivityType;
use App\Identity\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\AdminBundle\Identity\Event\PasswordResetRequested;
use Nubit\AdminBundle\Identity\Event\UserInvited;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * The bundle issues tokens and announces them with events; delivering them is the app's job.
 * A failed delivery is logged, never thrown: for password recovery the HTTP answer must not
 * reveal whether the account exists, and an admin can simply re-send an invitation.
 */
final readonly class IdentityMailSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private ActivityRecorderInterface $activity,
        private RequestStack $requests,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private string $mailerFrom,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            UserInvited::class => 'onUserInvited',
            PasswordResetRequested::class => 'onPasswordResetRequested',
        ];
    }

    public function onUserInvited(UserInvited $event): void
    {
        $link = $this->baseUrl() . '/#/accept-invitation?token=' . $event->token;
        $this->send(
            $event->email,
            'Te invitaron a Nubit ERP',
            sprintf(
                "Hola,\n\nTe invitaron a usar Nubit ERP. Crea tu contraseña desde este enlace:\n\n%s\n\nEl enlace vence el %s y solo se puede usar una vez.\nSi no esperabas esta invitación, ignora este correo.\n",
                $link,
                $event->expiresAt->format('d/m/Y'),
            ),
        );
    }

    public function onPasswordResetRequested(PasswordResetRequested $event): void
    {
        $user = $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['username' => $event->userIdentifier, 'deletedAt' => null]);
        if (!$user instanceof User) {
            return;
        }
        $this->activity->record(
            ActivityType::PASSWORD_RESET_REQUESTED,
            sprintf('%s pidió restablecer su contraseña', $user->getUsername()),
            actor: $user,
        );
        $email = $user->getEmail();
        if ($email === null) {
            return;
        }
        $link = $this->baseUrl() . '/#/reset-password?token=' . $event->token;
        $this->send(
            $email,
            'Restablece tu contraseña de Nubit ERP',
            sprintf(
                "Hola %s,\n\nPara elegir una nueva contraseña usa este enlace:\n\n%s\n\nVence a las %s y solo se puede usar una vez.\nSi no lo pediste tú, ignora este correo: tu contraseña actual sigue funcionando.\n",
                $user->getUsername(),
                $link,
                $event->expiresAt->format('H:i'),
            ),
        );
    }

    private function baseUrl(): string
    {
        return $this->requests->getCurrentRequest()?->getSchemeAndHttpHost() ?? '';
    }

    private function send(string $to, string $subject, string $text): void
    {
        try {
            $this->mailer->send(
                new Email()
                    ->from($this->mailerFrom)
                    ->to($to)
                    ->subject($subject)
                    ->text($text),
            );
        } catch (TransportExceptionInterface $exception) {
            $this->logger->error('Identity e-mail could not be delivered.', [
                'subject' => $subject,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
