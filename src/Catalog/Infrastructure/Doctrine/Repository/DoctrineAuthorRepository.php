<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine\Repository;

use App\Catalog\Domain\Entity\Author;
use App\Catalog\Domain\Repository\AuthorRepositoryInterface;
use App\Catalog\Domain\ValueObject\AuthorId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

/**
 * Adapter: Implementacja repozytorium autorów z Doctrine.
 */
final class DoctrineAuthorRepository implements AuthorRepositoryInterface
{
    /** @var EntityRepository<Author> */
    private EntityRepository $repository;

    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        $this->repository = $this->entityManager->getRepository(Author::class);
    }

    public function save(Author $author): void
    {
        $this->entityManager->persist($author);
        $this->entityManager->flush();
    }

    public function findById(AuthorId $id): ?Author
    {
        return $this->repository->find($id->value());
    }

    public function searchByName(string $query): array
    {
        return $this->repository->createQueryBuilder('a')
            ->where('LOWER(a.firstName) LIKE LOWER(:query)')
            ->orWhere('LOWER(a.lastName) LIKE LOWER(:query)')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('a.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findMostProlific(int $limit = 10): array
    {
        return $this->repository->createQueryBuilder('a')
            ->leftJoin('a.books', 'b')
            ->groupBy('a.id')
            ->orderBy('COUNT(b.id)', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
