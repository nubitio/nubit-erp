<?php

declare(strict_types=1);

namespace App\Pharmacy\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Pharmacy\Entity\Prescription;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProcessorInterface<PrescriptionInput, Prescription>
 */
final readonly class PrescriptionInputProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * @param PrescriptionInput $data
     */
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): Prescription {
        $prescription = new Prescription(
            patientName: $data->patientName,
            prescriberName: $data->prescriberName,
            issuedOn: new DateTimeImmutable($data->issuedOn),
            productIds: array_map(intval(...), $data->productIds),
        );
        $prescription
            ->setPatientDocument($data->patientDocument)
            ->setPrescriberRegistration($data->prescriberRegistration)
            ->setNotes($data->notes);

        if ($data->saleId !== null && $data->saleId !== '') {
            $prescription->linkToSale($data->saleId);
        }

        $this->entityManager->persist($prescription);
        $this->entityManager->flush();

        return $prescription;
    }
}
