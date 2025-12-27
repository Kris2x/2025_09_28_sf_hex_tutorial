# Domain Events

[< Powrót do README](../../README.md)

## Spis treści
- [Czym są Domain Events?](#czym-są-domain-events)
- [Struktura eventu](#struktura-eventu)
- [Publikacja eventów](#publikacja-eventów)
- [Event Handlers](#event-handlers)
- [Komunikacja między BC](#komunikacja-między-bc)
- [Konfiguracja Messenger](#konfiguracja-messenger)
- [Przykłady z projektu](#przykłady-z-projektu)

---

## Czym są Domain Events?

**Domain Event** to zapis faktu, że coś ważnego **już się wydarzyło** w domenie.

### Charakterystyka

| Cecha | Opis |
|-------|------|
| **Przeszły czas** | `BookBorrowed`, nie `BorrowBook` |
| **Immutable** | Raz stworzony, nie zmienia się |
| **Zawiera kontekst** | Kto, co, kiedy |
| **Asynchroniczny** | Przetwarzany niezależnie |

### Korzyści

1. **Loose coupling** - emitent nie zna odbiorców
2. **Rozszerzalność** - łatwo dodać nowych słuchaczy
3. **Audit log** - historia zdarzeń
4. **Eventual consistency** - synchronizacja między BC

### Przykład przepływu

```
┌─────────────────┐    BookBorrowedEvent    ┌─────────────────┐
│                 │ ─────────────────────► │                 │
│  LENDING BC     │                         │  CATALOG BC     │
│                 │                         │                 │
│  BorrowBook     │                         │  UpdatePopularity│
│  Handler        │                         │  EventHandler   │
└─────────────────┘                         └─────────────────┘
        │
        │  BookBorrowedEvent
        ▼
┌─────────────────┐
│  NOTIFICATION   │  (przyszły moduł)
│  BC             │
│                 │
│  SendEmail      │
│  EventHandler   │
└─────────────────┘
```

---

## Struktura eventu

### Interface

```php
namespace App\Shared\Domain\Event;

/**
 * Marker interface dla Domain Events.
 */
interface DomainEventInterface
{
    public function occurredAt(): DateTimeImmutable;
}
```

### Przykład: BookBorrowedEvent

```php
namespace App\Lending\Domain\Event;

use App\Shared\Domain\Event\DomainEventInterface;
use DateTimeImmutable;

/**
 * Event: Książka została wypożyczona.
 *
 * Nazwa w czasie przeszłym - opisuje fakt, który się wydarzył.
 * Immutable - readonly class, wszystkie dane w konstruktorze.
 */
final readonly class BookBorrowedEvent implements DomainEventInterface
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        private string $bookId,
        private string $userId,
        private string $loanId,
        ?DateTimeImmutable $occurredAt = null
    ) {
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable();
    }

    public function bookId(): string
    {
        return $this->bookId;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function loanId(): string
    {
        return $this->loanId;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
```

### Przykład: BookAddedToCatalogEvent

```php
namespace App\Catalog\Domain\Event;

final readonly class BookAddedToCatalogEvent implements DomainEventInterface
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        private string $bookId,
        private string $title,
        private string $isbn,
        private string $authorName,
        private string $publishedAt,
        ?DateTimeImmutable $occurredAt = null
    ) {
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable();
    }

    public function bookId(): string
    {
        return $this->bookId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function isbn(): string
    {
        return $this->isbn;
    }

    public function authorName(): string
    {
        return $this->authorName;
    }

    public function publishedAt(): string
    {
        return $this->publishedAt;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
```

### Konwencje nazewnictwa

| Dobrze | Źle |
|--------|-----|
| `BookBorrowedEvent` | `BorrowBookEvent` |
| `UserRegisteredEvent` | `RegisterUserEvent` |
| `OrderPlacedEvent` | `PlaceOrderEvent` |
| `PaymentReceivedEvent` | `ReceivePaymentEvent` |

---

## Publikacja eventów

### Port: EventPublisherInterface

```php
namespace App\Shared\Domain\Event;

/**
 * Port: Publikacja Domain Events.
 *
 * Domena nie wie jak eventy są dostarczane.
 */
interface EventPublisherInterface
{
    public function publish(DomainEventInterface $event): void;
}
```

### Adapter: MessengerEventPublisher

```php
namespace App\Shared\Infrastructure\Messenger;

use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Adapter: Publikuje eventy przez Symfony Messenger.
 */
final readonly class MessengerEventPublisher implements EventPublisherInterface
{
    public function __construct(
        private MessageBusInterface $eventBus
    ) {}

    public function publish(DomainEventInterface $event): void
    {
        $this->eventBus->dispatch($event);
    }
}
```

### Użycie w handlerze

```php
final readonly class BorrowBookCommandHandler
{
    public function __construct(
        private BookRepositoryInterface $bookRepository,
        private EventPublisherInterface $eventPublisher  // ← Port
    ) {}

    public function __invoke(BorrowBookCommand $command): void
    {
        $book = $this->bookRepository->findById(new BookId($command->bookId));

        $book->borrow();

        $this->bookRepository->save($book);

        // Publikuj event po zapisaniu
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

---

## Event Handlers

Event Handler **reaguje** na eventy z innych modułów.

### Konwencje

- Nazwa opisuje akcję: `CreateBookOnBookAddedToCatalog`
- Jedna publiczna metoda: `__invoke(Event $event)`
- Rejestracja w YAML (bez atrybutów Symfony)

### Przykład: CreateBookOnBookAddedToCatalog

```php
namespace App\Lending\Application\EventHandler;

use App\Catalog\Domain\Event\BookAddedToCatalogEvent;
use App\Lending\Domain\Entity\Book;
use App\Lending\Domain\Repository\BookRepositoryInterface;
use App\Lending\Domain\ValueObject\BookId;
use DateTimeImmutable;

/**
 * Event Handler: Tworzy Book w Lending gdy dodano do Catalog.
 *
 * Ten handler należy do modułu LENDING, ale reaguje na event z CATALOG.
 * Dzięki temu:
 * - Catalog nie wie, że Lending istnieje
 * - Lending ma swoją wersję Book z własnymi polami
 *
 * Handler zarejestrowany w services.yaml (bez atrybutów Symfony).
 */
final readonly class CreateBookOnBookAddedToCatalog
{
    public function __construct(
        private BookRepositoryInterface $bookRepository
    ) {}

    public function __invoke(BookAddedToCatalogEvent $event): void
    {
        // Sprawdź czy książka już istnieje (idempotentność)
        $existingBook = $this->bookRepository->findById(
            new BookId($event->bookId())
        );

        if ($existingBook) {
            return; // Już istnieje, nic nie rób
        }

        // Utwórz Book w kontekście Lending
        $book = new Book(
            new BookId($event->bookId()),
            $event->title(),
            $event->authorName(),
            $event->isbn(),
            new DateTimeImmutable($event->publishedAt())
        );

        $this->bookRepository->save($book);
    }
}
```

### Przykład: UpdateBookPopularityOnBookBorrowed

```php
namespace App\Catalog\Application\EventHandler;

use App\Lending\Domain\Event\BookBorrowedEvent;
use Psr\Log\LoggerInterface;

/**
 * Event Handler: Aktualizuje popularność książki gdy została wypożyczona.
 *
 * Ten handler należy do modułu CATALOG, ale reaguje na event z LENDING.
 * Pokazuje luźne powiązanie między modułami.
 */
final readonly class UpdateBookPopularityOnBookBorrowed
{
    public function __construct(
        private LoggerInterface $logger
        // W pełnej implementacji:
        // private CatalogBookRepositoryInterface $catalogBookRepository
    ) {}

    public function __invoke(BookBorrowedEvent $event): void
    {
        // Pełna implementacja:
        // $book = $this->catalogBookRepository->findById($event->bookId());
        // $book->incrementPopularity();
        // $this->catalogBookRepository->save($book);

        // Na razie tylko logujemy
        $this->logger->info('Book popularity updated', [
            'bookId' => $event->bookId(),
            'userId' => $event->userId(),
            'handler' => self::class,
        ]);
    }
}
```

---

## Komunikacja między BC

### Dwukierunkowa komunikacja

```
┌──────────────────────────────────────────────────────────────────┐
│                                                                  │
│  CATALOG                              LENDING                    │
│  ┌─────────────────┐                  ┌─────────────────┐       │
│  │ AddBookToCatalog│                  │ CreateBook      │       │
│  │ CommandHandler  │─────────────────►│ EventHandler    │       │
│  │                 │ BookAddedTo      │                 │       │
│  │                 │ CatalogEvent     │ (tworzy Book)   │       │
│  └─────────────────┘                  └─────────────────┘       │
│                                                                  │
│  ┌─────────────────┐                  ┌─────────────────┐       │
│  │ UpdatePopularity│                  │ BorrowBook      │       │
│  │ EventHandler    │◄─────────────────│ CommandHandler  │       │
│  │                 │ BookBorrowed     │                 │       │
│  │ (zwiększa       │ Event            │                 │       │
│  │  popularity)    │                  │                 │       │
│  └─────────────────┘                  └─────────────────┘       │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
```

### Przepływ: Dodanie książki

1. **Catalog**: `AddBookToCatalogCommand` → Handler tworzy `CatalogBook`
2. **Catalog**: Handler publikuje `BookAddedToCatalogEvent`
3. **Lending**: `CreateBookOnBookAddedToCatalog` odbiera event
4. **Lending**: Handler tworzy `Book` (wersja Lending)

### Przepływ: Wypożyczenie

1. **Lending**: `BorrowBookCommand` → Handler wypożycza książkę
2. **Lending**: Handler publikuje `BookBorrowedEvent`
3. **Catalog**: `UpdateBookPopularityOnBookBorrowed` odbiera event
4. **Catalog**: Handler zwiększa popularność `CatalogBook`

---

## Konfiguracja Messenger

### messenger.yaml

```yaml
framework:
    messenger:
        default_bus: command.bus

        buses:
            command.bus:
                middleware:
                    - doctrine_transaction

            event.bus:
                default_middleware:
                    allow_no_handlers: true  # Eventy mogą nie mieć handlerów
```

### services.yaml

```yaml
services:
    # Event Publisher
    App\Shared\Domain\Event\EventPublisherInterface:
        alias: App\Shared\Infrastructure\Messenger\MessengerEventPublisher

    App\Shared\Infrastructure\Messenger\MessengerEventPublisher:
        arguments:
            $eventBus: '@event.bus'

    # Event Handlers
    App\Lending\Application\EventHandler\CreateBookOnBookAddedToCatalog:
        tags:
            - { name: messenger.message_handler, bus: event.bus }

    App\Catalog\Application\EventHandler\UpdateBookPopularityOnBookBorrowed:
        tags:
            - { name: messenger.message_handler, bus: event.bus }
```

---

## Przykłady z projektu

### Eventy

| Event | BC | Opis | Słuchacze |
|-------|-----|------|-----------|
| `BookAddedToCatalogEvent` | Catalog | Nowa książka w katalogu | Lending: CreateBook |
| `BookBorrowedEvent` | Lending | Książka wypożyczona | Catalog: UpdatePopularity |

### Handlery

| Handler | BC | Reaguje na | Akcja |
|---------|-----|------------|-------|
| `CreateBookOnBookAddedToCatalog` | Lending | `BookAddedToCatalogEvent` | Tworzy Book |
| `UpdateBookPopularityOnBookBorrowed` | Catalog | `BookBorrowedEvent` | Zwiększa popularity |

### Możliwe rozszerzenia

```
BookBorrowedEvent
    ├── UpdatePopularity (Catalog)        ✅ Zaimplementowane
    ├── UpdateMemberHistory (Membership)  📋 TODO
    ├── SendNotification (Notification)   📋 TODO
    └── UpdateStatistics (Reporting)      📋 TODO

BookReturnedEvent
    ├── CalculateFine (Lending)           📋 TODO
    ├── UpdateMemberHistory (Membership)  📋 TODO
    └── SendThankYouEmail (Notification)  📋 TODO
```

---

## Dobre praktyki

### 1. Idempotentność

Handler powinien być bezpieczny przy wielokrotnym wywołaniu:

```php
public function __invoke(BookAddedToCatalogEvent $event): void
{
    // ✅ Sprawdź czy już przetworzone
    $existing = $this->bookRepository->findById(new BookId($event->bookId()));

    if ($existing) {
        return; // Już istnieje, nic nie rób
    }

    // Przetwarzaj...
}
```

### 2. Nie modyfikuj eventu

```php
// ❌ ŹLE - modyfikacja eventu
public function __invoke(BookBorrowedEvent $event): void
{
    $event->setProcessed(true);  // Event jest immutable!
}

// ✅ DOBRZE - tylko odczyt
public function __invoke(BookBorrowedEvent $event): void
{
    $bookId = $event->bookId();  // Tylko odczyt
}
```

### 3. Jeden handler = jedna odpowiedzialność

```php
// ❌ ŹLE - handler robi za dużo
class DoEverythingOnBookBorrowed
{
    public function __invoke(BookBorrowedEvent $event): void
    {
        $this->updatePopularity($event);
        $this->sendEmail($event);
        $this->updateStatistics($event);
    }
}

// ✅ DOBRZE - osobne handlery
class UpdatePopularityOnBookBorrowed { }
class SendEmailOnBookBorrowed { }
class UpdateStatisticsOnBookBorrowed { }
```

---

## Następne kroki

- [Commands i Handlers](commands-and-handlers.md) - Wzorzec Command/Handler
- [Porty i Adaptery](../architecture/ports-and-adapters.md) - Event Publisher jako port
- [Potencjalne ulepszenia](../improvements.md) - Eventy w Aggregate Root

[< Powrót do README](../../README.md)
