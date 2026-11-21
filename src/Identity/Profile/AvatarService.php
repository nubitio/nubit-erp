<?php

declare(strict_types=1);

namespace App\Identity\Profile;

use App\Identity\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** A user's profile photo: validated on the way in, served only to its owner. */
final readonly class AvatarService
{
    public const int MAX_BYTES = 2_097_152;

    /** Server-detected types we accept; SVG is out because it can carry script. */
    private const array TYPES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AvatarStorage $avatars,
    ) {}

    /** @throws ProfileViolations */
    public function set(User $user, UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw ProfileViolations::of('file', 'No se pudo recibir la imagen. Inténtalo de nuevo.');
        }
        $size = $file->getSize();
        if ($size === false || $size > self::MAX_BYTES) {
            throw ProfileViolations::of('file', 'La imagen supera los 2 MB.');
        }
        // Detected from the bytes, not from the name or the Content-Type the client sent.
        $mime = $file->getMimeType();
        if ($mime === null || !isset(self::TYPES[$mime])) {
            throw ProfileViolations::of('file', 'Sube una imagen PNG, JPG o WebP.');
        }

        $previous = $user->getAvatarPath();
        $user->setAvatar($this->avatars->store($file), new DateTimeImmutable());
        $this->entityManager->flush();

        if ($previous !== null) {
            $this->avatars->delete($previous);
        }
    }

    public function remove(User $user): void
    {
        $previous = $user->getAvatarPath();
        if ($previous === null) {
            return;
        }
        $user->setAvatar(null);
        $this->entityManager->flush();
        $this->avatars->delete($previous);
    }

    /** @return array{content: string, mime: string}|null */
    public function read(User $user): ?array
    {
        $path = $user->getAvatarPath();
        if ($path === null) {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = array_search($extension === 'jpeg' ? 'jpg' : $extension, self::TYPES, true);

        return [
            'content' => $this->avatars->read($path),
            'mime' => is_string($mime) ? $mime : 'application/octet-stream',
        ];
    }
}
