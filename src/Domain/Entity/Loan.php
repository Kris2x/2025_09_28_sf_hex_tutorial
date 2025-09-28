<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\ValueObject\BookId;
use App\Domain\ValueObject\UserId;
use DateTimeImmutable;

class Loan
{
    private const LOAN_PERIOD_DAYS = 14;
    private ?DateTimeImmutable $returnedAt = null;

    public function __construct(
        private string $id,
        private UserId $userId,
        private BookId $bookId,
        private DateTimeImmutable $borrowedAt
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function bookId(): BookId
    {
        return $this->bookId;
    }

    public function borrowedAt(): DateTimeImmutable
    {
        return $this->borrowedAt;
    }

    public function returnedAt(): ?DateTimeImmutable
    {
        return $this->returnedAt;
    }

    public function isActive(): bool
    {
        return $this->returnedAt === null;
    }

    public function returnBook(): void
    {
        if (!$this->isActive()) {
            throw new \DomainException('Loan is already returned');
        }

        $this->returnedAt = new DateTimeImmutable();
    }

    public function dueDate(): DateTimeImmutable
    {
        return $this->borrowedAt->modify(sprintf('+%d days', self::LOAN_PERIOD_DAYS));
    }

    public function isOverdue(): bool
    {
        return $this->isActive() && new DateTimeImmutable() > $this->dueDate();
    }

    public function calculateFine(): float
    {
        if (!$this->isOverdue()) {
            return 0.0;
        }

        $overdueDays = (new DateTimeImmutable())->diff($this->dueDate())->days;
        return $overdueDays * 0.50; // 50 groszy za dzień
    }
}