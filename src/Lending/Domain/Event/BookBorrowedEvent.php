<?php

declare(strict_types=1);

namespace App\Lending\Domain\Event;

use App\Shared\Domain\Event\DomainEventInterface;

/**
 * Domain Event: Książka została wypożyczona.
 *
 * Ten event informuje inne moduły (Bounded Contexts), że:
 * - Użytkownik X wypożyczył książkę Y
 * - Wydarzyło się to w momencie Z
 *
 * Event jest IMMUTABLE - opisuje coś co już się wydarzyło.
 * Inne moduły mogą na niego reagować (np. Catalog aktualizuje popularność).
 */
final readonly class BookBorrowedEvent implements DomainEventInterface
{
    private \DateTimeImmutable $occurredAt;

    public function __construct(
        private string $bookId,
        private string $userId,
        private string $loanId,
        ?\DateTimeImmutable $occurredAt = null
    ) {
        $this->occurredAt = $occurredAt ?? new \DateTimeImmutable();
    }

    public function bookId(): string
    {
        return $this->bookId;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function loanId(): string
    {
        return $this->loanId;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
