<?php

declare(strict_types=1);

namespace App\Tests\Identity\Profile;

use App\Identity\Profile\AvatarStorage;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class FakeAvatarStorage implements AvatarStorage
{
    /** @var array<string, string> */
    public array $files = [];

    /** @var list<string> */
    public array $deleted = [];

    private int $counter = 0;

    public function store(UploadedFile $file): string
    {
        $path = sprintf('a%d.%s', ++$this->counter, $file->guessExtension() ?? 'bin');
        $this->files[$path] = (string) file_get_contents($file->getPathname());

        return $path;
    }

    public function read(string $path): string
    {
        return $this->files[$path] ?? '';
    }

    public function delete(string $path): void
    {
        $this->deleted[] = $path;
        unset($this->files[$path]);
    }
}
