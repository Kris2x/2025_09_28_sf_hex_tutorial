<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Entity\Author;
use App\Catalog\Domain\Entity\CatalogBook;
use App\Catalog\Domain\Event\BookAddedToCatalogEvent;
use App\Catalog\Domain\Repository\AuthorRepositoryInterface;
use App\Catalog\Domain\Repository\CatalogBookRepositoryInterface;
use App\Catalog\Domain\ValueObject\AuthorId;
use App\Catalog\Domain\ValueObject\CatalogBookId;
use App\Catalog\Domain\ValueObject\Isbn;
use App\Shared\Domain\Event\EventPublisherInterface;

/**
 * Handler: Obsługuje dodanie nowej książki do katalogu.
 *
 * Ten handler:
 * 1. Tworzy lub znajduje autora
 * 2. Tworzy książkę w katalogu
 * 3. Publikuje event BookAddedToCatalogEvent
 * 4. Lending BC nasłuchuje i tworzy swoją wersję Book
 */
final readonly class AddBookToCatalogCommandHandler
{
    public function __construct(
        private CatalogBookRepositoryInterface $bookRepository,
        private AuthorRepositoryInterface $authorRepository,
        private EventPublisherInterface $eventPublisher
    ) {}

    public function __invoke(AddBookToCatalogCommand $command): CatalogBook
    {
        // 1. Znajdź lub utwórz autora
        $author = $this->authorRepository->findById(new AuthorId($command->authorId));

        if ($author === null) {
            $author = new Author(
                new AuthorId($command->authorId),
                $command->authorFirstName,
                $command->authorLastName
            );
            $this->authorRepository->save($author);
        }

        // 2. Utwórz książkę w katalogu
        $book = new CatalogBook(
            new CatalogBookId($command->bookId),
            $command->title,
            new Isbn($command->isbn),
            $author,
            $command->publishedAt
        );

        if ($command->description !== null) {
            $book->updateDescription($command->description);
        }

        $this->bookRepository->save($book);

        // 3. Publikuj event - Lending BC stworzy swoją wersję
        $this->eventPublisher->publish(new BookAddedToCatalogEvent(
            catalogBookId: $command->bookId,
            title: $command->title,
            authorName: $author->fullName(),
            isbn: $command->isbn,
            publishedAt: $command->publishedAt
        ));

        return $book;
    }
}
