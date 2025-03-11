<?php

declare(strict_types=1);

namespace App\Validator;

use App\Core\Infrastructure\Entity\AuditableEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class UniqueEntityValidator extends ConstraintValidator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueEntity) {
            throw new UnexpectedTypeException($constraint, UniqueEntity::class);
        }

        $fields = $constraint->fields;

        $entityClass = get_class($value);

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('e')
            ->from($entityClass, 'e');

        $propertyAccessor = new PropertyAccessor();

        foreach ($fields as $field) {
            $qb->andWhere("e.$field = :$field")
                ->setParameter($field, $propertyAccessor->getValue($value, $field));
        }

        if (is_subclass_of($entityClass, AuditableEntity::class)) {
            $qb->andWhere('e.active = 1');
        }

        $qb->setMaxResults(1);

        $result = $qb->getQuery()->getOneOrNullResult();

        if ($result !== null) {
            $this->context->buildViolation($constraint->message)
                ->atPath($fields[0])
                ->addViolation();
        }
    }
}
