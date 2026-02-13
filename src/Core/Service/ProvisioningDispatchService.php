<?php

declare(strict_types=1);

namespace App\Core\Service;

use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ProvisioningDispatchService
{
    public function __construct(
        private GithubProvisioningDispatcher $githubDispatcher,
        private LocalProvisioningDispatcher $localDispatcher,
        #[Autowire(env: 'APP_ENV')]
        private string $appEnv,
        #[Autowire(env: 'GITHUB_TARGET_CONTAINER')]
        private string $targetContainer,
    ) {
    }

    public function getTargetContainer(): string
    {
        return $this->targetContainer;
    }

    public function getTargetEnvironment(): string
    {
        return $this->appEnv;
    }

    /**
     * @param array<string, string> $inputs
     */
    public function dispatch(array $inputs): void
    {
        $targetEnv = $this->getTargetEnvironment();

        if (!array_key_exists('target_env', $inputs)) {
            $inputs['target_env'] = $targetEnv;
        }

        $dispatcher = match ($targetEnv) {
            'prod' => $this->githubDispatcher,
            default => $this->localDispatcher,
        };

        $dispatcher->dispatch($inputs);
    }
}
