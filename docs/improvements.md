# Potencjalne ulepszenia

[< Powrót do README](../README.md)

## Spis treści
- [Przegląd](#przegląd)
- [1. Atrybuty ORM w domenie](#1-atrybuty-orm-w-domenie)
- [2. Query zwraca encje zamiast Read Models](#2-query-zwraca-encje-zamiast-read-models)
- [3. Eventy tworzone w handlerze](#3-eventy-tworzone-w-handlerze)
- [Roadmap](#roadmap)
- [Rekomendacje](#rekomendacje)

---

## Przegląd

Obecna implementacja jest **pragmatyczna** - działa poprawnie i jest łatwa w utrzymaniu, ale zawiera kompromisy względem "czystej" architektury.

Poniżej opisano trzy główne obszary do poprawy dla pełnej zgodności z Clean Architecture, DDD i CQRS.

### Skala kompromisów

| Aspekt | Obecny stan | "Czyste" podejście | Trudność zmiany |
|--------|-------------|-------------------|-----------------|
| ORM w domenie | Atrybuty Doctrine | XML/YAML mapping | Średnia |
| Query zwraca | Encje domenowe | Read Models (DTO) | Średnia |
| Eventy tworzone przez | Handler | Aggregate Root | Wysoka |

---

## 1. Atrybuty ORM w domenie

### Problem

Warstwa Domain zawiera zależność od infrastruktury (Doctrine ORM):

```php
namespace App\Lending\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;  // ← Zależność od infrastruktury!

#[ORM\Entity]
#[ORM\Table(name: 'books')]
class Book
{
    #[ORM\Id]
    #[ORM\Column(type: 'book_id')]
    private BookId $id;

    #[ORM\Column(type: 'boolean')]
    private bool $isAvailable = true;

    // ...
}
```

### Dlaczego to problem?

1. **Domena wie o szczegółach technicznych** - łamie zasadę Dependency Inversion
2. **Vendor lock-in** - zmiana ORM wymaga zmiany domeny
3. **Testowanie** - choć nadal możliwe, domena "ciągnie" za sobą Doctrine

### Rozwiązanie: XML/YAML Mapping

**Krok 1: Czysta encja**

```php
namespace App\Lending\Domain\Entity;

// Żadnych importów Doctrine!
class Book
{
    private BookId $id;
    private string $title;
    private bool $isAvailable = true;

    public function __construct(
        BookId $id,
        string $title,
        string $author,
        string $isbn,
        \DateTimeImmutable $publishedAt
    ) {
        $this->id = $id;
        // ...
    }

    public function borrow(): void
    {
        if (!$this->isAvailable) {
            throw new \DomainException('Book is not available');
        }
        $this->isAvailable = false;
    }
}
```

**Krok 2: Osobny mapping**

```xml
<!-- config/doctrine/Lending.Book.orm.xml -->
<doctrine-mapping>
    <entity name="App\Lending\Domain\Entity\Book" table="books">
        <id name="id" type="book_id" column="id"/>
        <field name="title" type="string"/>
        <field name="author" type="string"/>
        <field name="isbn" type="string" unique="true"/>
        <field name="isAvailable" type="boolean" column="is_available"/>
        <field name="publishedAt" type="datetime_immutable" column="published_at"/>
    </entity>
</doctrine-mapping>
```

**Krok 3: Konfiguracja**

```yaml
# config/packages/doctrine.yaml
doctrine:
    orm:
        mappings:
            Lending:
                type: xml
                dir: '%kernel.project_dir%/config/doctrine/lending'
                prefix: App\Lending\Domain\Entity
```

### Porównanie

| Podejście | Zalety | Wady |
|-----------|--------|------|
| **Atrybuty (obecne)** | Wszystko w jednym miejscu, łatwiejszy refactoring, lepsze IDE support | Domena zależy od ORM |
| **XML/YAML mapping** | Czysta domena, pełna przenośność między ORM | Więcej plików, mapping osobno od encji |

### Kiedy warto zmienić?

- Projekt wymaga niezależności od Doctrine
- Domena jest współdzielona między aplikacjami
- Zespół stosuje ścisłe zasady Clean Architecture
- Planowana migracja na inny ORM

---

## 2. Query zwraca encje zamiast Read Models

### Problem

W CQRS strona Query powinna zwracać **Read Models** (DTO), nie encje domenowe:

```php
namespace App\Lending\Application\Query;

class GetAvailableBooksQuery
{
    public function execute(): array
    {
        // ❌ Zwraca encje domenowe
        return $this->bookRepository->findAvailable();  // Book[]
    }
}
```

### Dlaczego to problem?

1. **Encja jest do zapisu** - ma metody biznesowe, które nie powinny być dostępne przy odczycie
2. **Brak optymalizacji** - encja ładuje wszystkie pola, nawet niepotrzebne
3. **Coupling** - zmiana encji wpływa na prezentację
4. **Lazy loading** - ryzyko N+1 queries

### Rozwiązanie: Read Models

**Krok 1: Dedykowane DTO**

```php
namespace App\Lending\Application\ReadModel;

/**
 * Read Model - tylko dane do wyświetlenia.
 *
 * Nie ma metod biznesowych, jest immutable.
 */
final readonly class BookListItem
{
    public function __construct(
        public string $id,
        public string $title,
        public string $author,
        public bool $isAvailable
    ) {}
}
```

**Krok 2: Query zwraca Read Model**

```php
namespace App\Lending\Application\Query;

class GetAvailableBooksQuery
{
    public function __construct(
        private BookReadModelRepositoryInterface $readRepository
    ) {}

    /** @return BookListItem[] */
    public function execute(): array
    {
        return $this->readRepository->findAvailableForList();
    }
}
```

**Krok 3: Dedykowane repozytorium dla odczytów**

```php
namespace App\Lending\Infrastructure\Doctrine\ReadModel;

class DoctrineBookReadModelRepository implements BookReadModelRepositoryInterface
{
    public function findAvailableForList(): array
    {
        $qb = $this->entityManager->createQueryBuilder();

        // Tylko potrzebne pola - bez lazy loading
        $results = $qb
            ->select('b.id, b.title, b.author, b.isAvailable')
            ->from(Book::class, 'b')
            ->where('b.isAvailable = true')
            ->getQuery()
            ->getArrayResult();

        return array_map(
            fn(array $row) => new BookListItem(
                $row['id'],
                $row['title'],
                $row['author'],
                $row['isAvailable']
            ),
            $results
        );
    }
}
```

### Porównanie

| Podejście | Zalety | Wady |
|-----------|--------|------|
| **Encje (obecne)** | Mniej kodu, prostsze | Brak separacji Read/Write |
| **Read Models** | Optymalizacja, separacja, bezpieczeństwo | Więcej DTO, mapping |

### Kiedy warto zmienić?

- Problemy z wydajnością (N+1, niepotrzebne dane)
- Skomplikowana prezentacja wymagająca innych danych niż domena
- Planowane osobne źródło danych dla odczytów (Elasticsearch, Redis)
- Wiele różnych widoków tych samych danych

---

## 3. Eventy tworzone w handlerze

### Problem

Handler wie, jakie eventy publikować. W DDD to **encja (Aggregate Root)** powinna wiedzieć:

```php
// ❌ Obecny stan - handler tworzy event
class BorrowBookCommandHandler
{
    public function __invoke(BorrowBookCommand $command): void
    {
        $book = $this->bookRepository->findById(/*...*/);

        $book->borrow();  // Encja tylko zmienia stan

        // Handler wie jaki event opublikować
        $this->eventPublisher->publish(
            new BookBorrowedEvent(
                $book->id()->value(),
                $command->userId,
                $loan->id()->value()
            )
        );
    }
}
```

### Dlaczego to problem?

1. **Logika eventów poza domeną** - handler musi wiedzieć co się wydarzyło
2. **Ryzyko niespójności** - można zapomnieć o evencie
3. **Duplikacja** - różne handlery mogą tworzyć te same eventy

### Rozwiązanie: Aggregate Root

**Krok 1: Trait do zbierania eventów**

```php
namespace App\Shared\Domain\Aggregate;

use App\Shared\Domain\Event\DomainEventInterface;

trait AggregateRoot
{
    /** @var DomainEventInterface[] */
    private array $domainEvents = [];

    protected function recordEvent(DomainEventInterface $event): void
    {
        $this->domainEvents[] = $event;
    }

    /** @return DomainEventInterface[] */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }
}
```

**Krok 2: Encja używa traitu**

```php
namespace App\Lending\Domain\Entity;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Lending\Domain\Event\BookBorrowedEvent;

class Book
{
    use AggregateRoot;

    public function borrow(UserId $userId, LoanId $loanId): void
    {
        if (!$this->isAvailable) {
            throw new \DomainException('Book is not available');
        }

        $this->isAvailable = false;

        // ✅ Encja sama rejestruje event
        $this->recordEvent(new BookBorrowedEvent(
            $this->id->value(),
            $userId->value(),
            $loanId->value()
        ));
    }
}
```

**Krok 3: Handler publikuje eventy z encji**

```php
class BorrowBookCommandHandler
{
    public function __invoke(BorrowBookCommand $command): void
    {
        $book = $this->bookRepository->findById(/*...*/);

        // Encja rejestruje event wewnętrznie
        $book->borrow($userId, $loanId);

        $this->bookRepository->save($book);

        // ✅ Handler tylko publikuje to, co encja zarejestrowała
        foreach ($book->pullDomainEvents() as $event) {
            $this->eventPublisher->publish($event);
        }
    }
}
```

### Porównanie

| Podejście | Zalety | Wady |
|-----------|--------|------|
| **Handler (obecne)** | Prostsze, encja nie wie o eventach | Logika eventów poza domeną |
| **Aggregate Root** | Encja jest "źródłem prawdy", hermetyzacja | Więcej kodu, encja zna eventy |

### Kiedy warto zmienić?

- Złożona logika biznesowa z wieloma eventami
- Potrzeba Event Sourcing w przyszłości
- Wiele handlerów wykonujących podobne operacje
- Ścisłe przestrzeganie DDD

---

## Roadmap

### Faza 1: Podstawy (obecnie)
- [x] Architektura hexagonalna
- [x] Bounded Contexts (Lending, Catalog)
- [x] CQRS (Command/Handler, Query)
- [x] Domain Events i komunikacja między BC
- [x] Command Bus jako port

### Faza 2: Rozszerzenia (TODO)
- [ ] Read Models dla Query
- [ ] Więcej Domain Events (BookReturned, LoanOverdue)
- [ ] Membership BC
- [ ] Testy jednostkowe domeny

### Faza 3: Zaawansowane (opcjonalnie)
- [ ] Aggregate Root pattern
- [ ] XML mapping dla czystej domeny
- [ ] Event Sourcing
- [ ] CQRS z osobnymi bazami

---

## Rekomendacje

### Dla małych/średnich projektów

**Zostań przy pragmatycznym podejściu:**
- Atrybuty Doctrine w encjach - OK
- Query zwraca encje - OK (do momentu problemów z wydajnością)
- Eventy w handlerach - OK

**Priorytet: czytelność i produktywność.**

### Dla dużych projektów

**Rozważ pełną separację:**
1. **Read Models** - gdy masz problemy z wydajnością lub wiele widoków
2. **Aggregate Root** - gdy logika eventów jest złożona
3. **XML mapping** - gdy domena jest współdzielona lub planujesz zmianę ORM

**Priorytet: elastyczność i utrzymywalność długoterminowa.**

### Zasada

> "Make it work, make it right, make it fast"
> — Kent Beck

Obecna implementacja **działa** i jest **poprawna**. Optymalizuj tylko gdy masz konkretny problem.

---

## Materiały

### Książki
- "Domain-Driven Design" - Eric Evans
- "Implementing Domain-Driven Design" - Vaughn Vernon
- "Clean Architecture" - Robert C. Martin
- "Patterns, Principles, and Practices of DDD" - Scott Millett

### Artykuły
- [Hexagonal Architecture - Alistair Cockburn](https://alistair.cockburn.us/hexagonal-architecture/)
- [CQRS - Martin Fowler](https://martinfowler.com/bliki/CQRS.html)
- [Aggregate Root - Martin Fowler](https://martinfowler.com/bliki/DDD_Aggregate.html)

---

[< Powrót do README](../README.md)
