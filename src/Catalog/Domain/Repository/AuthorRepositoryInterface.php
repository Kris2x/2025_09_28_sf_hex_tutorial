<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Entity\Author;
use App\Catalog\Domain\ValueObject\AuthorId;

/**
 * Port: Repozytorium autorów.
 */
interface AuthorRepositoryInterface
{
    public function save(Author $author): void;

    public function findById(AuthorId $id): ?Author;

    /**
     * Wyszukuje autorów po nazwisku.
     *
     * @return Author[]
     */
    public function searchByName(string $query): array;

    /**
     * Zwraca autorów z największą liczbą książek.
     *
     * @return Author[]
     */
    public function findMostProlific(int $limit = 10): array;
}
