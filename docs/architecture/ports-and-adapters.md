# Porty i Adaptery

[< Powrót do README](../../README.md)

## Spis treści
- [Koncepcja](#koncepcja)
- [Porty wejściowe (Driving)](#porty-wejściowe-driving)
- [Porty wyjściowe (Driven)](#porty-wyjściowe-driven)
- [Dependency Injection](#dependency-injection)
- [Testowanie z różnymi adapterami](#testowanie-z-różnymi-adapterami)
- [Przykłady z projektu](#przykłady-z-projektu)

---

## Koncepcja

**Port** = interfejs definiujący kontrakt
**Adapter** = implementacja realizująca kontrakt

```
┌─────────────────────────────────────────────────────────────────┐
│                                                                 │
│  ┌──────────────┐                         ┌──────────────┐     │
│  │   HTTP       │                         │   Doctrine   │     │
│  │  Controller  │                         │  Repository  │     │
│  │  (Adapter)   │                         │  (Adapter)   │     │
│  └──────┬───────┘                         └───────┬──────┘     │
│         │                                         │             │
│         │ używa                        implementuje             │
│         ▼                                         ▼             │
│  ┌──────────────┐                         ┌──────────────┐     │
│  │  Application │                         │  Repository  │     │
│  │    Layer     │─────── używa ──────────►│  Interface   │     │
│  │  (Use Cases) │                         │    (Port)    │     │
│  └──────┬───────┘                         └──────────────┘     │
│         │                                                       │
│         ▼                                                       │
│  ┌──────────────┐                                               │
│  │    Domain    │                                               │
│  │   Entities   │                                               │
│  └──────────────┘                                               │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Kluczowa zasada

```
PORTY (Interfejsy):              ADAPTERY (Implementacje):
─────────────────────            ─────────────────────────
BookRepositoryInterface    ◄───  DoctrineBookRepository
                           ◄───  InMemoryBookRepository
                           ◄───  RedisBookRepository

Strzałka ◄─── oznacza "implementuje" / "zależy od"
Adapter ZALEŻY OD interfejsu (Port), nie odwrotnie!
```

---

## Porty wejściowe (Driving)

Porty wejściowe definiują **jak świat zewnętrzny używa aplikacji**.

### Rodzaje adapterów wejściowych

| Adapter | Opis | Lokalizacja |
|---------|------|-------------|
| **REST Controller** | HTTP API | `Presentation/Controller/` |
| **CLI Command** | Linia poleceń | `Presentation/CLI/` |
| **GraphQL Resolver** | GraphQL API | `Presentation/GraphQL/` |
| **Message Consumer** | Kolejki | `Infrastructure/Messenger/` |
| **Scheduler** | Cron jobs | `Infrastructure/Scheduler/` |

### Przykład: REST Controller jako adapter

```php
namespace App\Lending\Presentation\Controller;

#[Route('/api/books')]
final class BookController extends AbstractController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private GetAvailableBooksQuery $query
    ) {}

    /**
     * Adapter: HTTP → Application Layer
     *
     * Tłumaczy HTTP Request na Command i wywołuje Handler.
     */
    #[Route('/{bookId}/borrow', methods: ['POST'])]
    public function borrowBook(string $bookId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $command = new BorrowBookCommand(
            userId: $data['userId'],
            bookId: $bookId
        );

        try {
            $this->commandBus->dispatch($command);
            return $this->json(['message' => 'Book borrowed']);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
```

### Przykład: CLI Command jako adapter

```php
namespace App\Lending\Presentation\CLI;

#[AsCommand(name: 'app:borrow-book')]
final class BorrowBookCliCommand extends Command
{
    public function __construct(
        private CommandBusInterface $commandBus
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $command = new BorrowBookCommand(
            userId: $input->getArgument('userId'),
            bookId: $input->getArgument('bookId')
        );

        try {
            $this->commandBus->dispatch($command);
            $output->writeln('<info>Book borrowed successfully</info>');
            return Command::SUCCESS;
        } catch (\DomainException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }
}
```

### Ta sama logika, różne wejścia

```
HTTP POST /api/books/1/borrow     ──► BorrowBookCommand ──► Handler
CLI: app:borrow-book --user=1 --book=1 ──► BorrowBookCommand ──► Handler
Message Queue: borrow_book_message    ──► BorrowBookCommand ──► Handler
```

Logika biznesowa jest **jedna** - różne są tylko adaptery wejściowe.

---

## Porty wyjściowe (Driven)

Porty wyjściowe definiują **jak aplikacja używa świata zewnętrznego**.

### Rodzaje portów wyjściowych

| Port | Opis | Przykład adaptera |
|------|------|-------------------|
| **RepositoryInterface** | Persistencja | DoctrineRepository, RedisRepository |
| **EventPublisherInterface** | Publikacja eventów | MessengerEventPublisher |
| **MailerInterface** | Wysyłka email | SymfonyMailer, SendGridMailer |
| **PaymentGatewayInterface** | Płatności | StripeGateway, PayPalGateway |
| **NotificationInterface** | Powiadomienia | SlackNotifier, SMSNotifier |

### Przykład: Repository Interface (Port)

```php
namespace App\Lending\Domain\Repository;

/**
 * Port: Definiuje CO potrzebujemy od persistencji.
 *
 * Interfejs jest w Domain - nie wie o Doctrine, SQL, Redis.
 * Używa typów domenowych (BookId, Book), nie prymitywów.
 */
interface BookRepositoryInterface
{
    public function save(Book $book): void;
    public function findById(BookId $id): ?Book;
    public function findAvailable(): array;
    public function findByIsbn(string $isbn): ?Book;
    public function remove(Book $book): void;
}
```

### Przykład: Doctrine Adapter

```php
namespace App\Lending\Infrastructure\Doctrine\Repository;

/**
 * Adapter: Implementuje port używając Doctrine ORM.
 */
final class DoctrineBookRepository implements BookRepositoryInterface
{
    private ObjectRepository $repository;

    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        $this->repository = $entityManager->getRepository(Book::class);
    }

    public function save(Book $book): void
    {
        $this->entityManager->persist($book);
        $this->entityManager->flush();
    }

    public function findById(BookId $id): ?Book
    {
        return $this->repository->find($id->value());
    }

    public function findAvailable(): array
    {
        return $this->repository->findBy(['isAvailable' => true]);
    }

    public function findByIsbn(string $isbn): ?Book
    {
        return $this->repository->findOneBy(['isbn' => $isbn]);
    }

    public function remove(Book $book): void
    {
        $this->entityManager->remove($book);
        $this->entityManager->flush();
    }
}
```

### Przykład: Event Publisher Interface (Port)

```php
namespace App\Shared\Domain\Event;

/**
 * Port: Publikacja domain events.
 */
interface EventPublisherInterface
{
    public function publish(DomainEventInterface $event): void;
}
```

### Przykład: Messenger Adapter

```php
namespace App\Shared\Infrastructure\Messenger;

/**
 * Adapter: Implementuje port używając Symfony Messenger.
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

---

## Dependency Injection

Symfony automatycznie "skleja" porty z adapterami.

### Konfiguracja w services.yaml

```yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true

    App\:
        resource: '../src/'

    # ═══════════════════════════════════════════════════════════
    # LENDING BC - Port → Adapter bindings
    # ═══════════════════════════════════════════════════════════

    # "Gdy ktoś poprosi o BookRepositoryInterface,
    #  daj mu DoctrineBookRepository"

    App\Lending\Domain\Repository\BookRepositoryInterface:
        alias: App\Lending\Infrastructure\Doctrine\Repository\DoctrineBookRepository

    App\Lending\Domain\Repository\UserRepositoryInterface:
        alias: App\Lending\Infrastructure\Doctrine\Repository\DoctrineUserRepository

    App\Lending\Domain\Repository\LoanRepositoryInterface:
        alias: App\Lending\Infrastructure\Doctrine\Repository\DoctrineLoanRepository

    # ═══════════════════════════════════════════════════════════
    # SHARED - Event Publisher
    # ═══════════════════════════════════════════════════════════

    App\Shared\Domain\Event\EventPublisherInterface:
        alias: App\Shared\Infrastructure\Messenger\MessengerEventPublisher

    # ═══════════════════════════════════════════════════════════
    # SHARED - Command Bus
    # ═══════════════════════════════════════════════════════════

    App\Shared\Application\Bus\CommandBusInterface:
        alias: App\Shared\Infrastructure\Messenger\MessengerCommandBus
```

### Jak to działa?

```php
// Symfony widzi tę sygnaturę w konstruktorze:
class BorrowBookCommandHandler
{
    public function __construct(
        private BookRepositoryInterface $bookRepository  // ← Interfejs
    ) {}
}

// I automatycznie wstrzykuje implementację:
new BorrowBookCommandHandler(
    new DoctrineBookRepository($entityManager)  // ← Implementacja
);
```

---

## Testowanie z różnymi adapterami

### In-Memory Repository dla testów

```php
namespace App\Lending\Infrastructure\InMemory;

/**
 * Adapter testowy: Przechowuje dane w pamięci.
 */
final class InMemoryBookRepository implements BookRepositoryInterface
{
    /** @var array<string, Book> */
    private array $books = [];

    public function save(Book $book): void
    {
        $this->books[$book->id()->value()] = $book;
    }

    public function findById(BookId $id): ?Book
    {
        return $this->books[$id->value()] ?? null;
    }

    public function findAvailable(): array
    {
        return array_filter(
            $this->books,
            fn(Book $book) => $book->isAvailable()
        );
    }

    public function findByIsbn(string $isbn): ?Book
    {
        foreach ($this->books as $book) {
            if ($book->isbn() === $isbn) {
                return $book;
            }
        }
        return null;
    }

    public function remove(Book $book): void
    {
        unset($this->books[$book->id()->value()]);
    }

    // Helper do testów
    public function clear(): void
    {
        $this->books = [];
    }
}
```

### Konfiguracja testowa

```yaml
# config/services_test.yaml
services:
    # W testach używamy implementacji in-memory
    App\Lending\Domain\Repository\BookRepositoryInterface:
        alias: App\Lending\Infrastructure\InMemory\InMemoryBookRepository

    App\Lending\Infrastructure\InMemory\InMemoryBookRepository:
        public: true  # Dostępne w testach
```

### Test z in-memory repository

```php
class BorrowBookCommandHandlerTest extends TestCase
{
    private InMemoryBookRepository $bookRepository;
    private InMemoryUserRepository $userRepository;
    private BorrowBookCommandHandler $handler;

    protected function setUp(): void
    {
        $this->bookRepository = new InMemoryBookRepository();
        $this->userRepository = new InMemoryUserRepository();

        $this->handler = new BorrowBookCommandHandler(
            $this->bookRepository,
            $this->userRepository,
            new InMemoryLoanRepository(),
            new NullEventPublisher()  // Nie publikuje eventów
        );
    }

    public function testBorrowAvailableBook(): void
    {
        // Arrange
        $book = new Book(new BookId('book-1'), 'Title', 'Author', 'ISBN', new DateTimeImmutable());
        $user = new User(new UserId('user-1'), 'John', new Email('john@test.pl'), new DateTimeImmutable());

        $this->bookRepository->save($book);
        $this->userRepository->save($user);

        // Act
        $command = new BorrowBookCommand('user-1', 'book-1');
        ($this->handler)($command);

        // Assert
        $this->assertFalse($book->isAvailable());
    }
}
```

---

## Przykłady z projektu

### Wszystkie porty w projekcie

```
LENDING BC:
┌─────────────────────────────────┐     ┌─────────────────────────────────┐
│ BookRepositoryInterface         │ ◄── │ DoctrineBookRepository          │
│ UserRepositoryInterface         │ ◄── │ DoctrineUserRepository          │
│ LoanRepositoryInterface         │ ◄── │ DoctrineLoanRepository          │
└─────────────────────────────────┘     └─────────────────────────────────┘

CATALOG BC:
┌─────────────────────────────────┐     ┌─────────────────────────────────┐
│ CatalogBookRepositoryInterface  │ ◄── │ DoctrineCatalogBookRepository   │
│ AuthorRepositoryInterface       │ ◄── │ DoctrineAuthorRepository        │
│ CategoryRepositoryInterface     │ ◄── │ DoctrineCategoryRepository      │
└─────────────────────────────────┘     └─────────────────────────────────┘

SHARED:
┌─────────────────────────────────┐     ┌─────────────────────────────────┐
│ EventPublisherInterface         │ ◄── │ MessengerEventPublisher         │
│ CommandBusInterface             │ ◄── │ MessengerCommandBus             │
└─────────────────────────────────┘     └─────────────────────────────────┘

CONTRACTS (między BC):
┌─────────────────────────────────┐     ┌─────────────────────────────────┐
│ BookInfoProviderInterface       │ ◄── │ CatalogBookInfoProvider         │
└─────────────────────────────────┘     └─────────────────────────────────┘
```

### Command Bus jako port

```php
// Port (Application Layer)
namespace App\Shared\Application\Bus;

interface CommandBusInterface
{
    public function dispatch(object $command): mixed;
}

// Adapter (Infrastructure Layer)
namespace App\Shared\Infrastructure\Messenger;

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

### Contract Adapter między BC

```php
// Port w Shared
namespace App\Shared\Contract;

interface BookInfoProviderInterface
{
    public function getBookInfo(string $bookId): ?BookInfoDto;
}

// Adapter w Catalog (implementuje kontrakt)
namespace App\Catalog\Infrastructure\ContractAdapter;

final readonly class CatalogBookInfoProvider implements BookInfoProviderInterface
{
    public function __construct(
        private CatalogBookRepositoryInterface $repository
    ) {}

    public function getBookInfo(string $bookId): ?BookInfoDto
    {
        $book = $this->repository->findById(new CatalogBookId($bookId));

        if (!$book) {
            return null;
        }

        return new BookInfoDto(
            id: $book->id()->value(),
            title: $book->title(),
            authorName: $book->author()->fullName(),
            isbn: $book->isbn()->value()
        );
    }
}
```

---

## Następne kroki

- [Commands i Handlers](../cqrs/commands-and-handlers.md) - CQRS w praktyce
- [Domain Events](../cqrs/events.md) - Komunikacja eventowa
- [Testowanie](../testing.md) - Strategia testów

[< Powrót do README](../../README.md)
