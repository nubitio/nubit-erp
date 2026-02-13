<?php

declare(strict_types=1);

namespace App\Core\Service;

use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class GithubProvisioningDispatcher implements ProvisioningDispatcherInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire(env: 'GITHUB_TOKEN')]
        private string $token,
        #[Autowire(env: 'GITHUB_OWNER')]
        private string $owner,
        #[Autowire(env: 'GITHUB_REPO')]
        private string $repo,
        #[Autowire(env: 'GITHUB_WORKFLOW_REF')]
        private string $ref,
        #[Autowire(env: 'GITHUB_WORKFLOW_FILE')]
        private string $workflowFile,
    ) {
    }

    /**
     * @param array<string, string> $inputs
     */
    public function dispatch(array $inputs): void
    {
        if ($this->token === '' || $this->owner === '' || $this->repo === '') {
            throw new RuntimeException('GitHub workflow dispatch is not configured.');
        }

        $url = sprintf(
            'https://api.github.com/repos/%s/%s/actions/workflows/%s/dispatches',
            $this->owner,
            $this->repo,
            $this->workflowFile !== '' ? $this->workflowFile : 'tenant-provision.yml'
        );

        $response = $this->httpClient->request('POST', $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
                'Accept' => 'application/vnd.github+json',
            ],
            'json' => [
                'ref' => $this->ref !== '' ? $this->ref : 'main',
                'inputs' => $inputs,
            ],
        ]);

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            $body = $response->getContent(false);
            throw new RuntimeException('GitHub workflow dispatch failed: ' . $status . ' ' . $body);
        }
    }
}
