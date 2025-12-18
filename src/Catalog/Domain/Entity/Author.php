<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use App\Catalog\Domain\ValueObject\AuthorId;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Encja: Autor książek w katalogu.
 *
 * Autor jest agregatem - zarządza swoimi danymi.
 * Relacja do książek jest jednostronna (książka zna autora).
 */
#[ORM\Entity]
#[ORM\Table(name: 'catalog_authors')]
class Author
{
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $biography = null;

    /** @var Collection<int, CatalogBook> */
    #[ORM\OneToMany(mappedBy: 'author', targetEntity: CatalogBook::class)]
    private Collection $books;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'author_id')]
        private AuthorId $id,

        #[ORM\Column(type: 'string', length: 255)]
        private string $firstName,

        #[ORM\Column(type: 'string', length: 255)]
        private string $lastName
    ) {
        $this->books = new ArrayCollection();
    }

    public function id(): AuthorId
    {
        return $this->id;
    }

    public function firstName(): string
    {
        return $this->firstName;
    }

    public function lastName(): string
    {
        return $this->lastName;
    }

    public function fullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    public function biography(): ?string
    {
        return $this->biography;
    }

    public function updateBiography(string $biography): void
    {
        $this->biography = $biography;
    }

    /**
     * @return Collection<int, CatalogBook>
     */
    public function books(): Collection
    {
        return $this->books;
    }

    public function booksCount(): int
    {
        return $this->books->count();
    }
}
