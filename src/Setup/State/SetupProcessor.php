<?php

declare(strict_types=1);

namespace App\Setup\State;

use Override;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Setup\InitialSetupInput;
use App\Dto\Setup\SetupStatusDto;
use App\Setup\InitialSetupService;
use Doctrine\DBAL\Exception;

/**
 * @implements ProcessorInterface<mixed, mixed>
 */
readonly class SetupProcessor implements ProcessorInterface
{
    public function __construct(private InitialSetupService $service)
    {
    }

    /**
     * @inheritdoc
     *
     * @param InitialSetupInput $data
     *
     * @throws Exception
     */
    #[Override]
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ): SetupStatusDto {
        return $this->service->configure($data);
    }
}
