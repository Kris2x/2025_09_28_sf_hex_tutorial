<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine\Repository;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Repository\CategoryRepositoryInterface;
use App\Catalog\Domain\ValueObject\CategoryId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

/**
 * Adapter: Implementacja repozytorium kategorii z Doctrine.
 */
final class DoctrineCategoryRepository implements CategoryRepositoryInterface
{
    /** @var EntityRepository<Category> */
    private EntityRepository $repository;

    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        $this->repository = $this->entityManager->getRepository(Category::class);
    }

    public function save(Category $category): void
    {
        $this->entityManager->persist($category);
        $this->entityManager->flush();
    }

    public function findById(CategoryId $id): ?Category
    {
        return $this->repository->find($id->value());
    }

    public function findBySlug(string $slug): ?Category
    {
        return $this->repository->findOneBy(['slug' => $slug]);
    }

    public function findRootCategories(): array
    {
        return $this->repository->findBy(
            ['parent' => null],
            ['name' => 'ASC']
        );
    }

    public function findAll(): array
    {
        return $this->repository->findBy([], ['name' => 'ASC']);
    }
}
