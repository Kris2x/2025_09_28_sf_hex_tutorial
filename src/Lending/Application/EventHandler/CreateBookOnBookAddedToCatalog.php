<?php

declare(strict_types=1);

namespace App\Lending\Application\EventHandler;

use App\Catalog\Domain\Event\BookAddedToCatalogEvent;
use App\Lending\Domain\Entity\Book;
use App\Lending\Domain\Repository\BookRepositoryInterface;
use App\Lending\Domain\ValueObject\BookId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Event Handler: Tworzy książkę w Lending gdy zostanie dodana do Catalog.
 *
 * Ten handler nasłuchuje na BookAddedToCatalogEvent z modułu Catalog
 * i automatycznie tworzy odpowiadającą encję Book w module Lending.
 *
 * Dzięki temu:
 * - Catalog zarządza metadanymi (opis, kategorie, autorzy)
 * - Lending zarządza wypożyczeniami (dostępność, kto wypożyczył)
 * - Oba moduły mają WŁASNE encje Book z różnymi polami
 * - Synchronizacja odbywa się przez eventy
 */
#[AsMessageHandler(bus: 'event.bus')]
final readonly class CreateBookOnBookAddedToCatalog
{
    public function __construct(
        private BookRepositoryInterface $bookRepository
    ) {}

    public function __invoke(BookAddedToCatalogEvent $event): void
    {
        // Sprawdź czy książka już nie istnieje (idempotentność)
        $existingBook = $this->bookRepository->findById(new BookId($event->catalogBookId));

        if ($existingBook !== null) {
            return;
        }

        // Utwórz książkę w Lending z tym samym ID co w Catalog
        $book = new Book(
            new BookId($event->catalogBookId),
            $event->title,
            $event->authorName,
            $event->isbn,
            $event->publishedAt
        );

        $this->bookRepository->save($book);
    }
}
