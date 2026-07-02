<?php

declare(strict_types=1);

namespace App\Efact\Validator;

use App\Efact\Domain\AuditableEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class UniqueEntityValidator extends ConstraintValidator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueEntity) {
            throw new UnexpectedTypeException($constraint, UniqueEntity::class);
        }

        $fields = $constraint->fields;

        $entityClass = $value::class;

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('e')->from($entityClass, 'e');

        $propertyAccessor = new PropertyAccessor();

        foreach ($fields as $field) {
            $qb->andWhere(sprintf('e.%s = :%s', $field, $field))->setParameter($field, $propertyAccessor->getValue(
                $value,
                $field,
            ));
        }

        if (is_subclass_of($entityClass, AuditableEntity::class)) {
            $qb->andWhere('e.active = 1');
        }

        // Exclude the current entity if it already has an ID (edit case)
        /** @var class-string $entityClass */
        $metadata = $this->entityManager->getClassMetadata($entityClass);
        $identifierValues = $metadata->getIdentifierValues($value);

        if ($identifierValues !== []) {
            $identifierField = $metadata->getSingleIdentifierFieldName();
            $identifierValue = reset($identifierValues);

            $qb->andWhere(sprintf('e.%s != :entityId', $identifierField))->setParameter('entityId', $identifierValue);
        }

        $qb->setMaxResults(1);

        $result = $qb->getQuery()->getOneOrNullResult();

        if ($result !== null) {
            $this->context->buildViolation($constraint->message)->atPath($fields[0])->addViolation();
        }
    }
}
