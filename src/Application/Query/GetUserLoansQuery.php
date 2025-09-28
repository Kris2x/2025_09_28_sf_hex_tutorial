<?php

declare(strict_types=1);

namespace App\Application\Query;

use App\Domain\Repository\LoanRepositoryInterface;
use App\Domain\Entity\Loan;
use App\Domain\ValueObject\UserId;

final readonly class GetUserLoansQuery
{
    public function __construct(
        private LoanRepositoryInterface $loanRepository
    ) {
    }

    /** @return Loan[] */
    public function execute(string $userId): array
    {
        return $this->loanRepository->findActiveByUserId(new UserId($userId));
    }
}