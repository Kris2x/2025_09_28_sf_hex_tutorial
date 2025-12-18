<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Provider;

use App\Shared\Contract\BookInfoProviderInterface;
use App\Shared\ReadModel\BookBasicInfo;
use Doctrine\DBAL\Connection;

/**
 * Adapter: Dostarcza informacje o książkach z modułu Catalog.
 *
 * Ten adapter implementuje kontrakt z Shared i jest używany przez inne moduły
 * (np. Lending) gdy potrzebują informacji o książkach.
 *
 * Używa DBAL (nie ORM) żeby:
 * - Nie zależeć od encji Lending
 * - Symulować własne źródło danych Catalog
 * - W przyszłości łatwo zmienić na własną tabelę/bazę
 */
final readonly class CatalogBookInfoProvider implements BookInfoProviderInterface
{
    public function __construct(
        private Connection $connection
    ) {}

    public function getBookInfo(string $bookId): ?BookBasicInfo
    {
        // Bezpośrednie zapytanie SQL - niezależne od encji ORM
        $sql = 'SELECT id, title, author, isbn FROM books WHERE id = :id';

        $result = $this->connection->executeQuery($sql, ['id' => $bookId]);
        $row = $result->fetchAssociative();

        if (!$row) {
            return null;
        }

        return new BookBasicInfo(
            id: $row['id'],
            title: $row['title'],
            author: $row['author'],
            isbn: $row['isbn']
        );
    }
}
