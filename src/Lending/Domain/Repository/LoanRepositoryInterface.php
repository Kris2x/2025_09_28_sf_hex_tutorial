<?php

declare(strict_types=1);

namespace App\Lending\Domain\Repository;

use App\Lending\Domain\Entity\Loan;
use App\Lending\Domain\ValueObject\UserId;
use App\Lending\Domain\ValueObject\BookId;

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
