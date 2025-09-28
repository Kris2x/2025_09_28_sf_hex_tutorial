<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Entity\Book;
use App\Domain\Repository\BookRepositoryInterface;
use App\Domain\ValueObject\BookId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final class DoctrineBookRepository implements BookRepositoryInterface
{
    private EntityRepository $repository;

    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        $this->repository = $this->entityManager->getRepository(Book::class);
    }

    public function save(Book $book): void
    {
        $this->entityManager->persist($book);
        $this->entityManager->flush();
    }

    public function findById(BookId $id): ?Book
    {
        return $this->repository->find($id->value());
    }

    public function findByIsbn(string $isbn): ?Book
    {
        return $this->repository->findOneBy(['isbn' => $isbn]);
    }

    public function findAvailable(): array
    {
        return $this->repository->findBy(['isAvailable' => true]);
    }

    public function findAll(): array
    {
        return $this->repository->findAll();
    }

    public function remove(Book $book): void
    {
        $this->entityManager->remove($book);
        $this->entityManager->flush();
    }
}