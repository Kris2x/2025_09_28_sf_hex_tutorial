<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Shared\Domain\Event\DomainEventInterface;

/**
 * Domain Event: Książka została dodana do katalogu.
 *
 * Ten event jest publikowany gdy bibliotekarz dodaje nową książkę.
 * Lending BC nasłuchuje i automatycznie tworzy swoją wersję Book.
 */
final readonly class BookAddedToCatalogEvent implements DomainEventInterface
{
    private \DateTimeImmutable $occurredAt;

    public function __construct(
        public string $catalogBookId,
        public string $title,
        public string $authorName,
        public string $isbn,
        public \DateTimeImmutable $publishedAt
    ) {
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
