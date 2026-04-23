<?php

declare(strict_types=1);

namespace App\ControlPlane\Subscription\Controller;

use App\ControlPlane\Subscription\Payment\PaymentGatewayInterface;
use App\ControlPlane\Subscription\Payment\WebhookProcessor;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class WebhookController extends AbstractController
{
    /** @var array<string, PaymentGatewayInterface> */
    private readonly array $gateways;

    /**
     * @param iterable<PaymentGatewayInterface> $gatewayIterator
     */
    public function __construct(
        #[AutowireIterator('app.payment_gateway')]
        iterable $gatewayIterator,
        private readonly WebhookProcessor $webhookProcessor,
        private readonly LoggerInterface $logger,
    ) {
        $map = [];
        foreach ($gatewayIterator as $gateway) {
            $map[$gateway->getName()] = $gateway;
        }
        $this->gateways = $map;
    }

    #[Route('/webhook/paypal', name: 'onboarding_webhook_paypal', methods: ['POST'])]
    public function paypal(Request $request): Response
    {
        return $this->handleWebhook('paypal', $request);
    }

    #[Route('/webhook/mercadopago', name: 'onboarding_webhook_mercadopago', methods: ['POST'])]
    public function mercadopago(Request $request): Response
    {
        return $this->handleWebhook('mercadopago', $request);
    }

    private function handleWebhook(string $gatewayName, Request $request): Response
    {
        $gateway = $this->gateways[$gatewayName] ?? null;
        if ($gateway === null) {
            $this->logger->error('Webhook received for unconfigured gateway', ['gateway' => $gatewayName]);

            return new Response('', Response::HTTP_OK);
        }

        $event = $gateway->handleWebhook($request);
        if ($event === null) {
            return new Response('', Response::HTTP_OK);
        }

        try {
            $this->webhookProcessor->process($event);
        } catch (\Throwable $e) {
            $this->logger->error('Webhook processing failed', [
                'gateway' => $gatewayName,
                'event_type' => $event->eventType,
                'exception' => $e,
            ]);
        }

        return new Response('', Response::HTTP_OK);
    }
}
