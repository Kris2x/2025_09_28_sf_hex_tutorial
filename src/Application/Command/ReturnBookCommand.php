<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Domain\Repository\BookRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Repository\LoanRepositoryInterface;
use App\Domain\ValueObject\BookId;
use App\Domain\ValueObject\UserId;

final readonly class ReturnBookCommand
{
    public function __construct(
        private BookRepositoryInterface $bookRepository,
        private UserRepositoryInterface $userRepository,
        private LoanRepositoryInterface $loanRepository
    ) {
    }

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

        // Oblicz karę
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