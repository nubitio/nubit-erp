<?php

namespace App\MilitaryPatient\Infrastructure;

use App\ConsultationSale\Infrastructure\ConsultationSaleEntity;
use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Customer\Infrastructure\CustomerEntity;
use App\MilitaryPatient\Domain\MilitaryPatient;
use App\MilitaryPatient\Domain\MilitaryPatientRepositoryInterface;
use App\Patient\Domain\Patient;
use App\Patient\Infrastructure\PatientEntity;
use App\PatientType\Domain\PatientType;
use App\PatientType\Infrastructure\PatientTypeEntity;
use App\PriceList\Domain\PriceList;
use App\PriceList\Infrastructure\PriceListEntity;
use App\Sale\Domain\InvoiceExtraData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;

readonly class MilitaryPatientRepository implements MilitaryPatientRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader $dataLoader,
    ) {
    }

    public function getAll(Options $options): PaginatorInterface
    {
        return $this->dataLoader->loadCollection($options);
    }

    public function getOne(int $id): ?MilitaryPatient
    {
        $entity = $this->entityManager->find(MilitaryPatientEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, MilitaryPatient::class);
    }

    public function insert(MilitaryPatient $militaryPatient): MilitaryPatient
    {
        $customer = $this->entityManager->find(CustomerEntity::class, $militaryPatient->ruc);
        $patient = $this->entityManager->find(PatientEntity::class, $militaryPatient->patient?->id ?? 0);

        if (!$customer) {
            $customer = DataMapper::map($militaryPatient, CustomerEntity::class);
        } else {
            $customer = DataMapper::map($militaryPatient, $customer);
        }

        if (!$patient) {
            $patient = DataMapper::map($militaryPatient, PatientEntity::class);
        } else {
            $patient = DataMapper::map($militaryPatient, $patient);
        }

        $customer->price_list = $this->entityManager->getReference(PriceListEntity::class, $militaryPatient->price_list?->id);
        $patient->customer = $customer;
        $patient->type = $this->entityManager->getReference(PatientTypeEntity::class, $militaryPatient->type?->id);
        $entity = DataMapper::map($militaryPatient, MilitaryPatientEntity::class);
        $entity->patient = $patient;
        $this->entityManager->persist($customer);
        $this->entityManager->persist($patient);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, MilitaryPatient::class);
    }

    public function update(MilitaryPatient $militaryPatient): MilitaryPatient
    {
        $customer = $this->entityManager->find(CustomerEntity::class, $militaryPatient->ruc);
        $patient = $this->entityManager->find(PatientEntity::class, $militaryPatient->patient?->id ?? 0);
        $entity = $this->entityManager->find(MilitaryPatientEntity::class, $militaryPatient->id);

        if (!$entity || !$customer || !$patient) {
            throw new ServiceException('Entity not found');
        }

        /** @var CustomerEntity $customer */
        $customer = DataMapper::map($militaryPatient, $customer);
        $customer->price_list = $this->entityManager->getReference(PriceListEntity::class, $militaryPatient->price_list?->id);
        /** @var PatientEntity $patient */
        $patient = DataMapper::map($militaryPatient, $patient, ['id']);
        $patient->customer = $customer;
        $patient->type = $this->entityManager->getReference(PatientTypeEntity::class, $militaryPatient->type?->id);
        $entity = DataMapper::map($militaryPatient, $entity);
        $entity->patient = $patient;
        $this->entityManager->flush();

        return DataMapper::map($entity, MilitaryPatient::class);
    }

    public function delete(int $id): void
    {
        $entity = $this->entityManager->find(MilitaryPatientEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    /**
     * @throws NonUniqueResultException
     */
    public function getInvoiceExtraDataByCustomerId(string $customerId, string $saleId): ?InvoiceExtraData
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(MilitaryPatientEntity::class, 'm')
            ->join('m.patient', 'p')
            ->join('p.customer', 'c')
            ->where('c.ruc = :id')
            ->setParameter('id', $customerId)
            ->getQuery();

        /** @var ?MilitaryPatientEntity $militaryPatient */
        $militaryPatient = $query->getOneOrNullResult();

        /** @var ?ConsultationSaleEntity $consultationSale */
        $consultationSale = $this->entityManager->getRepository(ConsultationSaleEntity::class)->findOneBy(['sale' => $saleId]);

        return new InvoiceExtraData(
            cipCode: $militaryPatient?->cip_code,
            rank: $militaryPatient?->rank,
            weaponCode: $militaryPatient?->weapon_code,
            medicalRecordNumber: $militaryPatient?->patient?->medical_record_number,
            physicianFullName: $consultationSale?->consultation?->physician?->getFullName(),
            queue: $consultationSale?->consultation?->queue,
            physicianSpecialty: $consultationSale?->consultation?->physician?->specialty?->name
        );
    }

    public function findOneByPatientId(int $patientId): ?MilitaryPatient
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(MilitaryPatientEntity::class, 'm')
            ->join('m.patient', 'p')
            ->join('p.customer', 'c')
            ->where('p.id = :id')
            ->setParameter('id', $patientId)
            ->getQuery();

        /** @var ?MilitaryPatientEntity $militaryPatient */
        $militaryPatient = $query->getOneOrNullResult();

        if (!$militaryPatient) {
            return null;
        }

        $militaryPatient = DataMapper::map($militaryPatient, MilitaryPatient::class);

        $patient = $this->entityManager->find(PatientEntity::class, $patientId);
        $customer = $this->entityManager->find(CustomerEntity::class, $patient?->customer?->ruc ?? '');
        $priceList = $this->entityManager->find(PriceListEntity::class, $customer?->price_list?->id ?? 0);
        $patientType = $this->entityManager->find(PatientTypeEntity::class, $patient?->type?->id ?? 0);

        $data = new MilitaryPatient();
        $data->type = DataMapper::map($patientType, PatientType::class);
        $data->patient = DataMapper::map($patient, Patient::class);
        $data->price_list = DataMapper::map($priceList, PriceList::class);

        // customer properties
        $data->ruc = $customer?->ruc;
        $data->razon_social = $customer?->razon_social;
        $data->domicilio_fiscal = $customer?->domicilio_fiscal;
        $data->telefono = $customer?->telefono;
        $data->email = $customer?->email;
        $data->estado = $customer?->estado;
        // patient properties
        $data->medical_record_number = $patient?->medical_record_number;
        $data->insurance_provider = $patient?->insurance_provider;
        // military_patient properties
        $data->id = $militaryPatient->id;
        $data->cip_code = $militaryPatient->cip_code;
        $data->rank = $militaryPatient->rank;
        $data->weapon_code = $militaryPatient->weapon_code;

        return $data;
    }
}
