<?php

declare(strict_types=1);

namespace App\Core\Service;

use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class GitHubWorkflowDispatcher
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%env(default::GITHUB_TOKEN)%')]
        private string $token,
        #[Autowire('%env(default::GITHUB_OWNER)%')]
        private string $owner,
        #[Autowire('%env(default::GITHUB_REPO)%')]
        private string $repo,
        #[Autowire('%env(default:main:GITHUB_WORKFLOW_REF)%')]
        private string $ref,
        #[Autowire('%env(default:tenant-provision.yml:GITHUB_WORKFLOW_FILE)%')]
        private string $workflowFile,
    ) {
    }

    /**
     * @param array<string, string> $inputs
     *
     * @throws TransportExceptionInterface
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
            $this->workflowFile
        );

        $response = $this->httpClient->request('POST', $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
                'Accept' => 'application/vnd.github+json',
            ],
            'json' => [
                'ref' => $this->ref,
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
