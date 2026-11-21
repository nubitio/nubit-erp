<?php

declare(strict_types=1);

namespace App\Tests\Identity\Profile;

use App\Identity\Profile\AvatarService;
use App\Identity\Profile\ProfileView;
use App\Identity\Profile\ProfileViolations;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[AllowMockObjectsWithoutExpectations]
final class AvatarServiceTest extends TestCase
{
    private FakeAvatarStorage $storage;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $this->storage = new FakeAvatarStorage();
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function testAvatarIsStoredAndTheOldOneRemoved(): void
    {
        $user = ProfileUsers::user();
        $service = $this->service();

        $service->set($user, $this->upload(ProfileUsers::png(), 'one.png', 'image/png'));
        $first = (string) $user->getAvatarPath();
        $service->set($user, $this->upload(ProfileUsers::png(), 'two.png', 'image/png'));

        self::assertNotSame($first, $user->getAvatarPath());
        self::assertSame([$first], $this->storage->deleted);
        self::assertCount(1, $this->storage->files);
    }

    public function testNonImagesAreRejectedWhateverTheyClaimToBe(): void
    {
        $this->expectException(ProfileViolations::class);

        $this->service()->set(ProfileUsers::user(), $this->upload('<?php echo 1;', 'shell.png', 'image/png'));
    }

    public function testSvgIsRejected(): void
    {
        $this->expectException(ProfileViolations::class);

        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
        $this->service()->set(ProfileUsers::user(), $this->upload($svg, 'a.svg', 'image/svg+xml'));
    }

    public function testOversizedImagesAreRejected(): void
    {
        $this->expectException(ProfileViolations::class);

        $big = ProfileUsers::png() . str_repeat("\0", AvatarService::MAX_BYTES);
        $this->service()->set(ProfileUsers::user(), $this->upload($big, 'big.png', 'image/png'));
    }

    public function testRemovingTheAvatarDeletesTheStoredFile(): void
    {
        $user = ProfileUsers::user();
        $service = $this->service();
        $service->set($user, $this->upload(ProfileUsers::png(), 'one.png', 'image/png'));
        $path = (string) $user->getAvatarPath();

        $service->remove($user);

        self::assertNull($user->getAvatarPath());
        self::assertNull(ProfileView::avatarUrl($user));
        self::assertSame([$path], $this->storage->deleted);
    }

    public function testReadReturnsTheBytesWithTheirType(): void
    {
        $user = ProfileUsers::user();
        $service = $this->service();
        $service->set($user, $this->upload(ProfileUsers::png(), 'one.png', 'image/png'));

        $avatar = $service->read($user);

        self::assertSame('image/png', $avatar['mime'] ?? null);
        self::assertSame(ProfileUsers::png(), $avatar['content']);
        self::assertNull($service->read(ProfileUsers::user()));
    }

    private function service(): AvatarService
    {
        return new AvatarService($this->createMock(EntityManagerInterface::class), $this->storage);
    }

    private function upload(string $bytes, string $name, string $mime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'avatar');
        self::assertIsString($path);
        file_put_contents($path, $bytes);
        $this->tempFiles[] = $path;

        return new UploadedFile($path, $name, $mime, null, true);
    }
}
