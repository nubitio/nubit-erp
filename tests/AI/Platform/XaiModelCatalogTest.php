<?php

declare(strict_types=1);

namespace App\Tests\AI\Platform;

use App\AI\Platform\XaiGptModelClient;
use App\AI\Platform\XaiModelCatalog;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;

final class XaiModelCatalogTest extends TestCase
{
    public function testResolvesGrokChatModels(): void
    {
        $catalog = new XaiModelCatalog();
        $model = $catalog->getModel('grok-4.3');

        self::assertInstanceOf(Gpt::class, $model);
        self::assertSame('grok-4.3', $model->getName());
    }

    public function testEmptyXaiKeyIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new XaiGptModelClient(new MockHttpClient(), '');
    }
}
