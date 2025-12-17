<?php

declare(strict_types=1);

namespace App\Lending\Infrastructure\Doctrine\Repository;

use App\Lending\Domain\Entity\User;
use App\Lending\Domain\Repository\UserRepositoryInterface;
use App\Lending\Domain\ValueObject\UserId;
use App\Lending\Domain\ValueObject\Email;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final class DoctrineUserRepository implements UserRepositoryInterface
{
    private EntityRepository $repository;

    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        $this->repository = $this->entityManager->getRepository(User::class);
    }

    public function save(User $user): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    public function findById(UserId $id): ?User
    {
        return $this->repository->find($id->value());
    }

    public function findByEmail(Email $email): ?User
    {
        return $this->repository->findOneBy(['email' => $email->value()]);
    }

    public function findAll(): array
    {
        return $this->repository->findAll();
    }

    public function remove(User $user): void
    {
        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }
}
