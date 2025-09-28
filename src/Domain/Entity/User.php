<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\ValueObject\UserId;
use App\Domain\ValueObject\Email;
use DateTimeImmutable;

class User
{
    private int $activeLoanCount = 0;
    private const MAX_ACTIVE_LOANS = 3;

    public function __construct(
        private UserId $id,
        private string $name,
        private Email $email,
        private DateTimeImmutable $registeredAt
    ) {
    }

    public function id(): UserId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function registeredAt(): DateTimeImmutable
    {
        return $this->registeredAt;
    }

    public function activeLoanCount(): int
    {
        return $this->activeLoanCount;
    }

    public function canBorrowBook(): bool
    {
        return $this->activeLoanCount < self::MAX_ACTIVE_LOANS;
    }

    public function borrowBook(): void
    {
        if (!$this->canBorrowBook()) {
            throw new \DomainException(
                sprintf('User cannot borrow more than %d books', self::MAX_ACTIVE_LOANS)
            );
        }

        $this->activeLoanCount++;
    }

    public function returnBook(): void
    {
        if ($this->activeLoanCount <= 0) {
            throw new \DomainException('User has no active loans');
        }

        $this->activeLoanCount--;
    }
}