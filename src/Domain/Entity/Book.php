<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\ValueObject\BookId;
use DateTimeImmutable;

class Book
{
    private bool $isAvailable = true;

    public function __construct(
        private BookId $id,
        private string $title,
        private string $author,
        private string $isbn,
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