# Architektura Hexagonalna (Ports and Adapters)

[< Powrót do README](../../README.md)

## Spis treści
- [Wprowadzenie](#wprowadzenie)
- [Problem: Tradycyjna architektura](#problem-tradycyjna-architektura)
- [Rozwiązanie: Architektura Hexagonalna](#rozwiązanie-architektura-hexagonalna)
- [Dependency Inversion](#dependency-inversion)
- [Korzyści](#korzyści)
- [Historia i kontekst](#historia-i-kontekst)

---

## Wprowadzenie

**Architektura Hexagonalna** (ang. Hexagonal Architecture), znana również jako **Ports and Adapters**, to wzorzec architektoniczny stworzony przez **Alistair Cockburna** w 2005 roku.

> **"Pozwól aplikacji być równie dobrze sterowanej przez użytkowników, programy, testy automatyczne, czy skrypty batch, i być rozwijana oraz testowana w izolacji od urządzeń i baz danych."**
> — Alistair Cockburn

### Dlaczego "Hexagonalna"?

Nazwa pochodzi od sześciokątnego diagramu, który Cockburn użył do wizualizacji. Sześciokąt nie ma specjalnego znaczenia - chodzi o to, że aplikacja ma **wiele różnych portów** (wejść i wyjść), a nie tylko dwa (jak w tradycyjnej architekturze warstwowej).

```
                     ┌───────────────────────┐
                     │                       │
    REST API ───────►│                       │◄─────── CLI
                     │                       │
                     │       DOMAIN          │
    GraphQL ────────►│                       │◄─────── Tests
                     │   (logika biznesowa)  │
                     │                       │
    WebSocket ──────►│                       │─────────► Database
                     │                       │
                     │                       │─────────► Email
                     └───────────────────────┘
```

---

## Problem: Tradycyjna architektura

### Typowa architektura warstwowa (MVC)

```
Controller → Service → Repository → Database
```

W tradycyjnej architekturze MVC/warstwowej często spotykamy:

```php
// ❌ Typowy "gruby" serwis w tradycyjnej architekturze
class BookService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private LoggerInterface $logger
    ) {}

    public function borrowBook(int $bookId, int $userId): void
    {
        // Logika biznesowa pomieszana z infrastrukturą
        $book = $this->entityManager->find(Book::class, $bookId);

        // Walidacja w serwisie, nie w domenie
        if ($book->getStatus() !== 'available') {
            throw new \Exception('Book not available');
        }

        // Bezpośrednie modyfikacje stanu przez settery
        $book->setStatus('borrowed');
        $book->setBorrowedBy($userId);
        $book->setBorrowedAt(new \DateTime());

        // Wysyłka emaila w tym samym miejscu co logika
        $this->mailer->send(
            new Email()
                ->to($user->getEmail())
                ->subject('Książka wypożyczona')
        );

        // Logowanie w środku logiki
        $this->logger->info('Book borrowed', ['bookId' => $bookId]);

        $this->entityManager->flush();
    }
}
```

### Co jest nie tak?

| Problem | Opis | Konsekwencja |
|---------|------|--------------|
| **Anemic Domain Model** | Encje to "głupie" kontenery na dane z getterami/setterami | Logika biznesowa rozproszona po serwisach |
| **Zależność od Doctrine** | Serwis bezpośrednio używa EntityManager | Niemożliwe testowanie bez bazy danych |
| **Brak enkapsulacji** | Każdy może zmienić stan encji przez `setStatus()` | Brak gwarancji spójności stanu |
| **Pomieszane odpowiedzialności** | Serwis robi: walidację, logikę, email, logging, persistence | Trudność w utrzymaniu i testowaniu |
| **Sztywne powiązanie** | Zmiana bazy = przepisanie całej aplikacji | Brak elastyczności |

### Prawdziwy koszt - testy

```php
// ❌ Test wymaga mockowania całej infrastruktury
class BookServiceTest extends TestCase
{
    public function testBorrowBook(): void
    {
        // 15+ linii setupu zanim napiszesz właściwy test
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $mailer = $this->createMock(MailerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $book = new Book();
        $book->setStatus('available');

        $entityManager
            ->method('find')
            ->willReturn($book);

        $mailer
            ->expects($this->once())
            ->method('send');

        // ... dopiero teraz właściwy test
    }
}
```

---

## Rozwiązanie: Architektura Hexagonalna

### Wizualizacja

```
                    ┌─────────────────────────────────────────┐
                    │                                         │
    HTTP Request ──►│  ┌─────────────────────────────────┐   │
                    │  │         PRESENTATION            │   │
    CLI Command ───►│  │  (Controllers, Commands, API)   │   │
                    │  └──────────────┬──────────────────┘   │
                    │                 │                       │
                    │                 ▼                       │
                    │  ┌─────────────────────────────────┐   │
                    │  │         APPLICATION             │   │
                    │  │   (Use Cases, Commands, Queries)│   │
                    │  └──────────────┬──────────────────┘   │
                    │                 │                       │
                    │                 ▼                       │
                    │  ┌─────────────────────────────────┐   │
                    │  │           DOMAIN                │   │◄── Serce aplikacji
                    │  │  (Entities, Value Objects,      │   │    Czysta logika
                    │  │   Repository Interfaces)        │   │    biznesowa
                    │  └──────────────▲──────────────────┘   │
                    │                 │ implementuje         │
                    │  ┌──────────────┴──────────────────┐   │
                    │  │       INFRASTRUCTURE            │   │
                    │  │  (Doctrine, External APIs,      │   │──► Database
                    │  │   Message Queues, Email)        │   │──► Redis
                    │  └─────────────────────────────────┘   │──► External APIs
                    │                                         │
                    └─────────────────────────────────────────┘
```

### Kluczowe zasady

1. **Domena w centrum** - logika biznesowa nie wie o infrastrukturze
2. **Zależności wskazują do środka** - infrastruktura zależy od domeny
3. **Porty definiują kontrakty** - interfejsy w domenie mówią "co", nie "jak"
4. **Adaptery implementują porty** - konkretne technologie dostarczają "jak"

---

## Dependency Inversion

### Tradycyjnie vs Hexagonalnie

```
❌ TRADYCYJNIE: Domain zależy od Infrastructure
   Domain → Infrastructure (EntityManager, Mailer, etc.)

   Konsekwencja: Zmiana bazy = zmiana domeny

✅ HEXAGONALNIE: Infrastructure zależy od Domain
   Infrastructure → Domain (implementuje interfejsy domeny)

   Konsekwencja: Zmiana bazy = nowa implementacja, domena bez zmian
```

### Praktyczny przykład

```php
// KROK 1: Domain definiuje CO chce (interfejs = port)
namespace App\Lending\Domain\Repository;

interface BookRepositoryInterface
{
    public function findById(BookId $id): ?Book;
    public function save(Book $book): void;
    public function findAvailable(): array;
}
```

```php
// KROK 2: Infrastructure definiuje JAK to zrobić (implementacja = adapter)
namespace App\Lending\Infrastructure\Doctrine\Repository;

class DoctrineBookRepository implements BookRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function findById(BookId $id): ?Book
    {
        return $this->entityManager->find(Book::class, $id->value());
    }

    public function save(Book $book): void
    {
        $this->entityManager->persist($book);
        $this->entityManager->flush();
    }
}
```

```php
// KROK 3: Application używa interfejsu (port), nie implementacji
namespace App\Lending\Application\Command;

class BorrowBookCommandHandler
{
    public function __construct(
        private BookRepositoryInterface $bookRepository  // ✅ Interfejs!
    ) {}

    public function __invoke(BorrowBookCommand $command): void
    {
        $book = $this->bookRepository->findById(
            new BookId($command->bookId)
        );

        $book->borrow();

        $this->bookRepository->save($book);
    }
}
```

### Wstrzykiwanie zależności (DI)

Symfony automatycznie "skleja" port z adapterem:

```yaml
# config/services.yaml
services:
    App\Lending\Domain\Repository\BookRepositoryInterface:
        alias: App\Lending\Infrastructure\Doctrine\Repository\DoctrineBookRepository
```

---

## Korzyści

### 1. Testowalność

```php
// ✅ Test domenowy - zero zależności zewnętrznych!
class BookTest extends TestCase
{
    public function testCanBorrowAvailableBook(): void
    {
        $book = new Book(
            new BookId('book-1'),
            'Test Title',
            'Test Author',
            '978-0-000-00000-0',
            new DateTimeImmutable()
        );

        $book->borrow();

        $this->assertFalse($book->isAvailable());
    }
}
```

```php
// ✅ Test handlera z prostym mockiem
class BorrowBookCommandHandlerTest extends TestCase
{
    public function testHandle(): void
    {
        $book = new Book(/* ... */);

        $repo = $this->createMock(BookRepositoryInterface::class);
        $repo->method('findById')->willReturn($book);

        $handler = new BorrowBookCommandHandler($repo);
        $handler(new BorrowBookCommand('user-1', 'book-1'));

        $this->assertFalse($book->isAvailable());
    }
}
```

### 2. Wymienność technologii

```php
// Dzisiaj: PostgreSQL przez Doctrine
class DoctrineBookRepository implements BookRepositoryInterface { }

// Jutro: Redis cache
class RedisBookRepository implements BookRepositoryInterface { }

// Pojutrze: External API
class ApiBookRepository implements BookRepositoryInterface { }

// Tylko zmiana w services.yaml - domena bez zmian!
```

### 3. Niezależność od frameworka

Domena nie wie, że istnieje Symfony:

```php
namespace App\Lending\Domain\Entity;

// Czysta klasa PHP - zero importów Symfony
class Book
{
    public function borrow(): void
    {
        if (!$this->isAvailable) {
            throw new \DomainException('Book is not available');
        }
        $this->isAvailable = false;
    }
}
```

### 4. Czytelność i onboarding

| Warstwa | Odpowiedzialność | Lokalizacja |
|---------|------------------|-------------|
| Domain | Logika biznesowa | `src/{BC}/Domain/` |
| Application | Orkiestracja use cases | `src/{BC}/Application/` |
| Infrastructure | Szczegóły techniczne | `src/{BC}/Infrastructure/` |
| Presentation | Interfejs zewnętrzny | `src/{BC}/Presentation/` |

Nowy developer wie gdzie szukać czego.

### Porównanie

| Aspekt | Tradycyjna | Hexagonalna |
|--------|------------|-------------|
| **Testowanie domeny** | Wymaga bazy danych | Czyste unit testy |
| **Zmiana bazy danych** | Przepisanie aplikacji | Nowa implementacja |
| **Zrozumienie logiki** | Rozproszona po serwisach | Skupiona w domenie |
| **Onboarding** | Trudny | Jasna struktura |
| **Koszt początkowy** | Niski | Wyższy (więcej kodu) |
| **Koszt utrzymania** | Rośnie z czasem | Stabilny |

---

## Historia i kontekst

### Alistair Cockburn (2005)

Architektura Hexagonalna została zaproponowana przez Alistair Cockburna w artykule "Hexagonal Architecture" na jego blogu. Główną motywacją było:

1. **Uniezależnienie aplikacji od zewnętrznych aktorów** - czy to użytkownik, test automatyczny, czy inna aplikacja
2. **Możliwość testowania w izolacji** - bez baz danych, emaili, kolejek
3. **Elastyczność wymiany technologii** - bez przepisywania logiki biznesowej

### Podobne wzorce

| Wzorzec | Autor | Rok | Różnice |
|---------|-------|-----|---------|
| **Hexagonal Architecture** | Alistair Cockburn | 2005 | Ports & Adapters |
| **Onion Architecture** | Jeffrey Palermo | 2008 | Więcej warstw, Domain w centrum |
| **Clean Architecture** | Robert C. Martin | 2012 | Bardziej formalna, więcej zasad |

Wszystkie trzy wzorce mają wspólny cel: **izolacja logiki biznesowej od szczegółów technicznych**.

### Kiedy stosować?

**Stosuj gdy:**
- Projekt będzie rozwijany długoterminowo
- Logika biznesowa jest złożona
- Potrzebujesz wysokiej testowalności
- Możliwa zmiana technologii w przyszłości
- Zespół jest większy niż 2-3 osoby

**Rozważ prostsze podejście gdy:**
- Prosty CRUD bez logiki biznesowej
- Prototyp/MVP
- Projekt jednorazowy
- Bardzo mały zespół z ograniczonym czasem

---

## Następne kroki

- [Bounded Contexts](bounded-contexts.md) - Jak dzielić system na moduły
- [Warstwy aplikacji](layers.md) - Szczegóły każdej warstwy
- [Porty i Adaptery](ports-and-adapters.md) - Praktyczna implementacja

[< Powrót do README](../../README.md)
