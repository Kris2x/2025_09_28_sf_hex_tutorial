<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use DateTimeImmutable;

/**
 * Command: Dodanie nowej książki do katalogu.
 *
 * Czyste DTO - tylko dane, bez logiki.
 */
final readonly class AddBookToCatalogCommand
{
    public function __construct(
        public string $bookId,
        public string $title,
        public string $isbn,
        public string $authorId,
        public string $authorFirstName,
        public string $authorLastName,
        public DateTimeImmutable $publishedAt,
        public ?string $description = null
    ) {}
}
