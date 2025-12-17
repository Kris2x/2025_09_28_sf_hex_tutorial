<?php

declare(strict_types=1);

namespace App\Lending\Application\Query;

use App\Lending\Domain\Repository\LoanRepositoryInterface;
use App\Lending\Domain\Entity\Loan;
use App\Lending\Domain\ValueObject\UserId;

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
