<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine\Repository;

use App\Catalog\Domain\Entity\CatalogBook;
use App\Catalog\Domain\Repository\CatalogBookRepositoryInterface;
use App\Catalog\Domain\ValueObject\CatalogBookId;
use App\Catalog\Domain\ValueObject\Isbn;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

/**
 * Adapter: Implementacja repozytorium książek katalogowych z Doctrine.
 */
final class DoctrineCatalogBookRepository implements CatalogBookRepositoryInterface
{
    /** @var EntityRepository<CatalogBook> */
    private EntityRepository $repository;

    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        $this->repository = $this->entityManager->getRepository(CatalogBook::class);
    }

    public function save(CatalogBook $book): void
    {
        $this->entityManager->persist($book);
        $this->entityManager->flush();
    }

    public function findById(CatalogBookId $id): ?CatalogBook
    {
        return $this->repository->find($id->value());
    }

    public function findByIsbn(Isbn $isbn): ?CatalogBook
    {
        return $this->repository->findOneBy(['isbn.value' => $isbn->value()]);
    }

    public function searchByTitle(string $query): array
    {
        return $this->repository->createQueryBuilder('b')
            ->where('LOWER(b.title) LIKE LOWER(:query)')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('b.popularity', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findMostPopular(int $limit = 10): array
    {
        return $this->repository->createQueryBuilder('b')
            ->orderBy('b.popularity', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByCategory(string $categorySlug): array
    {
        return $this->repository->createQueryBuilder('b')
            ->join('b.categories', 'c')
            ->where('c.slug = :slug')
            ->setParameter('slug', $categorySlug)
            ->orderBy('b.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByAuthorId(string $authorId): array
    {
        return $this->repository->createQueryBuilder('b')
            ->join('b.author', 'a')
            ->where('a.id = :authorId')
            ->setParameter('authorId', $authorId)
            ->orderBy('b.publishedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
