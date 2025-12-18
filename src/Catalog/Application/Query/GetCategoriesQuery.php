<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Repository\CategoryRepositoryInterface;

/**
 * Query: Pobieranie kategorii.
 */
final readonly class GetCategoriesQuery
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository
    ) {}

    /**
     * Zwraca kategorie główne (root).
     *
     * @return Category[]
     */
    public function rootCategories(): array
    {
        return $this->categoryRepository->findRootCategories();
    }

    /**
     * Zwraca wszystkie kategorie.
     *
     * @return Category[]
     */
    public function all(): array
    {
        return $this->categoryRepository->findAll();
    }
}
