<?php

declare(strict_types=1);

namespace App\Lending\Application\Query;

use App\Lending\Domain\Repository\LoanRepositoryInterface;
use App\Lending\Domain\ValueObject\UserId;
use App\Shared\Contract\BookInfoProviderInterface;

/**
 * Query: Pobiera szczegóły wypożyczenia wraz z informacjami o książce.
 *
 * Ten Query demonstruje komunikację między modułami:
 * - Lending ma dane o wypożyczeniu (Loan)
 * - Catalog ma pełne dane o książce (tytuł, autor, ISBN)
 * - Lending używa kontraktu BookInfoProviderInterface żeby pobrać dane z Catalog
 *
 * Lending NIE WIE, że implementacja pochodzi z Catalog.
 * Zna tylko interfejs z Shared/Contract.
 */
final readonly class GetLoanDetailsQuery
{
    public function __construct(
        private LoanRepositoryInterface $loanRepository,
        private BookInfoProviderInterface $bookInfoProvider  // ← Kontrakt z Shared
    ) {}

    public function execute(string $userId): array
    {
        $loans = $this->loanRepository->findActiveByUserId(new UserId($userId));

        if (empty($loans)) {
            return [];
        }

        $result = [];
        foreach ($loans as $loan) {
            // Pobierz info o książce przez kontrakt (z Catalog)
            $bookInfo = $this->bookInfoProvider->getBookInfo($loan->bookId()->value());

            $result[] = [
                'loanId' => $loan->id(),
                'borrowedAt' => $loan->borrowedAt()->format('Y-m-d H:i:s'),
                'dueDate' => $loan->dueDate()->format('Y-m-d'),
                // Dane z Catalog (przez kontrakt):
                'book' => $bookInfo ? [
                    'id' => $bookInfo->id,
                    'title' => $bookInfo->title,
                    'author' => $bookInfo->author,
                    'isbn' => $bookInfo->isbn,
                ] : null,
            ];
        }

        return $result;
    }
}
