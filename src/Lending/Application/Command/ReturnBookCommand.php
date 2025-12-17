<?php

declare(strict_types=1);

namespace App\Lending\Application\Command;

use App\Lending\Domain\Repository\BookRepositoryInterface;
use App\Lending\Domain\Repository\UserRepositoryInterface;
use App\Lending\Domain\Repository\LoanRepositoryInterface;
use App\Lending\Domain\ValueObject\BookId;
use App\Lending\Domain\ValueObject\UserId;

/**
 * Command: Zwrot książki.
 *
 * Command modyfikuje stan systemu.
 * Zwraca wysokość kary za przetrzymanie.
 */
final readonly class ReturnBookCommand
{
    public function __construct(
        private BookRepositoryInterface $bookRepository,
        private UserRepositoryInterface $userRepository,
        private LoanRepositoryInterface $loanRepository
    ) {
    }

    /**
     * @return float Kara za przetrzymanie (0.0 jeśli w terminie)
     */
    public function execute(string $userId, string $bookId): float
    {
        $user = $this->userRepository->findById(new UserId($userId));
        if (!$user) {
            throw new \DomainException('User not found');
        }

        $book = $this->bookRepository->findById(new BookId($bookId));
        if (!$book) {
            throw new \DomainException('Book not found');
        }

        // Znajdź aktywne wypożyczenie
        $loans = $this->loanRepository->findActiveByUserId($user->id());
        $activeLoan = null;

        foreach ($loans as $loan) {
            if ($loan->bookId()->equals($book->id())) {
                $activeLoan = $loan;
                break;
            }
        }

        if (!$activeLoan) {
            throw new \DomainException('No active loan found for this book');
        }

        // Oblicz karę (logika w encji Loan!)
        $fine = $activeLoan->calculateFine();

        // Wykonaj zwrot
        $user->returnBook();
        $book->return();
        $activeLoan->returnBook();

        // Zapisz zmiany
        $this->userRepository->save($user);
        $this->bookRepository->save($book);
        $this->loanRepository->save($activeLoan);

        return $fine;
    }
}
