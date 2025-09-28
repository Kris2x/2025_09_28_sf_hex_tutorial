<?php

declare(strict_types=1);

namespace App\Application\Query;

use App\Domain\Repository\BookRepositoryInterface;
use App\Domain\Entity\Book;

final readonly class GetAvailableBooksQuery
{
    public function __construct(
        private BookRepositoryInterface $bookRepository
    ) {
    }

    /** @return Book[] */
    public function execute(): array
    {
        return $this->bookRepository->findAvailable();
    }
}