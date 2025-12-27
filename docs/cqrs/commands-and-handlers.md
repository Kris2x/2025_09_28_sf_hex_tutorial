# Commands i Handlers (CQRS)

[< Powrót do README](../../README.md)

## Spis treści
- [Czym jest CQRS?](#czym-jest-cqrs)
- [Command - czyste DTO](#command---czyste-dto)
- [CommandHandler - logika](#commandhandler---logika)
- [Query - odczyt danych](#query---odczyt-danych)
- [Command Bus](#command-bus)
- [Rejestracja Handlerów](#rejestracja-handlerów)
- [Przykłady z projektu](#przykłady-z-projektu)

---

## Czym jest CQRS?

**CQRS** (Command Query Responsibility Segregation) to wzorzec separujący operacje **zapisu** od operacji **odczytu**.

```
┌─────────────────────────────────────────────────────────────────┐
│                         APPLICATION                              │
├─────────────────────────────┬───────────────────────────────────┤
│                             │                                    │
│     COMMAND (Write)         │          QUERY (Read)             │
│                             │                                    │
│  ┌─────────────────────┐    │    ┌─────────────────────┐        │
│  │ BorrowBookCommand   │    │    │ GetAvailableBooksQ  │        │
│  │ (DTO)               │    │    │ (DTO + logika)      │        │
│  └──────────┬──────────┘    │    └──────────┬──────────┘        │
│             │               │               │                    │
│             ▼               │               ▼                    │
│  ┌─────────────────────┐    │    ┌─────────────────────┐        │
│  │ BorrowBookCommand   │    │    │      Wynik          │        │
│  │ Handler             │    │    │                     │        │
│  └──────────┬──────────┘    │    └─────────────────────┘        │
│             │               │                                    │
│             ▼               │                                    │
│  ┌─────────────────────┐    │                                    │
│  │  Domain + Events    │    │                                    │
│  └─────────────────────┘    │                                    │
│                             │                                    │
└─────────────────────────────┴───────────────────────────────────┘
```

### Podstawowe zasady

| Typ | Cel | Modyfikuje stan? | Zwraca dane? |
|-----|-----|------------------|--------------|
| **Command** | Zmiana stanu | ✅ TAK | ❌ NIE (void) |
| **Query** | Odczyt danych | ❌ NIE | ✅ TAK |

### Korzyści

1. **Separacja odpowiedzialności** - kod zapisu oddzielony od odczytu
2. **Optymalizacja** - różne modele dla zapisu i odczytu
3. **Skalowalność** - odczyty można skalować niezależnie
4. **Testowalność** - łatwiejsze testowanie pojedynczych operacji

---

## Command - czyste DTO

**Command** to **Data Transfer Object** - przenosi dane wejściowe do handlera.

### Konwencje nazewnictwa

- Tryb rozkazujący: `BorrowBook`, `ReturnBook`, `AddBookToCatalog`
- Sufiks `Command`: `BorrowBookCommand`, `ReturnBookCommand`
- Nazwa opisuje **intencję**, nie implementację

### Przykład: BorrowBookCommand

```php
namespace App\Lending\Application\Command;

/**
 * Command: Wypożyczenie książki.
 *
 * Czyste DTO - tylko dane, bez logiki.
 * Immutable - readonly class.
 */
final readonly class BorrowBookCommand
{
    public function __construct(
        public string $userId,
        public string $bookId
    ) {}
}
```

### Przykład: AddBookToCatalogCommand

```php
namespace App\Catalog\Application\Command;

/**
 * Command: Dodanie książki do katalogu.
 */
final readonly class AddBookToCatalogCommand
{
    public function __construct(
        public string $bookId,
        public string $title,
        public string $isbn,
        public string $authorId,
        public string $authorFirstName,
        public string $authorLastName,
        public string $publishedAt,
        public ?string $description = null
    ) {}
}
```

### Dobre praktyki

```php
// ✅ DOBRZE: Czyste DTO, public readonly properties
final readonly class CreateUserCommand
{
    public function __construct(
        public string $email,
        public string $name,
        public string $password
    ) {}
}

// ❌ ŹLE: Logika w Command
final readonly class CreateUserCommand
{
    public function __construct(
        public string $email,
        public string $name,
        public string $password
    ) {
        // ❌ Walidacja nie należy do Command!
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email');
        }
    }

    // ❌ Metody logiki nie należą do Command!
    public function execute(): void { }
}
```

---

## CommandHandler - logika

**Handler** zawiera logikę wykonania - orkiestruje przepływ, deleguje do domeny.

### Konwencje

- Nazwa: `{Command}Handler` → `BorrowBookCommandHandler`
- Jedna publiczna metoda: `__invoke(Command $command)`
- Zależności przez konstruktor (Dependency Injection)

### Przykład: BorrowBookCommandHandler

```php
namespace App\Lending\Application\Command;

/**
 * Handler: Obsługuje wypożyczenie książki.
 *
 * Orkiestruje przepływ - deleguje logikę biznesową do domeny.
 * Handler zarejestrowany w services.yaml (bez atrybutów Symfony).
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
        // 1. Pobierz encje przez porty (interfejsy)
        $user = $this->userRepository->findById(new UserId($command->userId));
        if (!$user) {
            throw new \DomainException('User not found');
        }

        $book = $this->bookRepository->findById(new BookId($command->bookId));
        if (!$book) {
            throw new \DomainException('Book not found');
        }

        // 2. Deleguj reguły biznesowe do DOMENY
        if (!$user->canBorrowBook()) {
            throw new \DomainException('User cannot borrow more books');
        }

        // 3. Wykonaj operacje domenowe
        $user->borrowBook();
        $book->borrow();

        $loan = new Loan(
            LoanId::generate(),
            $book->id(),
            $user->id(),
            new DateTimeImmutable(),
            new DateTimeImmutable('+14 days')
        );

        // 4. Zapisz zmiany przez porty
        $this->userRepository->save($user);
        $this->bookRepository->save($book);
        $this->loanRepository->save($loan);

        // 5. Opublikuj Domain Event
        $this->eventPublisher->publish(
            new BookBorrowedEvent(
                $book->id()->value(),
                $user->id()->value(),
                $loan->id()->value(),
                new DateTimeImmutable()
            )
        );
    }
}
```

### Co Handler ROBI vs NIE ROBI

| Handler ROBI | Handler NIE ROBI |
|--------------|------------------|
| Pobiera encje z repozytoriów | ❌ Nie zawiera logiki biznesowej |
| Wywołuje metody domenowe | ❌ Nie wie o HTTP, Doctrine |
| Zapisuje zmiany | ❌ Nie waliduje reguł (to domena!) |
| Publikuje eventy | ❌ Nie formatuje odpowiedzi |
| Koordynuje przepływ | ❌ Nie zwraca danych (void) |

### Przykład: AddBookToCatalogCommandHandler

```php
namespace App\Catalog\Application\Command;

final readonly class AddBookToCatalogCommandHandler
{
    public function __construct(
        private CatalogBookRepositoryInterface $bookRepository,
        private AuthorRepositoryInterface $authorRepository,
        private EventPublisherInterface $eventPublisher
    ) {}

    public function __invoke(AddBookToCatalogCommand $command): void
    {
        // Znajdź lub utwórz autora
        $author = $this->authorRepository->findById(new AuthorId($command->authorId));

        if (!$author) {
            $author = new Author(
                new AuthorId($command->authorId),
                $command->authorFirstName,
                $command->authorLastName
            );
            $this->authorRepository->save($author);
        }

        // Utwórz książkę w katalogu
        $catalogBook = new CatalogBook(
            new CatalogBookId($command->bookId),
            $command->title,
            new Isbn($command->isbn),
            $author,
            new DateTimeImmutable($command->publishedAt),
            $command->description
        );

        $this->bookRepository->save($catalogBook);

        // Opublikuj event → Lending BC utworzy swoją wersję Book
        $this->eventPublisher->publish(
            new BookAddedToCatalogEvent(
                $catalogBook->id()->value(),
                $catalogBook->title(),
                $catalogBook->isbn()->value(),
                $author->firstName() . ' ' . $author->lastName(),
                $command->publishedAt
            )
        );
    }
}
```

---

## Query - odczyt danych

W tym projekcie stosujemy **pragmatyczne podejście**: Query = DTO + logika razem.

### Dlaczego nie QueryHandler?

- Odczyty są prostsze niż zapisy
- Mniej boilerplate'u
- Rozdzielenie tylko tam, gdzie ma sens
- Dla złożonych systemów można dodać osobne handlery

### Przykład: GetAvailableBooksQuery

```php
namespace App\Lending\Application\Query;

/**
 * Query: Pobranie dostępnych książek.
 *
 * TYLKO ODCZYTUJE dane - NIE modyfikuje stanu!
 * Pragmatycznie: DTO i logika razem w jednej klasie.
 */
final readonly class GetAvailableBooksQuery
{
    public function __construct(
        private BookRepositoryInterface $bookRepository
    ) {}

    /** @return Book[] */
    public function execute(): array
    {
        return $this->bookRepository->findAvailable();
    }
}
```

### Przykład: SearchCatalogBooksQuery

```php
namespace App\Catalog\Application\Query;

final readonly class SearchCatalogBooksQuery
{
    public function __construct(
        private CatalogBookRepositoryInterface $bookRepository
    ) {}

    /** @return CatalogBook[] */
    public function execute(?string $search = null, ?string $category = null): array
    {
        if ($search) {
            return $this->bookRepository->search($search);
        }

        if ($category) {
            return $this->bookRepository->findByCategory($category);
        }

        return $this->bookRepository->findMostPopular(20);
    }
}
```

### Użycie w kontrolerze

```php
#[Route('/api/books', methods: ['GET'])]
public function listAvailable(GetAvailableBooksQuery $query): JsonResponse
{
    $books = $query->execute();

    return $this->json(
        array_map(fn(Book $book) => [
            'id' => $book->id()->value(),
            'title' => $book->title(),
            'available' => $book->isAvailable(),
        ], $books)
    );
}
```

---

## Command Bus

**Command Bus** to pośrednik między kontrolerem a handlerem.

### Korzyści

1. **Decoupling** - kontroler nie zna handlera
2. **Middleware** - można dodać logging, transakcje, etc.
3. **Async** - łatwo przejść na async processing

### Port: CommandBusInterface

```php
namespace App\Shared\Application\Bus;

/**
 * Port: Command Bus.
 *
 * Abstrakcja nad Symfony Messenger - Application Layer
 * nie wie o szczegółach implementacji.
 */
interface CommandBusInterface
{
    public function dispatch(object $command): mixed;
}
```

### Adapter: MessengerCommandBus

```php
namespace App\Shared\Infrastructure\Messenger;

use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Adapter: Implementuje Command Bus używając Symfony Messenger.
 */
final readonly class MessengerCommandBus implements CommandBusInterface
{
    public function __construct(
        private MessageBusInterface $commandBus
    ) {}

    public function dispatch(object $command): mixed
    {
        $envelope = $this->commandBus->dispatch($command);

        $handledStamp = $envelope->last(HandledStamp::class);

        return $handledStamp?->getResult();
    }
}
```

### Użycie w kontrolerze

```php
final class BookController extends AbstractController
{
    public function __construct(
        private CommandBusInterface $commandBus  // ← Interfejs, nie Messenger!
    ) {}

    #[Route('/{bookId}/borrow', methods: ['POST'])]
    public function borrowBook(string $bookId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $command = new BorrowBookCommand($data['userId'], $bookId);

        try {
            $this->commandBus->dispatch($command);
            return $this->json(['message' => 'Book borrowed']);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
```

---

## Rejestracja Handlerów

Handlery są rejestrowane w `services.yaml` bez atrybutów Symfony.

### Dlaczego YAML zamiast atrybutów?

| Podejście | Zalety | Wady |
|-----------|--------|------|
| `#[AsMessageHandler]` | Mniej konfiguracji | Zależność od Symfony w Application |
| YAML | Czysta Application Layer | Więcej konfiguracji |

### Konfiguracja Messenger

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        default_bus: command.bus

        buses:
            command.bus:
                middleware:
                    - doctrine_transaction  # Automatyczne transakcje

            event.bus:
                default_middleware:
                    allow_no_handlers: true  # Eventy mogą nie mieć handlerów
```

### Rejestracja handlerów

```yaml
# config/services.yaml
services:
    # === COMMAND HANDLERS ===

    App\Catalog\Application\Command\AddBookToCatalogCommandHandler:
        tags:
            - { name: messenger.message_handler, bus: command.bus }

    App\Lending\Application\Command\BorrowBookCommandHandler:
        tags:
            - { name: messenger.message_handler, bus: command.bus }

    App\Lending\Application\Command\ReturnBookCommandHandler:
        tags:
            - { name: messenger.message_handler, bus: command.bus }

    # === EVENT HANDLERS ===

    App\Lending\Application\EventHandler\CreateBookOnBookAddedToCatalog:
        tags:
            - { name: messenger.message_handler, bus: event.bus }

    App\Catalog\Application\EventHandler\UpdateBookPopularityOnBookBorrowed:
        tags:
            - { name: messenger.message_handler, bus: event.bus }
```

---

## Przykłady z projektu

### Lending BC - Commands

| Command | Handler | Opis |
|---------|---------|------|
| `BorrowBookCommand` | `BorrowBookCommandHandler` | Wypożyczenie książki |
| `ReturnBookCommand` | `ReturnBookCommandHandler` | Zwrot książki |

### Catalog BC - Commands

| Command | Handler | Opis |
|---------|---------|------|
| `AddBookToCatalogCommand` | `AddBookToCatalogCommandHandler` | Dodanie do katalogu |

### Queries

| Query | BC | Opis |
|-------|-----|------|
| `GetAvailableBooksQuery` | Lending | Lista dostępnych książek |
| `GetUserLoansQuery` | Lending | Wypożyczenia użytkownika |
| `SearchCatalogBooksQuery` | Catalog | Wyszukiwanie w katalogu |
| `GetCategoriesQuery` | Catalog | Lista kategorii |

---

## Następne kroki

- [Domain Events](events.md) - Komunikacja między kontekstami
- [Porty i Adaptery](../architecture/ports-and-adapters.md) - Dependency Injection
- [Testowanie](../testing.md) - Jak testować Commands/Handlers

[< Powrót do README](../../README.md)
