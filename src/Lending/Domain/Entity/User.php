<?php

declare(strict_types=1);

namespace App\Lending\Domain\Entity;

use App\Lending\Domain\ValueObject\UserId;
use App\Lending\Domain\ValueObject\Email;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User
{
    #[ORM\Column(type: 'integer', name: 'active_loan_count')]
    private int $activeLoanCount = 0;

    private const MAX_ACTIVE_LOANS = 3;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'user_id')]
        private UserId $id,

        #[ORM\Column(type: 'string')]
        private string $name,

        #[ORM\Column(type: 'email', unique: true)]
        private Email $email,

        #[ORM\Column(type: 'datetime_immutable', name: 'registered_at')]
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
