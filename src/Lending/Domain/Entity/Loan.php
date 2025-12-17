<?php

declare(strict_types=1);

namespace App\Lending\Domain\Entity;

use App\Lending\Domain\ValueObject\BookId;
use App\Lending\Domain\ValueObject\UserId;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'loans')]
class Loan
{
    private const LOAN_PERIOD_DAYS = 14;

    #[ORM\Column(type: 'datetime_immutable', name: 'returned_at', nullable: true)]
    private ?DateTimeImmutable $returnedAt = null;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'string')]
        private string $id,

        #[ORM\Column(type: 'user_id', name: 'user_id')]
        private UserId $userId,

        #[ORM\Column(type: 'book_id', name: 'book_id')]
        private BookId $bookId,

        #[ORM\Column(type: 'datetime_immutable', name: 'borrowed_at')]
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
