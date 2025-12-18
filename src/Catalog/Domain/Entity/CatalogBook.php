<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use App\Catalog\Domain\ValueObject\CatalogBookId;
use App\Catalog\Domain\ValueObject\Isbn;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Encja: Książka w katalogu.
 *
 * UWAGA: To jest INNA encja niż Lending\Book!
 * - Catalog.CatalogBook: metadane, opis, kategorie, popularność
 * - Lending.Book: dostępność, wypożyczenia
 *
 * Te same dane fizyczne, ale różne modele domenowe.
 */
#[ORM\Entity]
#[ORM\Table(name: 'catalog_books')]
class CatalogBook
{
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'integer')]
    private int $popularity = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: Author::class, inversedBy: 'books')]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id', nullable: false)]
    private Author $author;

    /** @var Collection<int, Category> */
    #[ORM\ManyToMany(targetEntity: Category::class, inversedBy: 'books')]
    #[ORM\JoinTable(name: 'catalog_book_categories')]
    private Collection $categories;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'catalog_book_id')]
        private CatalogBookId $id,

        #[ORM\Column(type: 'string', length: 255)]
        private string $title,

        #[ORM\Column(type: 'catalog_isbn')]
        private Isbn $isbn,

        Author $author,

        #[ORM\Column(type: 'date_immutable')]
        private DateTimeImmutable $publishedAt
    ) {
        $this->author = $author;
        $this->categories = new ArrayCollection();
        $this->createdAt = new DateTimeImmutable();
    }

    public function id(): CatalogBookId
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function isbn(): Isbn
    {
        return $this->isbn;
    }

    public function author(): Author
    {
        return $this->author;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function publishedAt(): DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function popularity(): int
    {
        return $this->popularity;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, Category>
     */
    public function categories(): Collection
    {
        return $this->categories;
    }

    // === Zachowania biznesowe ===

    public function updateDescription(string $description): void
    {
        $this->description = $description;
    }

    public function addCategory(Category $category): void
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
        }
    }

    public function removeCategory(Category $category): void
    {
        $this->categories->removeElement($category);
    }

    public function hasCategory(Category $category): bool
    {
        return $this->categories->contains($category);
    }

    /**
     * Zwiększa popularność książki.
     * Wywoływane np. gdy książka zostanie wypożyczona.
     */
    public function increasePopularity(): void
    {
        $this->popularity++;
    }

    /**
     * Zmienia autora książki.
     */
    public function changeAuthor(Author $author): void
    {
        $this->author = $author;
    }
}
