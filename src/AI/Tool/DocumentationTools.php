<?php

declare(strict_types=1);

namespace App\AI\Tool;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use App\AI\Knowledge\KnowledgeRetriever;
use App\AI\Knowledge\KnowledgeIndex;

#[AsTool(
    name: 'search_manual',
    description: 'Search the Cashier User Manual for specific topics, procedures, business rules, '
        . 'or troubleshooting help. Use keywords like "opening cash", "creating sale", '
        . '"credit note", "error messages", etc.',
    method: 'searchManual',
)]
final readonly class DocumentationTools
{
    public function __construct(
        private KnowledgeRetriever $retriever,
        private KnowledgeIndex $index,
    ) {
    }

    /**
     * Search the cashier manual for relevant information.
     *
     * @param string $query The search query or topic (e.g., "how to open cash register",
     *     "stock validation rules", "error: insufficient stock")
     * @return string Relevant section(s) from the manual
     */
    public function searchManual(string $query): string
    {
        $sections = $this->retriever->search($query);

        if (empty($sections)) {
            try {
                $this->index->build();
            } catch (\Throwable) {
                // ignore
            }
            return 'No se encontró información específica para tu consulta. '
                . 'Intenta ser más concreto o revisa el manual en docs/.';
        }

        return implode("\n\n---\n\n", array_column($sections, 'text'));
    }
}
