<?php

declare(strict_types=1);

namespace App\Catalog\Application\EventHandler;

use App\Lending\Domain\Event\BookBorrowedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Event Handler: Aktualizuje popularność książki gdy została wypożyczona.
 *
 * Ten handler należy do modułu CATALOG, ale reaguje na event z LENDING.
 * To pokazuje luźne powiązanie między modułami:
 * - Lending nie wie, że Catalog istnieje
 * - Catalog reaguje na eventy z Lending
 *
 * W przyszłości można dodać więcej handlerów bez zmiany Lending:
 * - SendNotificationOnBookBorrowed (Notification module)
 * - UpdateStatisticsOnBookBorrowed (Reporting module)
 */
#[AsMessageHandler(bus: 'event.bus')]
final readonly class UpdateBookPopularityOnBookBorrowed
{
    public function __construct(
        private LoggerInterface $logger
        // W prawdziwej implementacji:
        // private CatalogBookRepositoryInterface $catalogBookRepository
    ) {
    }

    public function __invoke(BookBorrowedEvent $event): void
    {
        // W prawdziwej implementacji:
        // $book = $this->catalogBookRepository->findById($event->bookId());
        // $book->incrementPopularity();
        // $this->catalogBookRepository->save($book);

        // Na razie logujemy - pokazuje że event dotarł
        $this->logger->info('Book popularity updated', [
            'bookId' => $event->bookId(),
            'userId' => $event->userId(),
            'loanId' => $event->loanId(),
            'occurredAt' => $event->occurredAt()->format('Y-m-d H:i:s'),
            'handler' => self::class,
        ]);
    }
}
