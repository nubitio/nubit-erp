<?php

declare(strict_types=1);

namespace App\AI\Service;

use ApiPlatform\Metadata\Post;
use App\AI\Entity\AssistantProposedAction;
use App\Billing\Entity\TaxDocument;
use App\Billing\Entity\TaxDocumentLine;
use App\Billing\State\TaxDocumentProcessor;
use App\Catalog\Entity\Category;
use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Entity\Product;
use App\Catalog\Entity\UnitOfMeasure;
use App\Catalog\Repository\ProductRepository;
use App\Contacts\Entity\Customer;
use App\Contacts\Repository\CustomerRepository;
use App\Identity\Entity\User;
use App\Inventory\Entity\Warehouse;
use App\Sales\Entity\SaleRecord;
use App\Sales\Port\RejectedDocumentReplacerInterface;
use App\Sales\State\SaleProcessor;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Exception\ServiceException;
use Nubit\Platform\Exception\ValidationException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;

final readonly class ConfirmProposedActionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private CustomerRepository $customerRepository,
        private ProductRepository $productRepository,
        private SaleProcessor $saleProcessor,
        private RejectedDocumentReplacerInterface $rejectedDocumentReplacer,
        private TaxDocumentProcessor $taxDocumentProcessor,
    ) {}

    /**
     * @return array{id: string, status: string, kind: string, resultRef: string|null, summary: string}
     */
    public function confirm(string $actionId): array
    {
        $action = $this->loadOwned($actionId);

        if ($action->getStatus() === AssistantProposedAction::CONFIRMED) {
            return $this->toResult($action);
        }

        if (!$action->isPending()) {
            throw ServiceException::create('Esta propuesta ya no se puede confirmar.', Response::HTTP_CONFLICT);
        }

        try {
            $resultRef = match ($action->getKind()) {
                AssistantProposedAction::KIND_CREATE_CUSTOMER => $this->createCustomer($action->getPayload()),
                AssistantProposedAction::KIND_CREATE_PRODUCT => $this->createProduct($action->getPayload()),
                AssistantProposedAction::KIND_CREATE_SALE => $this->createSale($action->getPayload()),
                AssistantProposedAction::KIND_REPLACE_REJECTED => $this->replaceRejected($action->getPayload()),
                AssistantProposedAction::KIND_CREATE_CREDIT_NOTE => $this->createCreditNote($action->getPayload()),
                AssistantProposedAction::KIND_FILL_ONBOARDING => 'applied',
                default => throw ServiceException::create('Este tipo de acción aún no se puede confirmar.'),
            };
        } catch (ValidationException $exception) {
            $action->setStatus(AssistantProposedAction::FAILED);
            $action->setErrorMessage($exception->getMessage());
            $this->entityManager->flush();
            throw ServiceException::create($exception->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (ServiceException $exception) {
            $action->setStatus(AssistantProposedAction::FAILED);
            $action->setErrorMessage($exception->getMessage());
            $this->entityManager->flush();
            throw $exception;
        }

        $action->setStatus(AssistantProposedAction::CONFIRMED);
        $action->setResultRef($resultRef);
        $this->entityManager->flush();

        return $this->toResult($action);
    }

    /**
     * @return array{id: string, status: string, kind: string, resultRef: string|null, summary: string}
     */
    public function cancel(string $actionId): array
    {
        $action = $this->loadOwned($actionId);

        if ($action->getStatus() === AssistantProposedAction::CONFIRMED) {
            throw ServiceException::create('Esta propuesta ya fue confirmada.', Response::HTTP_CONFLICT);
        }

        $action->setStatus(AssistantProposedAction::CANCELLED);
        $this->entityManager->flush();

        return $this->toResult($action);
    }

    private function loadOwned(string $actionId): AssistantProposedAction
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || $user->getId() === null) {
            throw ServiceException::create('Se requiere un usuario autenticado.', Response::HTTP_FORBIDDEN);
        }

        $action = $this->entityManager->find(AssistantProposedAction::class, $actionId);
        if (!$action instanceof AssistantProposedAction) {
            throw ServiceException::create('No se encontró la propuesta.', Response::HTTP_NOT_FOUND);
        }

        if ($action->getUserId() !== $user->getId()) {
            throw ServiceException::create(
                'No puedes confirmar una propuesta de otro usuario.',
                Response::HTTP_FORBIDDEN,
            );
        }

        if ($action->isExpired() && $action->getStatus() === AssistantProposedAction::PENDING) {
            $action->setStatus(AssistantProposedAction::EXPIRED);
            $this->entityManager->flush();
        }

        return $action;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createCustomer(array $payload): string
    {
        $ruc = trim((string) ($payload['ruc'] ?? ''));
        $name = trim((string) ($payload['businessName'] ?? ''));
        if ($ruc === '' || $name === '') {
            throw ServiceException::create('Faltan RUC o razón social.');
        }

        if ($this->customerRepository->findOneBy(['ruc' => $ruc]) !== null) {
            throw ServiceException::create(sprintf('Ya existe un cliente con el documento %s.', $ruc));
        }

        $customer = new Customer();
        $customer->setRuc($ruc);
        $customer->setBusinessName($name);
        if (isset($payload['fiscalAddress']) && is_string($payload['fiscalAddress'])) {
            $customer->setFiscalAddress($payload['fiscalAddress']);
        }
        if (isset($payload['phone']) && is_string($payload['phone'])) {
            $customer->setPhone($payload['phone']);
        }
        if (isset($payload['email']) && is_string($payload['email'])) {
            $customer->setEmail($payload['email']);
        }

        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        return (string) $customer->getId();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createProduct(array $payload): string
    {
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            throw ServiceException::create('Falta el nombre del producto.');
        }

        if ($this->productRepository->findOneBy(['name' => $name]) !== null) {
            throw ServiceException::create(sprintf('Ya existe un producto llamado "%s".', $name));
        }

        $category = $this->entityManager->getRepository(Category::class)->findOneBy([]);
        $unit = $this->entityManager->getRepository(UnitOfMeasure::class)->findOneBy([]);
        if (!$category instanceof Category || !$unit instanceof UnitOfMeasure) {
            throw ServiceException::create('No hay categoría o unidad de medida para crear el producto.');
        }

        $product = new Product();
        $product->setName($name);
        $product->setCategory($category);
        $product->setUnitOfMeasure($unit);
        if (isset($payload['code']) && is_string($payload['code']) && $payload['code'] !== '') {
            $product->setCode($payload['code']);
        }
        if (isset($payload['salePrice']) && is_numeric($payload['salePrice'])) {
            $product->setSalePrice((string) $payload['salePrice']);
        }

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return (string) $product->getId();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createSale(array $payload): string
    {
        $warehouseId = (string) ($payload['warehouseId'] ?? '');
        $warehouse = $this->entityManager->find(Warehouse::class, $warehouseId);
        if (!$warehouse instanceof Warehouse) {
            throw ServiceException::create('Almacén no encontrado.');
        }

        $sale = new SaleRecord();
        $sale->setTipoId(2);
        $sale->setWarehouse($warehouse);
        $sale->setBranch($warehouse->getBranch());
        $sale->setEmitInvoice((bool) ($payload['emitInvoice'] ?? true));
        $nowLima = new DateTimeImmutable('now', new DateTimeZone('America/Lima'));
        $sale->setIssuedAt(new \DateTime('now', new DateTimeZone('America/Lima')));
        $sale->setIssuedDate(new DateTimeImmutable($nowLima->format('Y-m-d')));

        $method = $this->entityManager->find(
            PaymentMethod::class,
            (int) ($payload['paymentMethodId'] ?? PaymentMethod::CASH),
        );
        if ($method instanceof PaymentMethod) {
            $sale->setPaymentMethod($method);
            $sale->setTipoPago($method);
        }

        if (isset($payload['customerId']) && $payload['customerId'] !== null) {
            $customer = $this->customerRepository->find($payload['customerId']);
            if ($customer instanceof Customer) {
                $sale->setCustomer($customer);
            }
        }

        $writeItems = [];
        $rawItems = is_array($payload['items'] ?? null) ? $payload['items'] : [];
        foreach ($rawItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $writeItems[] = [
                'product' => $item['productId'] ?? null,
                'qty' => $item['qty'] ?? 1,
                'unitPrice' => $item['unitPrice'] ?? '0.00',
                'description' => $item['name'] ?? null,
            ];
        }
        if ($writeItems === []) {
            throw ServiceException::create('La propuesta no tiene productos.');
        }
        $sale->setWriteItems($writeItems);

        $result = $this->saleProcessor->process($sale, new Post(), [], []);
        if (!$result instanceof SaleRecord || $result->getId() === null) {
            throw ServiceException::create('La venta no se pudo registrar.');
        }

        return (string) $result->getId();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function replaceRejected(array $payload): string
    {
        $saleId = trim((string) ($payload['saleId'] ?? ''));
        if ($saleId === '') {
            throw ServiceException::create('La propuesta no tiene una venta asociada.');
        }

        $result = $this->rejectedDocumentReplacer->replaceRejectedDocument($saleId);
        if (!$result->success) {
            throw ServiceException::create($result->message);
        }

        return $result->newId ?? $saleId;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createCreditNote(array $payload): string
    {
        $taxDocumentId = trim((string) ($payload['taxDocumentId'] ?? ''));
        $reasonCode = trim((string) ($payload['reasonCode'] ?? ''));
        if ($taxDocumentId === '' || $reasonCode === '') {
            throw ServiceException::create('La propuesta de nota de crédito está incompleta.');
        }

        $related = $this->entityManager->find(TaxDocument::class, $taxDocumentId);
        if (!$related instanceof TaxDocument) {
            throw ServiceException::create('No se encontró el comprobante afectado.');
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('America/Lima'));
        $note = new TaxDocument();
        $note->setRelatedTaxDocument($related);
        $note->setReasonCode($reasonCode);
        $note->setDocumentType('07');
        $note->setIssueDate($now);
        $note->setCreatedAt(new \DateTime('now', new DateTimeZone('America/Lima')));
        $note->setUpdatedAt(new \DateTime('now', new DateTimeZone('America/Lima')));

        $rawLines = is_array($payload['lines'] ?? null) ? $payload['lines'] : [];
        foreach ($rawLines as $item) {
            if (!is_array($item)) {
                continue;
            }
            $line = new TaxDocumentLine();
            $line->setLineNumber((int) ($item['lineNumber'] ?? 0));
            $line->setQuantity((string) ($item['qty'] ?? '0'));
            $line->setProductName('item');
            $note->addLine($line);
        }

        if ($note->getLines()->isEmpty()) {
            throw ServiceException::create('La propuesta no tiene ítems para la nota de crédito.');
        }

        $result = $this->taxDocumentProcessor->process($note, new Post(), [], []);
        if (!$result instanceof TaxDocument || $result->getId() === null) {
            throw ServiceException::create('La nota de crédito no se pudo registrar.');
        }

        return (string) $result->getId();
    }

    /**
     * @return array{id: string, status: string, kind: string, resultRef: string|null, summary: string}
     */
    private function toResult(AssistantProposedAction $action): array
    {
        return [
            'id' => (string) $action->getId(),
            'status' => $action->getStatus(),
            'kind' => $action->getKind(),
            'resultRef' => $action->getResultRef(),
            'summary' => $action->getSummary(),
        ];
    }
}
