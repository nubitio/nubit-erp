<?php

declare(strict_types=1);

namespace App\Settings\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use App\Settings\Repository\SunatOperationalStateRepository;
use App\Settings\State\SunatStateProvider;
use App\Settings\State\SunatStatePatchProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Operational state of the SUNAT integration for this tenant.
 *
 * - sendEnabled / receiptRequired are toggles the admin can change.
 * - rejectionStreak is written automatically by SunatSendControlService.
 * - rejectionThreshold is the limit after which sending is auto-paused.
 *
 * Retry timing parameters (maxAttempts, baseSeconds, maxSeconds) live in
 * config/packages/sunat.yaml — they are the same for all tenants.
 */
#[ORM\Entity(repositoryClass: SunatOperationalStateRepository::class)]
#[ORM\Table(name: 'sunat_operational_state')]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/tenant/sunat-state',
            provider: SunatStateProvider::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Patch(
            uriTemplate: '/tenant/sunat-state',
            provider: SunatStateProvider::class,
            processor: SunatStatePatchProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
            inputFormats: ['json' => ['application/merge-patch+json', 'application/json']],
        ),
    ],
    normalizationContext: ['groups' => ['sunat_state:read']],
    denormalizationContext: ['groups' => ['sunat_state:write']],
)]
class SunatOperationalState
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Whether automatic submission to SUNAT is active. */
    #[ORM\Column]
    #[Groups(['sunat_state:read', 'sunat_state:write'])]
    private bool $sendEnabled = true;

    /** Whether a physical receipt must be issued for every sale. */
    #[ORM\Column]
    #[Groups(['sunat_state:read', 'sunat_state:write'])]
    private bool $receiptRequired = false;

    /** Number of consecutive SUNAT rejections since last reset. Written by the system. */
    #[ORM\Column]
    #[Groups(['sunat_state:read'])]
    private int $rejectionStreak = 0;

    /** Auto-pause threshold: sendEnabled is set to false when streak reaches this value. */
    #[ORM\Column]
    #[Assert\Range(min: 1, max: 100)]
    #[Groups(['sunat_state:read', 'sunat_state:write'])]
    private int $rejectionThreshold = 3;

    // ── Getters / Setters ─────────────────────────────────────────────────────

    public function getId(): ?int { return $this->id; }

    public function isSendEnabled(): bool { return $this->sendEnabled; }
    public function setSendEnabled(bool $sendEnabled): static
    {
        $this->sendEnabled = $sendEnabled;
        return $this;
    }

    public function isReceiptRequired(): bool { return $this->receiptRequired; }
    public function setReceiptRequired(bool $receiptRequired): static
    {
        $this->receiptRequired = $receiptRequired;
        return $this;
    }

    public function getRejectionStreak(): int { return $this->rejectionStreak; }
    public function setRejectionStreak(int $rejectionStreak): static
    {
        $this->rejectionStreak = max(0, $rejectionStreak);
        return $this;
    }

    public function getRejectionThreshold(): int { return $this->rejectionThreshold; }
    public function setRejectionThreshold(int $rejectionThreshold): static
    {
        $this->rejectionThreshold = max(1, $rejectionThreshold);
        return $this;
    }

    public function incrementStreak(): int
    {
        $this->rejectionStreak++;
        return $this->rejectionStreak;
    }

    public function resetStreak(): void
    {
        $this->rejectionStreak = 0;
    }

    public function isStreakAtThreshold(): bool
    {
        return $this->rejectionStreak >= $this->rejectionThreshold;
    }
}
