<?php

declare(strict_types=1);

namespace App\Billing\Recurring\State;

use ApiPlatform\State\ProcessorInterface;
use App\Billing\Recurring\Entity\CustomerSubscription;
use Nubit\AdminBundle\State\AbstractEmbeddedLinesProcessor;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** @extends AbstractEmbeddedLinesProcessor<CustomerSubscription, \App\Billing\Recurring\Entity\CustomerSubscriptionLine> */
final readonly class CustomerSubscriptionWriteProcessor extends AbstractEmbeddedLinesProcessor
{
    /** @param ProcessorInterface<CustomerSubscription, CustomerSubscription> $persistProcessor */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        ProcessorInterface $persistProcessor,
    ) {
        parent::__construct($persistProcessor);
    }

    protected function supports(mixed $data): bool
    {
        return $data instanceof CustomerSubscription;
    }

    protected function linesProperty(): string
    {
        return 'lines';
    }

    protected function lineSetter(): string
    {
        return 'setSubscription';
    }
}
