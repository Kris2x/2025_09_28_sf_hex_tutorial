<?php

declare(strict_types=1);

namespace App\Lending\Domain\Entity;

use App\Lending\Domain\ValueObject\BookId;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'books')]
class Book
{
    #[ORM\Column(type: 'boolean')]
    private bool $isAvailable = true;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'book_id')]
        private BookId $id,

        #[ORM\Column(type: 'string')]
        private string $title,

        #[ORM\Column(type: 'string')]
        private string $author,

        #[ORM\Column(type: 'string', unique: true)]
        private string $isbn,

        #[ORM\Column(type: 'datetime_immutable', name: 'published_at')]
        private DateTimeImmutable $publishedAt
    ) {
    }

    public function id(): BookId
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function author(): string
    {
        return $this->author;
    }

    public function isbn(): string
    {
        return $this->isbn;
    }

    public function publishedAt(): DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function isAvailable(): bool
    {
        return $this->isAvailable;
    }

    public function borrow(): void
    {
        if (!$this->isAvailable) {
            throw new \DomainException('Book is not available for borrowing');
        }

        $this->isAvailable = false;
    }

    public function return(): void
    {
        if ($this->isAvailable) {
            throw new \DomainException('Book is already available');
        }

        $this->isAvailable = true;
    }
}
