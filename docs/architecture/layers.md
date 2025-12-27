# Warstwy aplikacji

[< Powrót do README](../../README.md)

## Spis treści
- [Przegląd warstw](#przegląd-warstw)
- [Domain Layer](#domain-layer)
- [Application Layer](#application-layer)
- [Infrastructure Layer](#infrastructure-layer)
- [Presentation Layer](#presentation-layer)
- [Przepływ danych](#przepływ-danych)

---

## Przegląd warstw

```
┌─────────────────────────────────────────────────────────────────┐
│                     PRESENTATION LAYER                          │
│  Controllers, CLI Commands, GraphQL Resolvers                   │
│  "Jak świat zewnętrzny komunikuje się z aplikacją"             │
└─────────────────────────────┬───────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                     APPLICATION LAYER                            │
│  Commands, Handlers, Queries, Event Handlers                     │
│  "Co aplikacja umie robić (use cases)"                          │
└─────────────────────────────┬───────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                       DOMAIN LAYER                               │
│  Entities, Value Objects, Domain Services, Repository Interfaces│
│  "Serce aplikacji - czysta logika biznesowa"                    │
└─────────────────────────────▲───────────────────────────────────┘
                              │ implementuje
┌─────────────────────────────┴───────────────────────────────────┐
│                   INFRASTRUCTURE LAYER                           │
│  Doctrine Repositories, Messenger, External APIs, Email         │
│  "Szczegóły techniczne - jak rzeczy są zrobione"               │
└─────────────────────────────────────────────────────────────────┘
```

### Zasady

| Zasada | Opis |
|--------|------|
| **Dependency Rule** | Zależności wskazują do środka (ku domenie) |
| **Isolation** | Domena nie wie o zewnętrznym świecie |
| **Inversion** | Infrastruktura zależy od domeny, nie odwrotnie |

---

## Domain Layer

**Lokalizacja:** `src/{BoundedContext}/Domain/`

**Zasada:** Domena nie wie, że istnieje Symfony, Doctrine, HTTP, czy baza danych.

### Struktura

```
Domain/
├── Entity/                 # Encje domenowe (Aggregates)
│   ├── Book.php
│   ├── User.php
│   └── Loan.php
│
├── ValueObject/            # Value Objects (niezmienne)
│   ├── BookId.php
│   ├── UserId.php
│   ├── Email.php
│   └── Isbn.php
│
├── Event/                  # Domain Events
│   └── BookBorrowedEvent.php
│
├── Repository/             # Repository Interfaces (Porty)
│   ├── BookRepositoryInterface.php
│   └── UserRepositoryInterface.php
│
└── Service/                # Domain Services (opcjonalnie)
    └── LoanPolicyService.php
```

### Encje domenowe

Encja to obiekt z **tożsamością** (ID) i **zachowaniami biznesowymi**.

```php
namespace App\Lending\Domain\Entity;

class Book
{
    // ✅ Stan prywatny - brak setterów!
    private bool $isAvailable = true;

    public function __construct(
        private BookId $id,
        private string $title,
        private string $author,
        private string $isbn,
        private DateTimeImmutable $publishedAt
    ) {}

    // ✅ Zachowania biznesowe - metody, które ROBIĄ coś sensownego
    public function borrow(): void
    {
        // ✅ Reguła biznesowa w encji, nie w serwisie!
        if (!$this->isAvailable) {
            throw new \DomainException('Book is not available for borrowing');
        }
        $this->isAvailable = false;
    }

    public function return(): void
    {
        if ($this->isAvailable) {
            throw new \DomainException('Book is already available');
        }
        $this->isAvailable = true;
    }

    // ✅ Gettery zwracają stan, ale nie ma setterów
    public function id(): BookId
    {
        return $this->id;
    }

    public function isAvailable(): bool
    {
        return $this->isAvailable;
    }
}
```

#### Rich vs Anemic Domain Model

```php
// ❌ Anemic Domain Model - encja to głupi kontener
$book->setStatus('borrowed');  // Każdy może zmienić na cokolwiek
$book->setAvailable(false);    // Brak walidacji

// ✅ Rich Domain Model - encja chroni swój stan
$book->borrow();  // Encja waliduje i zmienia stan atomowo
```

### Value Objects

Value Object to obiekt **bez tożsamości**, porównywany przez **wartość**, **niezmienny** (immutable).

```php
namespace App\Lending\Domain\ValueObject;

final readonly class Email
{
    public function __construct(private string $value)
    {
        // ✅ Walidacja w konstruktorze
        if (!filter_var($this->value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException(
                sprintf('Invalid email format: %s', $this->value)
            );
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    // ✅ Porównywanie przez wartość
    public function equals(Email $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

#### Dlaczego Value Objects?

```php
// ❌ Primitive Obsession - string może być czymkolwiek
function sendEmail(string $email): void { }
sendEmail('not-an-email');  // Kompiluje się, wysadzi runtime

// ✅ Type Safety - niemożliwe stworzyć nieprawidłowy email
function sendEmail(Email $email): void { }
sendEmail(new Email('not-an-email'));  // Wyjątek od razu
```

#### Przykład: BookId

```php
final readonly class BookId
{
    public function __construct(private string $value)
    {
        if (empty($value)) {
            throw new InvalidArgumentException('BookId cannot be empty');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(BookId $other): bool
    {
        return $this->value === $other->value;
    }
}
```

### Repository Interfaces (Porty)

Interfejs definiuje **CO** potrzebuję, nie **JAK** to zrobić.

```php
namespace App\Lending\Domain\Repository;

interface BookRepositoryInterface
{
    public function save(Book $book): void;
    public function findById(BookId $id): ?Book;
    public function findAvailable(): array;
    public function remove(Book $book): void;
}
```

**Zauważ:**
- Interfejs jest w **Domain**, nie w Infrastructure
- Używa **domenowych typów** (BookId, Book), nie prymitywów
- Brak wzmianki o Doctrine, SQL, czy bazie danych

---

## Application Layer

**Lokalizacja:** `src/{BoundedContext}/Application/`

**Zasada:** Orkiestruje use cases, deleguje logikę do domeny.

### Struktura

```
Application/
├── Command/                # Commands (modyfikują stan)
│   ├── BorrowBookCommand.php           # DTO
│   ├── BorrowBookCommandHandler.php    # Logika
│   ├── ReturnBookCommand.php           # DTO
│   └── ReturnBookCommandHandler.php    # Logika
│
├── Query/                  # Queries (tylko odczyt)
│   ├── GetAvailableBooksQuery.php
│   └── GetUserLoansQuery.php
│
└── EventHandler/           # Reaguje na eventy z innych BC
    └── CreateBookOnBookAddedToCatalog.php
```

### Command (DTO)

Command to **czyste DTO** - tylko dane wejściowe, zero logiki.

```php
namespace App\Lending\Application\Command;

/**
 * Command: Wypożyczenie książki.
 *
 * Czyste DTO - tylko dane, bez logiki.
 * Nazwa w trybie rozkazującym (Borrow, Return, Create).
 */
final readonly class BorrowBookCommand
{
    public function __construct(
        public string $userId,
        public string $bookId
    ) {}
}
```

### CommandHandler

Handler zawiera **logikę orkiestracji** - koordynuje przepływ, deleguje do domeny.

```php
namespace App\Lending\Application\Command;

/**
 * Handler: Obsługuje wypożyczenie książki.
 *
 * Orkiestruje przepływ - deleguje logikę biznesową do domeny.
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
        // 1. Pobierz encje
        $user = $this->userRepository->findById(new UserId($command->userId));
        if (!$user) {
            throw new \DomainException('User not found');
        }

        $book = $this->bookRepository->findById(new BookId($command->bookId));
        if (!$book) {
            throw new \DomainException('Book not found');
        }

        // 2. Deleguj logikę do DOMENY
        if (!$user->canBorrowBook()) {
            throw new \DomainException('User has reached maximum loan limit');
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

        // 4. Zapisz zmiany
        $this->userRepository->save($user);
        $this->bookRepository->save($book);
        $this->loanRepository->save($loan);

        // 5. Opublikuj event
        $this->eventPublisher->publish(
            new BookBorrowedEvent(
                $book->id()->value(),
                $user->id()->value(),
                $loan->id()->value()
            )
        );
    }
}
```

#### Co Handler ROBI vs NIE ROBI

| Handler ROBI | Handler NIE ROBI |
|--------------|------------------|
| Pobiera encje z repozytoriów | Nie zawiera logiki biznesowej |
| Wywołuje metody na encjach | Nie wie o HTTP, Doctrine |
| Zapisuje zmiany | Nie waliduje reguł biznesowych |
| Publikuje eventy | Nie formatuje odpowiedzi |

### Query

Query **tylko odczytuje** dane. Pragmatyczne podejście: DTO + logika razem.

```php
namespace App\Lending\Application\Query;

/**
 * Query: Pobranie dostępnych książek.
 *
 * TYLKO ODCZYTUJE dane - NIE modyfikuje stanu!
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

**Dlaczego Query nie ma osobnego Handlera?**
- Odczyty są prostsze niż zapisy
- Mniej boilerplate'u
- Rozdzielenie tylko tam, gdzie ma sens

---

## Infrastructure Layer

**Lokalizacja:** `src/{BoundedContext}/Infrastructure/`

**Zasada:** Implementuje interfejsy z domeny. Zawiera szczegóły techniczne.

### Struktura

```
Infrastructure/
├── Doctrine/
│   ├── Repository/         # Implementacje repozytoriów
│   │   ├── DoctrineBookRepository.php
│   │   └── DoctrineUserRepository.php
│   │
│   └── Type/               # Custom Doctrine Types
│       ├── BookIdType.php
│       └── EmailType.php
│
├── ContractAdapter/        # Adaptery kontraktów z Shared
│   └── CatalogBookInfoProvider.php
│
└── External/               # Zewnętrzne API (opcjonalnie)
    └── GoogleBooksClient.php
```

### Doctrine Repository (Adapter)

```php
namespace App\Lending\Infrastructure\Doctrine\Repository;

final class DoctrineBookRepository implements BookRepositoryInterface
{
    private ObjectRepository $repository;

    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        $this->repository = $entityManager->getRepository(Book::class);
    }

    public function findById(BookId $id): ?Book
    {
        // ✅ Szczegóły Doctrine są TUTAJ, nie w domenie
        return $this->repository->find($id->value());
    }

    public function save(Book $book): void
    {
        $this->entityManager->persist($book);
        $this->entityManager->flush();
    }

    public function findAvailable(): array
    {
        return $this->repository->findBy(['isAvailable' => true]);
    }

    public function remove(Book $book): void
    {
        $this->entityManager->remove($book);
        $this->entityManager->flush();
    }
}
```

### Custom Doctrine Type

Mapowanie Value Objects na kolumny bazy danych.

```php
namespace App\Lending\Infrastructure\Doctrine\Type;

use App\Lending\Domain\ValueObject\BookId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;

class BookIdType extends StringType
{
    public const NAME = 'book_id';

    public function convertToPHPValue($value, AbstractPlatform $platform): ?BookId
    {
        return $value !== null ? new BookId($value) : null;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        return $value instanceof BookId ? $value->value() : $value;
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
```

### Wymienność implementacji

```php
// Dzisiaj: PostgreSQL
class DoctrineBookRepository implements BookRepositoryInterface { }

// Jutro: Redis cache
class RedisBookRepository implements BookRepositoryInterface
{
    public function findById(BookId $id): ?Book
    {
        $data = $this->redis->get("book:{$id->value()}");
        return $data ? Book::fromArray(json_decode($data, true)) : null;
    }
}

// Tylko zmiana w services.yaml - domena bez zmian!
```

---

## Presentation Layer

**Lokalizacja:** `src/{BoundedContext}/Presentation/`

**Zasada:** "Tłumacz" między HTTP/CLI a Application Layer.

### Struktura

```
Presentation/
├── Controller/
│   └── BookController.php      # REST API
│
└── CLI/                        # Opcjonalnie
    └── ImportBooksCommand.php
```

### REST Controller

```php
namespace App\Lending\Presentation\Controller;

#[Route('/api/books')]
final class BookController extends AbstractController
{
    public function __construct(
        private CommandBusInterface $commandBus
    ) {}

    #[Route('/{bookId}/borrow', methods: ['POST'])]
    public function borrowBook(string $bookId, Request $request): JsonResponse
    {
        // 1. Wyciągnij dane z HTTP
        $data = json_decode($request->getContent(), true);
        $userId = $data['userId'] ?? null;

        if (!$userId) {
            return $this->json(['error' => 'userId is required'], 400);
        }

        // 2. Stwórz Command i wyślij przez bus
        try {
            $command = new BorrowBookCommand($userId, $bookId);
            $this->commandBus->dispatch($command);

            return $this->json(['message' => 'Book borrowed successfully']);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/', methods: ['GET'])]
    public function listAvailable(GetAvailableBooksQuery $query): JsonResponse
    {
        $books = $query->execute();

        return $this->json(
            array_map(fn(Book $book) => [
                'id' => $book->id()->value(),
                'title' => $book->title(),
                'author' => $book->author(),
                'available' => $book->isAvailable(),
            ], $books)
        );
    }
}
```

#### Kontroler NIE:
- Nie zawiera logiki biznesowej
- Nie operuje bezpośrednio na encjach
- Nie wywołuje repozytoriów

---

## Przepływ danych

### Sekwencja: Wypożyczenie książki

```
1. HTTP Request
   POST /api/books/book-1/borrow
   Body: {"userId": "user-1"}
            │
            ▼
2. BookController (Presentation)
   - Parsuje JSON
   - Tworzy BorrowBookCommand
   - Wysyła przez CommandBus
            │
            ▼
3. BorrowBookCommandHandler (Application)
   - Pobiera User i Book przez interfejsy
   - Wywołuje: user.canBorrowBook()
   - Wywołuje: book.borrow()
   - Tworzy Loan
   - Zapisuje przez interfejsy
   - Publikuje BookBorrowedEvent
            │
            ▼
4. DoctrineRepositories (Infrastructure)
   - persist() + flush()
            │
            ▼
5. PostgreSQL
   - INSERT INTO loans ...
   - UPDATE books SET is_available = false
            │
            ▼
6. Response
   {"message": "Book borrowed successfully"}
```

### Diagram sekwencji

```
Controller    CommandBus    Handler         Domain          Repository
    │             │            │               │                │
    │──dispatch()─►│           │               │                │
    │             │──__invoke()─►              │                │
    │             │            │──findById()────►               │
    │             │            │               │◄───────────────│
    │             │            │               │                │
    │             │            │──borrow()─────►               │
    │             │            │◄──────────────│                │
    │             │            │               │                │
    │             │            │──save()───────►               │
    │             │            │               │────────────────►
    │◄────────────│◄───────────│               │                │
```

---

## Następne kroki

- [Porty i Adaptery](ports-and-adapters.md) - Szczegóły implementacji
- [Commands i Handlers](../cqrs/commands-and-handlers.md) - Wzorzec CQRS
- [Domain Events](../cqrs/events.md) - Komunikacja eventowa

[< Powrót do README](../../README.md)
