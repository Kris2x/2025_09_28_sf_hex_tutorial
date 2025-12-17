<?php

declare(strict_types=1);

namespace App\Lending\Infrastructure\Doctrine\Repository;

use App\Lending\Domain\Entity\Loan;
use App\Lending\Domain\Repository\LoanRepositoryInterface;
use App\Lending\Domain\ValueObject\UserId;
use App\Lending\Domain\ValueObject\BookId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final class DoctrineLoanRepository implements LoanRepositoryInterface
{
    private EntityRepository $repository;

    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        $this->repository = $this->entityManager->getRepository(Loan::class);
    }

    public function save(Loan $loan): void
    {
        $this->entityManager->persist($loan);
        $this->entityManager->flush();
    }

    public function findById(string $id): ?Loan
    {
        return $this->repository->find($id);
    }

    public function findActiveByUserId(UserId $userId): array
    {
        return $this->repository->findBy([
            'userId' => $userId->value(),
            'returnedAt' => null
        ]);
    }

    public function findByBookId(BookId $bookId): array
    {
        return $this->repository->findBy([
            'bookId' => $bookId->value()
        ]);
    }

    public function findOverdue(): array
    {
        $qb = $this->repository->createQueryBuilder('l');

        return $qb
            ->where('l.returnedAt IS NULL')
            ->andWhere('l.borrowedAt < :overdueDate')
            ->setParameter('overdueDate', new \DateTimeImmutable('-14 days'))
            ->getQuery()
            ->getResult();
    }

    public function findAll(): array
    {
        return $this->repository->findAll();
    }
}
