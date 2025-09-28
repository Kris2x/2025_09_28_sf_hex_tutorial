<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Book;
use App\Domain\ValueObject\BookId;

interface BookRepositoryInterface
{
    public function save(Book $book): void;

    public function findById(BookId $id): ?Book;

    public function findByIsbn(string $isbn): ?Book;

    /** @return Book[] */
    public function findAvailable(): array;

    /** @return Book[] */
    public function findAll(): array;

    public function remove(Book $book): void;
}