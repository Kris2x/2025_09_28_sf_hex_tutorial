<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Loan;
use App\Domain\ValueObject\UserId;
use App\Domain\ValueObject\BookId;

interface LoanRepositoryInterface
{
    public function save(Loan $loan): void;

    public function findById(string $id): ?Loan;

    /** @return Loan[] */
    public function findActiveByUserId(UserId $userId): array;

    /** @return Loan[] */
    public function findByBookId(BookId $bookId): array;

    /** @return Loan[] */
    public function findOverdue(): array;

    /** @return Loan[] */
    public function findAll(): array;
}