<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Entity\CatalogBook;
use App\Catalog\Domain\ValueObject\CatalogBookId;
use App\Catalog\Domain\ValueObject\Isbn;

/**
 * Port: Repozytorium książek w katalogu.
 */
interface CatalogBookRepositoryInterface
{
    public function save(CatalogBook $book): void;

    public function findById(CatalogBookId $id): ?CatalogBook;

    public function findByIsbn(Isbn $isbn): ?CatalogBook;

    /**
     * Wyszukuje książki po fragmencie tytułu.
     *
     * @return CatalogBook[]
     */
    public function searchByTitle(string $query): array;

    /**
     * Zwraca najpopularniejsze książki.
     *
     * @return CatalogBook[]
     */
    public function findMostPopular(int $limit = 10): array;

    /**
     * Zwraca książki z danej kategorii.
     *
     * @return CatalogBook[]
     */
    public function findByCategory(string $categorySlug): array;

    /**
     * Zwraca wszystkie książki danego autora.
     *
     * @return CatalogBook[]
     */
    public function findByAuthorId(string $authorId): array;
}
