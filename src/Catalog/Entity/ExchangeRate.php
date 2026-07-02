<?php

declare(strict_types=1);

namespace App\Catalog\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Catalog\Enum\ExchangeRateSource;
use App\Catalog\Repository\ExchangeRateRepository;
use App\Catalog\State\ExchangeRateProvider;
use App\Catalog\State\ExchangeRatePutProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Current exchange rate for a currency pair (e.g. USD → PEN).
 *
 * One row per currency. Updated either manually via PUT or automatically
 * by ExchangeRateSyncService (BCRP sync).
 */
#[ORM\Entity(repositoryClass: ExchangeRateRepository::class)]
#[ORM\Table(name: 'exchange_rates')]
#[ORM\UniqueConstraint(name: 'uq_currency', columns: ['currency'])]
#[ApiResource(
    operations: [
        new Get(uriTemplate: '/exchange-rate/{currency}', provider: ExchangeRateProvider::class),
        new Put(
            uriTemplate: '/exchange-rate/{currency}',
            provider: ExchangeRateProvider::class,
            processor: ExchangeRatePutProcessor::class,
        ),
        new Post(
            uriTemplate: '/exchange-rate/{currency}/sync',
            provider: ExchangeRateProvider::class,
            processor: ExchangeRatePutProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => ['exchange_rate:read']],
    denormalizationContext: ['groups' => ['exchange_rate:write']],
)]
class ExchangeRate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 6)]
    #[Assert\Positive]
    private string $rate;

    #[ORM\Column(enumType: ExchangeRateSource::class)]
    private ExchangeRateSource $source;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $currency, string $rate, ExchangeRateSource $source)
    {
        $this->currency = strtoupper($currency);
        $this->rate = $rate;
        $this->source = $source;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getRate(): string
    {
        return $this->rate;
    }

    public function getSource(): ExchangeRateSource
    {
        return $this->source;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function update(string $rate, ExchangeRateSource $source): void
    {
        $this->rate = $rate;
        $this->source = $source;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
