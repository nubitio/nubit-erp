<?php

declare(strict_types=1);

namespace App\Validator;

use App\Core\Infrastructure\Entity\AuditableEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class UniqueEntityValidator extends ConstraintValidator
{
    private readonly PropertyAccessor $propertyAccessor;

    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        $this->propertyAccessor = new PropertyAccessor();
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueEntity) {
            throw new UnexpectedTypeException($constraint, UniqueEntity::class);
        }

        if (!is_object($value)) {
            throw new UnexpectedValueException($value, 'object');
        }

        $fields = $constraint->fields;

        if (empty($fields)) {
            throw new \InvalidArgumentException('No fields specified for uniqueness check.');
        }

        $entityClass = get_class($value);

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('e')
            ->from($entityClass, 'e');

        foreach ($fields as $field) {
            if (str_contains($field, '.')) {
                throw new \InvalidArgumentException("Nested properties are not supported: '$field'");
            }

            if (!$this->propertyAccessor->isReadable($value, $field)) {
                throw new \InvalidArgumentException("Property '$field' is not readable on " . $entityClass);
            }

            $fieldValue = $this->propertyAccessor->getValue($value, $field);

            $qb->andWhere("e.$field = :$field")
                ->setParameter($field, $fieldValue);
        }

        // Exclude the current entity if it has an ID (editing case)
        if (method_exists($value, 'getId') && null !== $value->getId()) {
            $qb->andWhere('e.id != :id')
                ->setParameter('id', $value->getId());
        }

        // Apply active filter only for AuditableEntity
        if (is_subclass_of($entityClass, AuditableEntity::class)) {
            $qb->andWhere('e.active = 1');
        }

        $qb->setMaxResults(1);
        $result = $qb->getQuery()->getOneOrNullResult();

        if ($result !== null) {
            $violationBuilder = $this->context->buildViolation($constraint->message);

            foreach ($fields as $field) {
                $violationBuilder->atPath($field);
            }

            $violationBuilder->addViolation();
        }
    }
}
