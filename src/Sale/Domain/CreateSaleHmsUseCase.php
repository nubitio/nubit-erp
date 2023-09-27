<?php

namespace App\Sale\Domain;

use App\Consultation\Domain\Consultation;
use App\Consultation\Domain\CreateConsultationUseCase;
use App\ConsultationSale\Domain\ConsultationSale;
use App\ConsultationSale\Domain\CreateConsultationSaleUseCase;
use App\Patient\Domain\GetOnePatientByCustomerIdUseCase;
use App\Sale\Api\SaleHmsDto;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

readonly class CreateSaleHmsUseCase
{
    public function __construct(
        private SaleRepositoryInterface $repository,
        private EventDispatcherInterface $eventDispatcher,
        private GetOnePatientByCustomerIdUseCase $getOnePatientByCustomerIdUseCase,
        private CreateConsultationUseCase $createConsultationUseCase,
        private CreateConsultationSaleUseCase $createConsultationSaleUseCase,
    ) {
    }

    public function __invoke(SaleHmsDto $sale): Sale
    {
        $patient = $this->getOnePatientByCustomerIdUseCase->__invoke(strval($sale->cliente?->ruc));

        $consultation = new Consultation(
            patient: $patient,
            physician: $sale->physician,
            date: $sale->fecha,
            reason: $sale->glosa,
        );

        $createdConsultation = $this->createConsultationUseCase->__invoke($consultation);

        $createdSale = $this->repository->insert($sale);

        $consultationSale = new ConsultationSale(
            consultation: $createdConsultation,
            sale: $createdSale,
        );

        $this->createConsultationSaleUseCase->__invoke($consultationSale);

        $saleCreatedEvent = new SaleCreatedEvent($createdSale);

        $this->eventDispatcher->dispatch($saleCreatedEvent, SaleCreatedEvent::NAME);

        return $createdSale;
    }
}
