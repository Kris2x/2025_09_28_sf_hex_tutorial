# Architektura Hexagonalna w Symfony - Przykład Biblioteki Online

## Spis treści
- [Wprowadzenie](#wprowadzenie)
- [Architektura hexagonalna - podstawy](#architektura-hexagonalna---podstawy)
- [Struktura projektu](#struktura-projektu)
- [Warstwy aplikacji](#warstwy-aplikacji)
- [Przykład biznesowy](#przykład-biznesowy)
- [Implementacja](#implementacja)
- [Dependency Injection](#dependency-injection)
- [Przepływ danych](#przepływ-danych)
- [Zalety architektury](#zalety-architektury)
- [Testowanie](#testowanie)

## Wprowadzenie

Ten projekt demonstruje implementację **architektury hexagonalnej** (znanej również jako "Ports and Adapters") w frameworku Symfony. Jako przykład biznesowy wybrano system zarządzania biblioteką online z funkcjami wypożyczania i zwracania książek.

## Architektura hexagonalna - podstawy

### Czym jest architektura hexagonalna?

Architektura hexagonalna to wzorzec architektoniczny stworzony przez Alistaira Cockburna, który ma na celu:

- **Izolację logiki biznesowej** od szczegółów technicznych
- **Łatwość testowania** bez zależności zewnętrznych
- **Wymienność komponentów** infrastrukturalnych
- **Niezależność od frameworków** i bibliotek

### Kluczowe pojęcia

- **Domain (Domena)** - serce aplikacji zawierające logikę biznesową
- **Ports (Porty)** - interfejsy definiujące "co" aplikacja robi
- **Adapters (Adaptery)** - implementacje definiujące "jak" coś jest wykonywane

### Przepływ architektury

```
Świat zewnętrzny → Adapter → Port → Domain ← Port ← Adapter ← Świat zewnętrzny
    (HTTP)         (Controller) (Interface) (Logika) (Interface) (Repository) (Database)
```

## Struktura projektu

```
src/
├── Application/              # Warstwa aplikacji (Use Cases)
│   ├── Command/             # Command handlers (modyfikują stan)
│   │   ├── BorrowBookCommand.php
│   │   └── ReturnBookCommand.php
│   └── Query/               # Query handlers (odczytują dane)
│       ├── GetAvailableBooksQuery.php
│       └── GetUserLoansQuery.php
│
├── Domain/                  # Domena biznesowa (serce aplikacji)
│   ├── Entity/              # Encje domenowe
│   │   ├── Book.php
│   │   ├── User.php
│   │   └── Loan.php
│   ├── ValueObject/         # Value objects
│   │   ├── BookId.php
│   │   ├── UserId.php
│   │   └── Email.php
│   └── Repository/          # Repository interfaces (PORTS)
│       ├── BookRepositoryInterface.php
│       ├── UserRepositoryInterface.php
│       └── LoanRepositoryInterface.php
│
├── Infrastructure/          # Adaptery (szczegóły techniczne)
│   └── Doctrine/
│       └── Repository/      # Repository implementations
│           ├── DoctrineBookRepository.php
│           ├── DoctrineUserRepository.php
│           └── DoctrineLoanRepository.php
│
└── Presentation/            # Warstwa prezentacji
    └── Controller/          # Symfony controllers
        └── BookController.php
```

## Warstwy aplikacji

### 1. Domain Layer (Domena)

**Charakterystyka:**
- Czyste PHP klasy bez zewnętrznych zależności
- Zawiera logikę biznesową i reguły domenowe
- Enkapsulacja - stan zmieniany tylko przez metody biznesowe
- Walidacja reguł biznesowych

**Przykład encji:**

```php
class Book
{
    private bool $isAvailable = true;

    public function borrow(): void
    {
        if (!$this->isAvailable) {
            throw new \DomainException('Book is not available for borrowing');
        }
        $this->isAvailable = false;
    }
}
```

### 2. Application Layer (Aplikacja)

**Charakterystyka:**
- Orkiestruje logikę biznesową
- Implementuje Use Cases systemu
- Deleguje logikę do domeny
- Zarządza transakcjami

**Przykład Use Case:**

```php
class BorrowBookCommand
{
    public function execute(string $userId, string $bookId): void
    {
        $user = $this->userRepository->findById(new UserId($userId));
        $book = $this->bookRepository->findById(new BookId($bookId));

        // Sprawdź reguły biznesowe
        if (!$user->canBorrowBook()) {
            throw new \DomainException('User has reached maximum loan limit');
        }

        // Wykonaj operację biznesową
        $user->borrowBook();
        $book->borrow();

        // Zapisz zmiany
        $this->userRepository->save($user);
        $this->bookRepository->save($book);
    }
}
```

### 3. Infrastructure Layer (Infrastruktura)

**Charakterystyka:**
- Implementuje interfejsy z domeny
- Zawiera szczegóły techniczne (Doctrine, HTTP, Email)
- Nie zawiera logiki biznesowej
- Adaptery do zewnętrznych systemów

**Przykład implementacji:**

```php
class DoctrineBookRepository implements BookRepositoryInterface
{
    public function findById(BookId $id): ?Book
    {
        return $this->repository->find($id->value());
    }

    public function save(Book $book): void
    {
        $this->entityManager->persist($book);
        $this->entityManager->flush();
    }
}
```

### 4. Presentation Layer (Prezentacja)

**Charakterystyka:**
- Interfejsy użytkownika (REST API, Web, CLI)
- Przekłada żądania zewnętrzne na wywołania Use Cases
- Formatuje odpowiedzi
- Obsługuje błędy

## Przykład biznesowy

### Funkcjonalności systemu biblioteki:

1. **Dodawanie książek** do biblioteki
2. **Wypożyczanie książek** przez użytkowników
3. **Zwracanie książek** z obliczaniem kar
4. **Sprawdzanie dostępności** książek
5. **Historia wypożyczeń**

### Reguły biznesowe:

- Książka może być wypożyczona tylko jeśli jest dostępna
- Użytkownik może mieć maksymalnie 3 aktywne wypożyczenia
- Wypożyczenie trwa maksymalnie 14 dni
- Kara 0,50 zł za każdy dzień przetrzymania

## Implementacja

### Kluczowe klasy domenowe:

#### Book (Książka)
```php
class Book
{
    public function __construct(
        private BookId $id,
        private string $title,
        private string $author,
        private string $isbn,
        private DateTimeImmutable $publishedAt
    ) {}

    public function borrow(): void { /* logika biznesowa */ }
    public function return(): void { /* logika biznesowa */ }
    public function isAvailable(): bool { /* stan */ }
}
```

#### User (Użytkownik)
```php
class User
{
    private const MAX_ACTIVE_LOANS = 3;

    public function canBorrowBook(): bool
    {
        return $this->activeLoanCount < self::MAX_ACTIVE_LOANS;
    }

    public function borrowBook(): void { /* aktualizacja stanu */ }
}
```

#### Loan (Wypożyczenie)
```php
class Loan
{
    public function isOverdue(): bool
    {
        return $this->isActive() && new DateTimeImmutable() > $this->dueDate();
    }

    public function calculateFine(): float
    {
        $overdueDays = (new DateTimeImmutable())->diff($this->dueDate())->days;
        return $overdueDays * 0.50;
    }
}
```

## Dependency Injection

### Konfiguracja w services.yaml

```yaml
services:
    # Bind domain interfaces to infrastructure implementations
    App\Domain\Repository\BookRepositoryInterface:
        alias: App\Infrastructure\Doctrine\Repository\DoctrineBookRepository

    App\Domain\Repository\UserRepositoryInterface:
        alias: App\Infrastructure\Doctrine\Repository\DoctrineUserRepository

    # Application Services
    App\Application\Command\BorrowBookCommand:
        arguments:
            $bookRepository: '@App\Domain\Repository\BookRepositoryInterface'
            $userRepository: '@App\Domain\Repository\UserRepositoryInterface'
```

### Korzyści:
- **Wymienność** - zmiana implementacji tylko w konfiguracji
- **Testowanie** - łatwe mockowanie interfejsów
- **Separacja** - jasne oddzielenie warstw

## Przepływ danych

### Typowy request flow:

1. **HTTP Request** → `BookController`
2. **Controller** → `BorrowBookCommand` (Use Case)
3. **Use Case** → Domain entities (`User`, `Book`)
4. **Domain** → Repository interfaces
5. **Repositories** → Doctrine/Database
6. **Response** ← powrót przez te same warstwy

### Przykład kompletnego przepływu:

```
POST /api/books/{id}/borrow
    ↓
BookController::borrowBook()
    ↓
BorrowBookCommand::execute()
    ↓
UserRepository::findById() + BookRepository::findById()
    ↓
User::borrowBook() + Book::borrow()
    ↓
UserRepository::save() + BookRepository::save()
    ↓
JsonResponse z wynikiem
```

## Zalety architektury

### 1. **Testowalność**
- Domain można testować bez bazy danych
- Use Cases z mock'owanymi repositories
- Każda warstwa testowana niezależnie

### 2. **Wymienność technologii**
- Doctrine → MongoDB: zmiana tylko w Infrastructure
- REST → GraphQL: zmiana tylko w Presentation
- Logika biznesowa pozostaje nietknięta

### 3. **Czytelność kodu**
- Jasne oddzielenie odpowiedzialności
- Logika biznesowa w jednym miejscu
- Domenowy język w kodzie

### 4. **Skalowalność**
- Łatwe dodawanie nowych Use Cases
- Niezależny rozwój warstw
- Możliwość równoległej pracy zespołu

## Testowanie

### Struktura testów:

```
tests/
├── Unit/                    # Domain & Application tests
│   ├── Domain/
│   │   ├── Entity/
│   │   └── ValueObject/
│   └── Application/
│       ├── Command/
│       └── Query/
├── Integration/             # Infrastructure tests
│   └── Repository/
└── Functional/              # End-to-end tests
    └── Controller/
```

### Przykład testu domenowego:

```php
class BookTest extends TestCase
{
    public function testCannotBorrowUnavailableBook(): void
    {
        $book = new Book(/* ... */);
        $book->borrow(); // pierwsze wypożyczenie

        $this->expectException(\DomainException::class);
        $book->borrow(); // próba ponownego wypożyczenia
    }
}
```

### Przykład testu Use Case z mockami:

```php
class BorrowBookCommandTest extends TestCase
{
    public function testExecuteSuccessfully(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $bookRepository = $this->createMock(BookRepositoryInterface::class);

        $command = new BorrowBookCommand($bookRepository, $userRepository);

        // Test logiki bez zależności zewnętrznych
        $command->execute('user-1', 'book-1');
    }
}
```

---

## Podsumowanie

Architektura hexagonalna zapewnia:

- ✅ **Czystą logikę biznesową** niezależną od technologii
- ✅ **Łatwość testowania** bez bazy danych i zewnętrznych systemów
- ✅ **Wymienność komponentów** infrastrukturalnych
- ✅ **Skalowalność** i utrzymywalność kodu
- ✅ **Czytelność** i zgodność z domeną biznesową

Projekt demonstruje wszystkie kluczowe elementy tej architektury w praktycznym przykładzie systemu biblioteki online.