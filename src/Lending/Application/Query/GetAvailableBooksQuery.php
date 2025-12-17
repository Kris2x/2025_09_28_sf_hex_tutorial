<?php

declare(strict_types=1);

namespace App\Lending\Application\Query;

use App\Lending\Domain\Repository\BookRepositoryInterface;
use App\Lending\Domain\Entity\Book;

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
