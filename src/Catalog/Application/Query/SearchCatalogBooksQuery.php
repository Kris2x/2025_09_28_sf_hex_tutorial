<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Domain\Entity\CatalogBook;
use App\Catalog\Domain\Repository\CatalogBookRepositoryInterface;

/**
 * Query: Wyszukiwanie książek w katalogu.
 */
final readonly class SearchCatalogBooksQuery
{
    public function __construct(
        private CatalogBookRepositoryInterface $bookRepository
    ) {}

    /**
     * Wyszukuje książki po fragmencie tytułu.
     *
     * @return CatalogBook[]
     */
    public function byTitle(string $query): array
    {
        return $this->bookRepository->searchByTitle($query);
    }

    /**
     * Zwraca najpopularniejsze książki.
     *
     * @return CatalogBook[]
     */
    public function mostPopular(int $limit = 10): array
    {
        return $this->bookRepository->findMostPopular($limit);
    }

    /**
     * Zwraca książki z danej kategorii.
     *
     * @return CatalogBook[]
     */
    public function byCategory(string $categorySlug): array
    {
        return $this->bookRepository->findByCategory($categorySlug);
    }

    /**
     * Zwraca książki danego autora.
     *
     * @return CatalogBook[]
     */
    public function byAuthor(string $authorId): array
    {
        return $this->bookRepository->findByAuthorId($authorId);
    }
}
