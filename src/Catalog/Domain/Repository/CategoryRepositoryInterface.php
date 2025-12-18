<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\ValueObject\CategoryId;

/**
 * Port: Repozytorium kategorii.
 */
interface CategoryRepositoryInterface
{
    public function save(Category $category): void;

    public function findById(CategoryId $id): ?Category;

    public function findBySlug(string $slug): ?Category;

    /**
     * Zwraca wszystkie kategorie główne (bez rodzica).
     *
     * @return Category[]
     */
    public function findRootCategories(): array;

    /**
     * Zwraca wszystkie kategorie jako płaską listę.
     *
     * @return Category[]
     */
    public function findAll(): array;
}
