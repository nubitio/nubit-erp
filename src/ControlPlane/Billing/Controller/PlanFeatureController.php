<?php

declare(strict_types=1);

namespace App\ControlPlane\Billing\Controller;

use App\ControlPlane\Billing\Feature\PlanFeatureManager;
use App\ControlPlane\Billing\Repository\PlanFeatureRepositoryInterface;
use App\ControlPlane\Billing\Service\PlanManager;
use App\Core\Audit\AuditLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin UI for the plan_features matrix.
 *
 * Routes:
 *   GET  /admin/plan-features                          → matrix of all plans × all features
 *   POST /admin/plan-features/{planName}/{featureKey}  → update a single cell
 *
 * The matrix is a table where:
 *   - Rows  = feature keys (from PlanFeatureManager::FEATURE_KEYS)
 *   - Columns = plan names (loaded dynamically from DB)
 *   - Each cell = an independent <form> (POST on change, no JS required)
 *
 * Config features (api_rate_limit, retention_days, concurrent_sessions) show an
 * additional <textarea> for the JSON config payload in each cell.
 */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/plan-features')]
final class PlanFeatureController extends AbstractController
{
    public function __construct(
        private readonly PlanManager $planManager,
        private readonly PlanFeatureManager $planFeatureManager,
        private readonly PlanFeatureRepositoryInterface $planFeatureRepository,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    // -------------------------------------------------------------------------
    // Matrix view
    // -------------------------------------------------------------------------

    #[Route('', name: 'admin_plan_features_index', methods: ['GET'])]
    public function index(): Response
    {
        $plans = $this->planManager->getPlans();

        // Build a lookup: featureKey → planName → PlanFeature
        $matrix = [];
        foreach (PlanFeatureManager::FEATURE_KEYS as $featureKey) {
            $matrix[$featureKey] = [];
            foreach ($plans as $plan) {
                $matrix[$featureKey][$plan['name']] = $this->planFeatureRepository
                    ->findOneByPlanAndKey($plan['name'], $featureKey);
            }
        }

        return $this->render('admin/plan-features/index.html.twig', [
            'plans'               => $plans,
            'feature_keys'        => PlanFeatureManager::FEATURE_KEYS,
            'config_feature_keys' => PlanFeatureManager::CONFIG_FEATURE_KEYS,
            'matrix'              => $matrix,
        ]);
    }

    // -------------------------------------------------------------------------
    // Update a single cell
    // -------------------------------------------------------------------------

    #[Route(
        '/{planName}/{featureKey}',
        name: 'admin_plan_features_update',
        requirements: ['planName' => '[a-z0-9_-]+', 'featureKey' => '[a-z_]+'],
        methods: ['POST'],
    )]
    public function update(string $planName, string $featureKey, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin', $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return $this->redirectToRoute('admin_plan_features_index');
        }

        if (!$this->planManager->planExists($planName)) {
            $this->addFlash('error', sprintf('Plan "%s" not found.', $planName));

            return $this->redirectToRoute('admin_plan_features_index');
        }

        $isEnabled  = (bool) $request->request->get('is_enabled', false);
        $configJson = trim((string) $request->request->get('config_json', ''));

        try {
            $config = $this->parseConfigJson($configJson !== '' ? $configJson : null);

            $this->planFeatureManager->setFeature($planName, $featureKey, $isEnabled, $config);

            $this->auditLogger->log('plan.feature.update', [
                'plan_name'   => $planName,
                'feature_key' => $featureKey,
                'is_enabled'  => $isEnabled,
                'config'      => $config,
            ]);

            $this->addFlash('success', sprintf(
                'Feature "%s" updated for plan "%s".',
                $featureKey,
                $planName,
            ));
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\JsonException $e) {
            $this->addFlash('error', 'Config JSON is invalid: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_plan_features_index');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Parses a raw JSON string into an associative array, or returns null.
     *
     * @return array<string, mixed>|null
     *
     * @throws \JsonException on invalid JSON
     */
    private function parseConfigJson(?string $raw): ?array
    {
        if ($raw === null) {
            return null;
        }

        $trimmed = trim($raw);
        if ($trimmed === '' || $trimmed === 'null') {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
