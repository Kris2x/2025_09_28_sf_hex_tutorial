<?php

declare(strict_types=1);

namespace App\Lending\Application\Command;

/**
 * Command: Zwrot książki.
 *
 * Czyste DTO - tylko dane, bez logiki.
 */
final readonly class ReturnBookCommand
{
    public function __construct(
        public string $userId,
        public string $bookId
    ) {}
}
