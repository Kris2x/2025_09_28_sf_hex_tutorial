<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Domain\Entity\CatalogBook;
use App\Catalog\Domain\Repository\CatalogBookRepositoryInterface;
use App\Catalog\Domain\ValueObject\CatalogBookId;

/**
 * Query: Pobieranie szczegółów książki z katalogu.
 */
final readonly class GetCatalogBookDetailsQuery
{
    public function __construct(
        private CatalogBookRepositoryInterface $bookRepository
    ) {}

    public function execute(string $bookId): ?CatalogBook
    {
        return $this->bookRepository->findById(new CatalogBookId($bookId));
    }
}
