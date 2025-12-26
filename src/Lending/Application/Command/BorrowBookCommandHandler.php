<?php

declare(strict_types=1);

namespace App\Lending\Application\Command;

use App\Lending\Domain\Entity\Loan;
use App\Lending\Domain\Event\BookBorrowedEvent;
use App\Lending\Domain\Repository\BookRepositoryInterface;
use App\Lending\Domain\Repository\LoanRepositoryInterface;
use App\Lending\Domain\Repository\UserRepositoryInterface;
use App\Lending\Domain\ValueObject\BookId;
use App\Lending\Domain\ValueObject\UserId;
use App\Shared\Domain\Event\EventPublisherInterface;
use DateTimeImmutable;

/**
 * Handler: Obsługuje wypożyczenie książki.
 *
 * Handler zawiera logikę wykonania komendy.
 * Po wykonaniu operacji publikuje Domain Event.
 */
final readonly class BorrowBookCommandHandler
{
    public function __construct(
        private BookRepositoryInterface $bookRepository,
        private UserRepositoryInterface $userRepository,
        private LoanRepositoryInterface $loanRepository,
        private EventPublisherInterface $eventPublisher
    ) {}

    public function __invoke(BorrowBookCommand $command): void
    {
        $user = $this->userRepository->findById(new UserId($command->userId));
        if (!$user) {
            throw new \DomainException('User not found');
        }

        $book = $this->bookRepository->findById(new BookId($command->bookId));
        if (!$book) {
            throw new \DomainException('Book not found');
        }

        // Sprawdź reguły biznesowe (logika w encjach!)
        if (!$user->canBorrowBook()) {
            throw new \DomainException('User has reached maximum loan limit');
        }

        if (!$book->isAvailable()) {
            throw new \DomainException('Book is not available');
        }

        // Wykonaj operację biznesową
        $user->borrowBook();
        $book->borrow();

        // Stwórz wypożyczenie
        $loanId = uniqid('loan-', true);
        $loan = new Loan(
            $loanId,
            $user->id(),
            $book->id(),
            new DateTimeImmutable()
        );

        // Zapisz zmiany
        $this->userRepository->save($user);
        $this->bookRepository->save($book);
        $this->loanRepository->save($loan);

        // Opublikuj Domain Event
        $this->eventPublisher->publish(
            new BookBorrowedEvent(
                bookId: $command->bookId,
                userId: $command->userId,
                loanId: $loanId
            )
        );
    }
}
