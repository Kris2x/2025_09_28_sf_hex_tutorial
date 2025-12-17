# Architektura Hexagonalna w Symfony - System Biblioteki Online

## Spis treści
- [Wprowadzenie](#wprowadzenie)
- [Problem: Dlaczego tradycyjna architektura zawodzi?](#problem-dlaczego-tradycyjna-architektura-zawodzi)
- [Rozwiązanie: Architektura Hexagonalna](#rozwiązanie-architektura-hexagonalna)
- [Bounded Contexts - podział na moduły biznesowe](#bounded-contexts---podział-na-moduły-biznesowe)
- [Struktura projektu](#struktura-projektu)
- [Warstwy aplikacji - szczegółowo](#warstwy-aplikacji---szczegółowo)
- [Porty i Adaptery - serce architektury](#porty-i-adaptery---serce-architektury)
- [Dependency Injection - sklejanie warstw](#dependency-injection---sklejanie-warstw)
- [Przepływ danych - jak to wszystko działa razem](#przepływ-danych---jak-to-wszystko-działa-razem)
- [Kompromisy architektoniczne](#kompromisy-architektoniczne)
- [Uruchomienie projektu](#uruchomienie-projektu)
- [API Endpoints](#api-endpoints)
- [Testowanie](#testowanie)
- [Następne kroki](#następne-kroki)

---

## Wprowadzenie

Ten projekt demonstruje implementację **architektury hexagonalnej** (znanej również jako "Ports and Adapters") w frameworku Symfony, z podziałem na **Bounded Contexts** zgodnie z Domain-Driven Design.

**Stack technologiczny:**
- PHP 8.2+
- Symfony 7.3
- Doctrine ORM 3.5
- PostgreSQL (Docker)

---

## Problem: Dlaczego tradycyjna architektura zawodzi?

### Typowa architektura warstwowa (MVC)

```
Controller → Service → Repository → Database
```

**Co jest nie tak?**

```php
// ❌ Typowy "gruby" serwis w tradycyjnej architekturze
class BookService
{
    public function borrowBook(int $bookId, int $userId): void
    {
        // Logika biznesowa pomieszana z infrastrukturą
        $book = $this->entityManager->find(Book::class, $bookId);

        // Walidacja w serwisie, nie w domenie
        if ($book->getStatus() !== 'available') {
            throw new \Exception('Book not available');
        }

        // Bezpośrednie modyfikacje stanu
        $book->setStatus('borrowed');
        $book->setBorrowedBy($userId);
        $book->setBorrowedAt(new \DateTime());

        // Wysyłka emaila w tym samym miejscu co logika
        $this->mailer->send(...);

        $this->entityManager->flush();
    }
}
```

### Problemy tej architektury

| Problem | Konsekwencja |
|---------|--------------|
| **Logika biznesowa w serwisach** | Encje to "głupie" kontenery na dane (anemic domain model) |
| **Zależność od Doctrine** | Nie można przetestować logiki bez bazy danych |
| **Brak enkapsulacji** | Każdy może zmienić stan encji przez settery |
| **Pomieszane odpowiedzialności** | Serwis robi wszystko: walidację, logikę, persistencję, notyfikacje |
| **Trudność testowania** | Testy wymagają bazy danych, mailerów, itp. |

### Prawdziwy koszt

```php
// ❌ Test wymaga mockowania całej infrastruktury
class BookServiceTest extends TestCase
{
    public function testBorrowBook(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $mailer = $this->createMock(MailerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        // ... 15 linii mockowania zanim napiszesz właściwy test
    }
}
```

---

## Rozwiązanie: Architektura Hexagonalna

### Główna idea

> **"Pozwól aplikacji być równie dobrze sterowanej przez użytkowników, programy, testy automatyczne, czy skrypty batch, i być rozwijana oraz testowana w izolacji od urządzeń i baz danych."**
> — Alistair Cockburn (twórca architektury hexagonalnej)

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
                    │  └──────────────┬──────────────────┘   │
                    │                 │                       │
                    │                 ▼                       │
                    │  ┌─────────────────────────────────┐   │
                    │  │       INFRASTRUCTURE            │   │
                    │  │  (Doctrine, External APIs,      │   │──► Database
                    │  │   Message Queues, Email)        │   │──► Redis
                    │  └─────────────────────────────────┘   │──► External APIs
                    │                                         │
                    └─────────────────────────────────────────┘
```

### Kluczowa zasada: Dependency Inversion

```
❌ TRADYCYJNIE: Domain zależy od Infrastructure
   Domain → Infrastructure (EntityManager, Mailer, etc.)

✅ HEXAGONALNIE: Infrastructure zależy od Domain
   Infrastructure → Domain (implementuje interfejsy domeny)
```

**Co to oznacza w praktyce?**

```php
// Domain definiuje CO chce (interfejs)
namespace App\Lending\Domain\Repository;

interface BookRepositoryInterface
{
    public function findById(BookId $id): ?Book;
    public function save(Book $book): void;
}

// Infrastructure definiuje JAK to zrobić (implementacja)
namespace App\Lending\Infrastructure\Doctrine\Repository;

class DoctrineBookRepository implements BookRepositoryInterface
{
    public function findById(BookId $id): ?Book
    {
        return $this->entityManager->find(Book::class, $id->value());
    }
}
```

### Dlaczego to działa?

| Aspekt | Tradycyjna | Hexagonalna |
|--------|------------|-------------|
| **Testowanie domeny** | Wymaga bazy danych | Czyste unit testy |
| **Zmiana bazy danych** | Przepisanie całej aplikacji | Nowa implementacja repozytorium |
| **Zrozumienie logiki** | Rozproszona po serwisach | Skupiona w domenie |
| **Onboarding nowego developera** | Trudny | Łatwiejszy - jasna struktura |

---

## Bounded Contexts - podział na moduły biznesowe

### Czym jest Bounded Context?

**Bounded Context** to granica, w której dany model domenowy ma **spójne i jednoznaczne znaczenie**.

### Problem: To samo słowo, różne znaczenia

W systemie bibliotecznym słowo "Książka" może oznaczać różne rzeczy:

```
📚 Dla bibliotekarza katalogującego:
   - Tytuł, autor, ISBN, opis, okładka, recenzje, kategorie

📖 Dla systemu wypożyczeń:
   - ID, czy jest dostępna, kto wypożyczył, kiedy zwrot

💰 Dla działu zakupów:
   - Cena, dostawca, numer faktury, data dostawy
```

**Próba stworzenia jednej encji Book dla wszystkich przypadków kończy się katastrofą:**

```php
// ❌ "God Object" - encja, która wie wszystko
class Book
{
    private $id;
    private $title;
    private $author;
    private $isbn;
    private $description;        // Katalog
    private $coverImage;         // Katalog
    private $reviews;            // Katalog
    private $categories;         // Katalog
    private $isAvailable;        // Wypożyczenia
    private $borrowedBy;         // Wypożyczenia
    private $dueDate;            // Wypożyczenia
    private $purchasePrice;      // Zakupy
    private $supplier;           // Zakupy
    private $invoiceNumber;      // Zakupy
    // ... 50 pól później ...
}
```

### Rozwiązanie: Osobne modele w osobnych kontekstach

```
src/
├── Lending/           # Kontekst: Wypożyczenia
│   └── Domain/
│       └── Entity/
│           └── Book.php    ← Book z polami: id, title, isAvailable
│
├── Catalog/           # Kontekst: Katalog
│   └── Domain/
│       └── Entity/
│           └── CatalogBook.php  ← Book z: title, description, reviews
│
└── Acquisition/       # Kontekst: Zakupy
    └── Domain/
        └── Entity/
            └── PurchasedBook.php  ← Book z: price, supplier, invoice
```

### Bounded Contexts w tym projekcie

| Kontekst | Odpowiedzialność | Encje | Status |
|----------|------------------|-------|--------|
| **Lending** | Wypożyczenia, zwroty, kary | Book, User, Loan | ✅ Zaimplementowany |
| **Catalog** | Przeglądanie, wyszukiwanie, recenzje | CatalogBook, Author, Category | 📋 TODO |
| **Membership** | Członkostwo, karty biblioteczne | Member, LibraryCard | 📋 TODO |
| **Acquisition** | Zakupy, dostawcy, faktury | PurchaseOrder, Supplier | 📋 TODO |

### Komunikacja między kontekstami

Konteksty komunikują się przez:

1. **Domain Events** (asynchronicznie)
   ```
   Lending emituje: BookBorrowedEvent
   Membership nasłuchuje: aktualizuje historię członka
   ```

2. **Shared Kernel** (współdzielone Value Objects)
   ```
   BookId może być współdzielone między Lending a Catalog
   ```

3. **Anti-Corruption Layer** (tłumaczenie między kontekstami)
   ```
   Lending.Book ←→ ACL ←→ Catalog.CatalogBook
   ```

---

## Struktura projektu

```
src/
├── Shared/                             # ══════════════════════════════
│   │                                   # WSPÓŁDZIELONE MIĘDZY KONTEKSTAMI
│   │                                   # ══════════════════════════════
│   │
│   ├── Domain/
│   │   └── Event/                      # 📢 Domain Events (interfejsy)
│   │       ├── DomainEventInterface.php
│   │       └── EventPublisherInterface.php   # Port do publikacji eventów
│   │
│   └── Infrastructure/
│       └── Messenger/
│           └── MessengerEventPublisher.php   # Adapter - Symfony Messenger
│
├── Lending/                            # ══════════════════════════════
│   │                                   # BOUNDED CONTEXT: WYPOŻYCZENIA
│   │                                   # ══════════════════════════════
│   │
│   ├── Domain/                         # 🎯 WARSTWA DOMENOWA
│   │   │                               # Serce aplikacji - czysta logika biznesowa
│   │   │                               # ZERO zależności zewnętrznych
│   │   │
│   │   ├── Entity/                     # Encje domenowe (Aggregates)
│   │   │   ├── Book.php                #   - Stan + zachowania biznesowe
│   │   │   ├── User.php                #   - Walidacja reguł w metodach
│   │   │   └── Loan.php                #   - Enkapsulacja (brak setterów)
│   │   │
│   │   ├── ValueObject/                # Value Objects (niezmienne)
│   │   │   ├── BookId.php              #   - Identyfikatory typowane
│   │   │   ├── UserId.php              #   - Walidacja w konstruktorze
│   │   │   └── Email.php               #   - Porównywanie przez wartość
│   │   │
│   │   ├── Event/                      # 📢 Domain Events (tego kontekstu)
│   │   │   └── BookBorrowedEvent.php   #   - "Książka wypożyczona"
│   │   │
│   │   └── Repository/                 # 🔌 PORTY (interfejsy)
│   │       ├── BookRepositoryInterface.php    # Kontrakt: "co" potrzebuję
│   │       ├── UserRepositoryInterface.php    # NIE mówi "jak" to zrobić
│   │       └── LoanRepositoryInterface.php
│   │
│   ├── Application/                    # 🎬 WARSTWA APLIKACJI
│   │   │                               # Orkiestracja use cases
│   │   │                               # Zależy TYLKO od Domain
│   │   │
│   │   ├── Command/                    # Komendy (modyfikują stan)
│   │   │   ├── BorrowBookCommand.php   #   - Wypożycz + emituje event
│   │   │   └── ReturnBookCommand.php   #   - Zwróć książkę
│   │   │
│   │   └── Query/                      # Zapytania (tylko odczyt)
│   │       ├── GetAvailableBooksQuery.php
│   │       └── GetUserLoansQuery.php
│   │
│   ├── Infrastructure/                 # 🔧 WARSTWA INFRASTRUKTURY
│   │   │                               # Szczegóły techniczne
│   │   │                               # Implementuje interfejsy z Domain
│   │   │
│   │   └── Doctrine/
│   │       ├── Repository/             # 🔌 ADAPTERY (implementacje)
│   │       │   ├── DoctrineBookRepository.php   # Implementuje BookRepositoryInterface
│   │       │   ├── DoctrineUserRepository.php   # Wie JAK zapisać do PostgreSQL
│   │       │   └── DoctrineLoanRepository.php
│   │       │
│   │       └── Type/                   # Custom Doctrine Types
│   │           ├── BookIdType.php      # Mapowanie Value Objects ↔ DB
│   │           ├── UserIdType.php
│   │           └── EmailType.php
│   │
│   └── Presentation/                   # 🖥️ WARSTWA PREZENTACJI
│       │                               # Interfejs ze światem zewnętrznym
│       │
│       └── Controller/
│           └── BookController.php      # REST API adapter
│
├── Catalog/                            # ══════════════════════════════
│   │                                   # BOUNDED CONTEXT: KATALOG
│   │                                   # ══════════════════════════════
│   │
│   └── Application/
│       └── EventHandler/               # 👂 Nasłuchuje eventów z innych kontekstów
│           └── UpdateBookPopularityOnBookBorrowed.php
│
├── Membership/                         # 📋 TODO: Kontekst Członkostwo
│   └── README.md
│
├── Acquisition/                        # 📋 TODO: Kontekst Zakupy
│   └── README.md
│
└── DataFixtures/
    └── LibraryFixtures.php
```

---

## Warstwy aplikacji - szczegółowo

### 1. Domain Layer - Serce aplikacji

**Zasada:** Domena nie wie, że istnieje Symfony, Doctrine, HTTP, czy baza danych.

#### Encje domenowe

```php
namespace App\Lending\Domain\Entity;

class Book
{
    // ✅ Stan prywatny - nie ma setterów!
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
    public function isAvailable(): bool
    {
        return $this->isAvailable;
    }
}
```

**Dlaczego to lepsze?**

```php
// ❌ Anemic Domain Model - encja to głupi kontener
$book->setStatus('borrowed');  // Każdy może zmienić na cokolwiek
$book->setAvailable(false);    // Brak walidacji

// ✅ Rich Domain Model - encja chroni swój stan
$book->borrow();  // Encja waliduje i zmienia stan atomowo
```

#### Value Objects

```php
namespace App\Lending\Domain\ValueObject;

final readonly class Email
{
    public function __construct(private string $value)
    {
        // ✅ Walidacja w konstruktorze - niemożliwe stworzyć nieprawidłowy email
        if (!filter_var($this->value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    // ✅ Porównywanie przez wartość, nie referencję
    public function equals(Email $other): bool
    {
        return $this->value === $other->value;
    }
}
```

**Dlaczego Value Objects?**

```php
// ❌ Primitive Obsession - string może być czymkolwiek
function sendEmail(string $email): void { }
sendEmail('not-an-email');  // Kompiluje się, ale wysadzi runtime

// ✅ Type Safety - kompilator pilnuje poprawności
function sendEmail(Email $email): void { }
sendEmail(new Email('not-an-email'));  // Wyjątek od razu w konstruktorze
```

#### Repository Interfaces (Porty)

```php
namespace App\Lending\Domain\Repository;

// ✅ Interfejs mówi CO potrzebuję, nie JAK to zrobić
interface BookRepositoryInterface
{
    public function save(Book $book): void;
    public function findById(BookId $id): ?Book;
    public function findAvailable(): array;
}
```

**Zauważ:**
- Interfejs jest w **Domain**, nie w Infrastructure
- Używa **domenowych typów** (BookId, Book), nie prymitywów
- Nie ma żadnej wzmianki o Doctrine, SQL, czy bazie danych

---

### 2. Application Layer - Command i Query

**Zasada:** Warstwa aplikacji koordynuje przepływ, ale NIE zawiera logiki biznesowej.

#### Podział na Command i Query

| Typ | Cel | Przykład |
|-----|-----|----------|
| **Command** | Modyfikuje stan systemu | BorrowBookCommand, ReturnBookCommand |
| **Query** | Tylko odczytuje dane | GetAvailableBooksQuery, GetUserLoansQuery |

#### Command - modyfikacja stanu

```php
namespace App\Lending\Application\Command;

/**
 * Command: Wypożyczenie książki.
 *
 * Command MODYFIKUJE stan systemu.
 * Orkiestruje przepływ - deleguje logikę biznesową do domeny.
 */
final readonly class BorrowBookCommand
{
    public function __construct(
        // ✅ Zależność od INTERFEJSU, nie implementacji
        private BookRepositoryInterface $bookRepository,
        private UserRepositoryInterface $userRepository,
        private LoanRepositoryInterface $loanRepository
    ) {}

    public function execute(string $userId, string $bookId): void
    {
        // 1. Pobierz encje
        $user = $this->userRepository->findById(new UserId($userId));
        $book = $this->bookRepository->findById(new BookId($bookId));

        // 2. Deleguj logikę do DOMENY
        if (!$user->canBorrowBook()) {
            throw new \DomainException('User has reached maximum loan limit');
        }

        // 3. Wykonaj operacje domenowe
        $user->borrowBook();
        $book->borrow();

        $loan = new Loan(/* ... */);

        // 4. Zapisz zmiany
        $this->userRepository->save($user);
        $this->bookRepository->save($book);
        $this->loanRepository->save($loan);
    }
}
```

#### Query - tylko odczyt

```php
namespace App\Lending\Application\Query;

/**
 * Query: Pobranie dostępnych książek.
 *
 * Query TYLKO ODCZYTUJE dane - NIE modyfikuje stanu!
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

**Co Command/Query ROBI:**
- Pobiera encje z repozytoriów
- Wywołuje metody biznesowe na encjach
- Command: zapisuje zmiany | Query: zwraca dane
- Koordynuje przepływ

**Czego Command/Query NIE ROBI:**
- Nie zawiera logiki biznesowej (to domena!)
- Nie wie o HTTP, Doctrine, czy innych szczegółach
- Nie waliduje reguł biznesowych (to domena!)

---

### 3. Infrastructure Layer - Szczegóły techniczne

**Zasada:** Infrastruktura IMPLEMENTUJE interfejsy zdefiniowane w domenie.

```php
namespace App\Lending\Infrastructure\Doctrine\Repository;

// ✅ Implementuje interfejs domenowy
final class DoctrineBookRepository implements BookRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        $this->repository = $this->entityManager->getRepository(Book::class);
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
}
```

**Korzyść: Wymienność**

```php
// Jutro chcesz Redis zamiast PostgreSQL?
final class RedisBookRepository implements BookRepositoryInterface
{
    public function findById(BookId $id): ?Book
    {
        $data = $this->redis->get("book:{$id->value()}");
        return $data ? Book::fromArray(json_decode($data)) : null;
    }
}

// Tylko zmiana w services.yaml:
// App\Lending\Domain\Repository\BookRepositoryInterface:
//     alias: App\Lending\Infrastructure\Redis\RedisBookRepository
```

---

### 4. Presentation Layer - Interfejs zewnętrzny

**Zasada:** Kontroler to "tłumacz" między HTTP a Application Layer.

```php
namespace App\Lending\Presentation\Controller;

#[Route('/api/books')]
final class BookController extends AbstractController
{
    #[Route('/{bookId}/borrow', methods: ['POST'])]
    public function borrowBook(
        string $bookId,
        Request $request,
        BorrowBookCommand $command  // ✅ Wstrzyknięty przez DI
    ): JsonResponse {
        // 1. Wyciągnij dane z HTTP
        $data = json_decode($request->getContent(), true);
        $userId = $data['userId'] ?? null;

        if (!$userId) {
            return $this->json(['error' => 'userId is required'], 400);
        }

        // 2. Deleguj do Command
        try {
            $command->execute($userId, $bookId);
            return $this->json(['message' => 'Book borrowed successfully']);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
```

**Kontroler NIE:**
- Nie zawiera logiki biznesowej
- Nie operuje bezpośrednio na encjach
- Nie wywołuje repozytoriów bezpośrednio

---

## Porty i Adaptery - serce architektury

### Wizualizacja

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
│         │ wywołuje                                              │
│         ▼                                                       │
│  ┌──────────────┐                                               │
│  │    Domain    │                                               │
│  │   Entities   │                                               │
│  └──────────────┘                                               │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘

PORTY (Interfejsy):              ADAPTERY (Implementacje):
─────────────────────            ─────────────────────────
BookRepositoryInterface    ◄───  DoctrineBookRepository
UserRepositoryInterface    ◄───  DoctrineUserRepository
                           ◄───  InMemoryUserRepository (testy)
                           ◄───  RedisUserRepository (cache)

Strzałka ◄─── oznacza "implementuje" / "zależy od"
Adapter ZALEŻY OD interfejsu (Port), nie odwrotnie!
```

### Rodzaje portów

**Porty wejściowe (Driving)** - jak świat zewnętrzny używa aplikacji:
- REST API Controller
- CLI Command
- GraphQL Resolver
- Message Queue Consumer

**Porty wyjściowe (Driven)** - jak aplikacja używa świata zewnętrznego:
- Repository Interface (baza danych)
- Mailer Interface (wysyłka emaili)
- EventPublisher Interface (eventy)
- PaymentGateway Interface (płatności)

---

## Dependency Injection - sklejanie warstw

### Konfiguracja w services.yaml

```yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true

    # Autoload wszystkich klas
    App\:
        resource: '../src/'

    # ═══════════════════════════════════════════════════════════
    # LENDING BOUNDED CONTEXT
    # ═══════════════════════════════════════════════════════════

    # Binding: Port → Adapter
    # "Gdy ktoś poprosi o BookRepositoryInterface, daj mu DoctrineBookRepository"

    App\Lending\Domain\Repository\BookRepositoryInterface:
        alias: App\Lending\Infrastructure\Doctrine\Repository\DoctrineBookRepository

    App\Lending\Domain\Repository\UserRepositoryInterface:
        alias: App\Lending\Infrastructure\Doctrine\Repository\DoctrineUserRepository

    App\Lending\Domain\Repository\LoanRepositoryInterface:
        alias: App\Lending\Infrastructure\Doctrine\Repository\DoctrineLoanRepository
```

### Jak to działa?

```php
// Symfony widzi tę sygnaturę:
class BorrowBookCommand
{
    public function __construct(
        private BookRepositoryInterface $bookRepository,  // Interfejs!
    ) {}
}

// I automatycznie wstrzykuje:
new BorrowBookCommand(
    new DoctrineBookRepository($entityManager)  // Implementację!
);
```

### Korzyść: Łatwe testowanie

```yaml
# config/services_test.yaml
services:
    # W testach używamy implementacji in-memory
    App\Lending\Domain\Repository\BookRepositoryInterface:
        alias: App\Lending\Infrastructure\InMemory\InMemoryBookRepository
```

---

## Przepływ danych - jak to wszystko działa razem

### Sekwencja: Wypożyczenie książki

```
1. HTTP Request
   POST /api/books/book-1/borrow
   Body: {"userId": "user-1"}
            │
            ▼
2. BookController (Presentation)
   - Parsuje JSON
   - Wyciąga userId z body
   - Wywołuje BorrowBookCommand
            │
            ▼
3. BorrowBookCommand (Application)
   - Pobiera User przez UserRepositoryInterface
   - Pobiera Book przez BookRepositoryInterface
   - Sprawdza: user.canBorrowBook()
   - Wywołuje: user.borrowBook()
   - Wywołuje: book.borrow()
   - Tworzy Loan
   - Zapisuje wszystko przez interfejsy
            │
            ▼
4. DoctrineUserRepository (Infrastructure)
   DoctrineBookRepository
   DoctrineLoanRepository
   - EntityManager->persist()
   - EntityManager->flush()
            │
            ▼
5. PostgreSQL
   - INSERT INTO loans ...
   - UPDATE books SET is_available = false
   - UPDATE users SET active_loan_count = ...
            │
            ▼
6. Response
   {"message": "Book borrowed successfully"}
```

### Diagram sekwencji

```
Controller      Command         Domain          Repository      Database
    │               │              │                │              │
    │──execute()───►│              │                │              │
    │               │──findById()─►│                │              │
    │               │              │◄──────────────►│──SELECT─────►│
    │               │              │                │◄─────────────│
    │               │◄─────────────│                │              │
    │               │              │                │              │
    │               │──canBorrow()─►│               │              │
    │               │◄──true───────│                │              │
    │               │              │                │              │
    │               │──borrowBook()►│               │              │
    │               │──borrow()────►│               │              │
    │               │              │                │              │
    │               │──save()──────►│               │              │
    │               │              │───────────────►│──UPDATE─────►│
    │               │              │                │◄─────────────│
    │◄──success─────│              │                │              │
```

---

## Kompromisy architektoniczne

### Doctrine Attributes w encjach domenowych

**Purystyczne podejście:**
```php
// Domain - czysta encja
class Book { }

// Infrastructure - osobny mapping
// config/doctrine/Book.orm.xml
```

**Nasze pragmatyczne podejście:**
```php
#[ORM\Entity]
#[ORM\Table(name: 'books')]
class Book
{
    #[ORM\Id]
    #[ORM\Column(type: 'book_id')]
    private BookId $id;
}
```

**Dlaczego to akceptowalne?**

| Aspekt | Puryzm | Pragmatyzm |
|--------|--------|------------|
| **Czystość domeny** | ✅ 100% czysta | ⚠️ Atrybuty ORM |
| **Ilość kodu** | ❌ Dużo boilerplate | ✅ Mniej kodu |
| **Czytelność** | ❌ Mapping osobno | ✅ Mapping przy encji |
| **Refactoring** | ❌ 2 miejsca do zmiany | ✅ 1 miejsce |
| **IDE support** | ❌ Słabszy | ✅ Pełny |

**Wniosek:** Atrybuty Doctrine to akceptowalny kompromis dla większości projektów. Zyskujemy produktywność, tracimy niewiele.

### Kiedy wybrać pełną separację?

- Projekt ma działać z wieloma różnymi bazami danych
- Domena jest współdzielona między wiele aplikacji
- Zespół jest bardzo duży i potrzebuje ścisłych granic

---

## Uruchomienie projektu

### Wymagania
- PHP 8.2+
- Composer
- Docker (dla PostgreSQL)
- Symfony CLI (opcjonalnie)

### Instalacja

```bash
# 1. Klonowanie
git clone <repo-url>
cd 2025_09_28_sf_hex_tutorial

# 2. Zależności
composer install

# 3. Baza danych
docker-compose up -d

# 4. Migracje
php bin/console doctrine:migrations:migrate

# 5. Dane testowe
php bin/console doctrine:fixtures:load

# 6. Serwer
symfony server:start
# lub
php -S localhost:8000 -t public/
```

---

## API Endpoints

### GET /api/books/ - Lista dostępnych książek

```bash
curl http://localhost:8000/api/books/
```

```json
[
    {
        "id": "book-1",
        "title": "Wzorce projektowe",
        "author": "Erich Gamma",
        "isbn": "978-83-246-1493-0",
        "available": true
    },
    {
        "id": "book-2",
        "title": "Czysty kod",
        "author": "Robert C. Martin",
        "isbn": "978-83-283-6234-4",
        "available": true
    }
]
```

### POST /api/books/{id}/borrow - Wypożycz książkę

```bash
curl -X POST http://localhost:8000/api/books/book-1/borrow \
  -H "Content-Type: application/json" \
  -d '{"userId": "user-1"}'
```

```json
{"message": "Book borrowed successfully"}
```

### POST /api/books/{id}/return - Zwróć książkę

```bash
curl -X POST http://localhost:8000/api/books/book-1/return \
  -H "Content-Type: application/json" \
  -d '{"userId": "user-1"}'
```

```json
{
    "message": "Book returned successfully",
    "fine": 0.0
}
```

---

## Testowanie

### Struktura testów

```
tests/
├── Unit/                           # Testy bez I/O
│   └── Lending/
│       ├── Domain/
│       │   ├── Entity/
│       │   │   ├── BookTest.php    # Test logiki Book
│       │   │   ├── UserTest.php    # Test logiki User
│       │   │   └── LoanTest.php    # Test logiki Loan
│       │   └── ValueObject/
│       │       ├── BookIdTest.php
│       │       └── EmailTest.php
│       └── Application/
│           └── Command/
│               └── BorrowBookCommandTest.php
│
├── Integration/                    # Testy z bazą danych
│   └── Lending/
│       └── Repository/
│           └── DoctrineBookRepositoryTest.php
│
└── Functional/                     # Testy HTTP end-to-end
    └── Lending/
        └── Controller/
            └── BookControllerTest.php
```

### Przykład: Test domenowy (bez zależności!)

```php
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

    public function testCannotBorrowUnavailableBook(): void
    {
        $book = new Book(/* ... */);
        $book->borrow();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Book is not available');

        $book->borrow();  // Druga próba - powinno rzucić wyjątek
    }
}
```

### Przykład: Test Command z mockami

```php
class BorrowBookCommandTest extends TestCase
{
    public function testExecuteSuccessfully(): void
    {
        // Arrange - przygotuj mocki
        $user = new User(new UserId('user-1'), 'Jan', new Email('jan@test.pl'), new DateTimeImmutable());
        $book = new Book(new BookId('book-1'), 'Title', 'Author', 'ISBN', new DateTimeImmutable());

        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->method('findById')->willReturn($user);

        $bookRepo = $this->createMock(BookRepositoryInterface::class);
        $bookRepo->method('findById')->willReturn($book);

        $loanRepo = $this->createMock(LoanRepositoryInterface::class);

        // Act
        $command = new BorrowBookCommand($bookRepo, $userRepo, $loanRepo);
        $command->execute('user-1', 'book-1');

        // Assert
        $this->assertFalse($book->isAvailable());
        $this->assertEquals(1, $user->activeLoanCount());
    }
}
```

---

## Następne kroki

### Co już mamy: CQS (Command-Query Separation)

```
Application/
├── Command/    ← Modyfikują stan (BorrowBookCommand)
└── Query/      ← Tylko odczyt (GetAvailableBooksQuery)
```

Obie warstwy używają **tych samych encji domenowych** (Book, User, Loan).

---

### Zaimplementowane: Domain Events

Komunikacja między Bounded Contexts przez Domain Events:

```
Lending                          Catalog
┌──────────────────┐             ┌──────────────────┐
│ BorrowBookCommand│             │ EventHandler     │
│                  │             │                  │
│  publish(event)  │──event.bus─►│ __invoke(event)  │
│                  │             │                  │
└──────────────────┘             └──────────────────┘
```

```php
// Lending emituje (BorrowBookCommand)
$this->eventPublisher->publish(new BookBorrowedEvent($bookId, $userId, $loanId));

// Catalog nasłuchuje (UpdateBookPopularityOnBookBorrowed)
#[AsMessageHandler(bus: 'event.bus')]
class UpdateBookPopularityOnBookBorrowed
{
    public function __invoke(BookBorrowedEvent $event): void { }
}
```

**Korzyści:**
- Lending nie wie, że Catalog istnieje
- Można dodawać nowe handlery bez zmiany Lending
- Luźne powiązanie między modułami

---

### Co można dodać:

1. **CQRS - osobne modele read/write**

   Obecnie Query zwraca encje domenowe. W pełnym CQRS:
   ```
   Command: Book (pełna encja z logiką biznesową)
   Query:   BookReadModel (prosty DTO zoptymalizowany do wyświetlania)
   ```

   Korzyść: Query może czytać z osobnej, zdenormalizowanej bazy (np. Elasticsearch).

2. **Więcej Domain Events**
   - `BookReturnedEvent` - gdy książka zostanie zwrócona
   - `LoanOverdueEvent` - gdy minie termin zwrotu
   - `UserRegisteredEvent` - gdy dołączy nowy użytkownik

3. **Implementacja pozostałych Bounded Contexts**
   - Catalog: wyszukiwanie, metadane, recenzje (częściowo zaimplementowany - EventHandler)
   - Membership: rejestracja, typy członkostwa
   - Acquisition: zakupy, dostawcy

4. **Testy jednostkowe** dla całej domeny

5. **Testy integracyjne** dla repozytoriów

---

## Podsumowanie

### Architektura hexagonalna zapewnia:

| Korzyść | Jak to osiągamy |
|---------|-----------------|
| **Testowalność** | Domena nie zależy od infrastruktury |
| **Wymienność** | Interfejsy w domenie, implementacje w infrastrukturze |
| **Czytelność** | Logika biznesowa w jednym miejscu |
| **Skalowalność** | Bounded Contexts dzielą system na moduły |
| **Utrzymywalność** | Jasny podział odpowiedzialności |

### Kluczowe zasady:

1. **Domena jest najważniejsza** - reszta to szczegóły implementacyjne
2. **Zależności wskazują do środka** - infrastruktura zależy od domeny, nie odwrotnie
3. **Interfejsy definiują kontrakty** - porty mówią CO, adaptery JAK
4. **Bounded Contexts izolują modele** - każdy kontekst ma własne rozumienie domeny

---

*Projekt demonstruje architekturę hexagonalną z podziałem na Bounded Contexts w praktycznym przykładzie systemu biblioteki online.*
