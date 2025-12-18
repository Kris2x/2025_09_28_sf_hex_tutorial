<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use App\Catalog\Domain\ValueObject\CategoryId;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Encja: Kategoria książek.
 *
 * Kategorie są hierarchiczne - mogą mieć rodzica.
 * Np. "Programowanie" -> "PHP" -> "Symfony"
 */
#[ORM\Entity]
#[ORM\Table(name: 'catalog_categories')]
class Category
{
    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true)]
    private ?Category $parent = null;

    /** @var Collection<int, Category> */
    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: Category::class)]
    private Collection $children;

    /** @var Collection<int, CatalogBook> */
    #[ORM\ManyToMany(mappedBy: 'categories', targetEntity: CatalogBook::class)]
    private Collection $books;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'category_id')]
        private CategoryId $id,

        #[ORM\Column(type: 'string', length: 100)]
        private string $name,

        #[ORM\Column(type: 'string', length: 100, unique: true)]
        private string $slug
    ) {
        $this->children = new ArrayCollection();
        $this->books = new ArrayCollection();
    }

    public function id(): CategoryId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function parent(): ?Category
    {
        return $this->parent;
    }

    public function setParent(?Category $parent): void
    {
        if ($parent !== null && $parent->id()->equals($this->id)) {
            throw new \DomainException('Category cannot be its own parent');
        }

        $this->parent = $parent;
    }

    /**
     * @return Collection<int, Category>
     */
    public function children(): Collection
    {
        return $this->children;
    }

    public function hasChildren(): bool
    {
        return !$this->children->isEmpty();
    }

    public function isRoot(): bool
    {
        return $this->parent === null;
    }

    /**
     * Zwraca pełną ścieżkę kategorii: "Programowanie / PHP / Symfony"
     */
    public function path(): string
    {
        if ($this->parent === null) {
            return $this->name;
        }

        return $this->parent->path() . ' / ' . $this->name;
    }

    public function rename(string $name, string $slug): void
    {
        $this->name = $name;
        $this->slug = $slug;
    }
}
