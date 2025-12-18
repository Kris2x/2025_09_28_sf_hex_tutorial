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
 * Command: Dodanie nowej książki do katalogu.
 *
 * Ten command:
 * 1. Tworzy lub znajduje autora
 * 2. Tworzy książkę w katalogu
 * 3. Publikuje event BookAddedToCatalogEvent
 * 4. Lending BC nasłuchuje i tworzy swoją wersję Book
 */
final readonly class AddBookToCatalogCommand
{
    public function __construct(
        private CatalogBookRepositoryInterface $bookRepository,
        private AuthorRepositoryInterface $authorRepository,
        private EventPublisherInterface $eventPublisher
    ) {}

    public function execute(
        string $bookId,
        string $title,
        string $isbn,
        string $authorId,
        string $authorFirstName,
        string $authorLastName,
        \DateTimeImmutable $publishedAt,
        ?string $description = null
    ): CatalogBook {
        // 1. Znajdź lub utwórz autora
        $author = $this->authorRepository->findById(new AuthorId($authorId));

        if ($author === null) {
            $author = new Author(
                new AuthorId($authorId),
                $authorFirstName,
                $authorLastName
            );
            $this->authorRepository->save($author);
        }

        // 2. Utwórz książkę w katalogu
        $book = new CatalogBook(
            new CatalogBookId($bookId),
            $title,
            new Isbn($isbn),
            $author,
            $publishedAt
        );

        if ($description !== null) {
            $book->updateDescription($description);
        }

        $this->bookRepository->save($book);

        // 3. Publikuj event - Lending BC stworzy swoją wersję
        $this->eventPublisher->publish(new BookAddedToCatalogEvent(
            catalogBookId: $bookId,
            title: $title,
            authorName: $author->fullName(),
            isbn: $isbn,
            publishedAt: $publishedAt
        ));

        return $book;
    }
}
