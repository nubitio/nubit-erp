<?php

declare(strict_types=1);

namespace App\Setup\Demo\Minimarket;

use App\Identity\Entity\Role;
use App\Identity\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use SensitiveParameter;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** The people at the till. They can sell and run the register, and cannot see the owner's oversight views. */
final readonly class MinimarketStaff
{
    /** The built-in "Cajero" role. */
    private const int CASHIER_ROLE_ID = 3;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {}

    /** @return array<string, User> keyed by the cashier key used in the story */
    public function cashiers(#[SensitiveParameter] string $password): array
    {
        $role = $this->entityManager->find(Role::class, self::CASHIER_ROLE_ID);
        if (!$role instanceof Role) {
            throw new RuntimeException('No existe el rol Cajero. Ejecute el setup inicial antes del seed.');
        }

        $users = [];
        foreach (MinimarketCatalog::cashiers() as $key => $cashier) {
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $cashier['username']]);
            if (!$user instanceof User) {
                $user = new User()->setUsername($cashier['username']);
                $this->entityManager->persist($user);
            }
            $user
                ->setRole($role)
                ->setDisplayName($cashier['name'])
                ->setPassword($this->passwordHasher->hashPassword($user, $password));
            $users[$key] = $user;
        }
        $this->entityManager->flush();

        return $users;
    }
}
