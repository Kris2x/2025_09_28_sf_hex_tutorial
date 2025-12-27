# Bounded Contexts - Podział na moduły biznesowe

[< Powrót do README](../../README.md)

## Spis treści
- [Czym jest Bounded Context?](#czym-jest-bounded-context)
- [Problem: Ubiquitous Language](#problem-ubiquitous-language)
- [Bounded Contexts w tym projekcie](#bounded-contexts-w-tym-projekcie)
- [Komunikacja między kontekstami](#komunikacja-między-kontekstami)
- [Anti-Corruption Layer](#anti-corruption-layer)
- [Praktyczne wskazówki](#praktyczne-wskazówki)

---

## Czym jest Bounded Context?

**Bounded Context** to centralna koncepcja Domain-Driven Design (DDD). Jest to **granica**, w której dany model domenowy ma **spójne i jednoznaczne znaczenie**.

> **"A Bounded Context is a semantic contextual boundary. Within the boundary, elements of a model mean specific things."**
> — Vaughn Vernon, "Implementing Domain-Driven Design"

### Kluczowe cechy

1. **Jasna granica** - wiadomo co należy do kontekstu, a co nie
2. **Spójny język** - te same słowa mają to samo znaczenie
3. **Własny model** - encje dopasowane do potrzeb kontekstu
4. **Autonomia** - kontekst może działać niezależnie

---

## Problem: Ubiquitous Language

### To samo słowo, różne znaczenia

W systemie bibliotecznym słowo **"Książka"** może oznaczać zupełnie różne rzeczy:

```
📚 Dla bibliotekarza katalogującego (CATALOG):
   - Tytuł, autor, ISBN
   - Opis, recenzje, okładka
   - Kategorie, tagi
   - Data publikacji

📖 Dla systemu wypożyczeń (LENDING):
   - ID książki
   - Czy jest dostępna?
   - Kto wypożyczył?
   - Kiedy termin zwrotu?

💰 Dla działu zakupów (ACQUISITION):
   - Cena zakupu
   - Dostawca
   - Numer faktury
   - Data dostawy

👥 Dla działu członkostwa (MEMBERSHIP):
   - Które książki pożyczał użytkownik?
   - Historia wypożyczeń
   - Ulubione gatunki
```

### God Object Anti-Pattern

Próba stworzenia jednej encji `Book` dla wszystkich przypadków:

```php
// ❌ "God Object" - encja, która wie wszystko
class Book
{
    // Dane podstawowe
    private $id;
    private $title;
    private $author;
    private $isbn;

    // Katalog
    private $description;
    private $coverImage;
    private $reviews;
    private $categories;
    private $tags;

    // Wypożyczenia
    private $isAvailable;
    private $borrowedBy;
    private $dueDate;
    private $reservations;

    // Zakupy
    private $purchasePrice;
    private $supplier;
    private $invoiceNumber;
    private $deliveryDate;

    // Statystyki
    private $borrowCount;
    private $popularity;
    private $rating;

    // ... 50 pól później ...

    // Metody dla każdego kontekstu
    public function borrow(): void { }
    public function return(): void { }
    public function addToCategory(): void { }
    public function calculatePopularity(): void { }
    public function updatePurchaseInfo(): void { }
    // ... 100 metod później ...
}
```

**Problemy:**
- Trudna do zrozumienia i utrzymania
- Zmiany w jednym kontekście wpływają na inne
- Wszystkie testy muszą znać całą encję
- Naruszenie Single Responsibility Principle

---

## Bounded Contexts w tym projekcie

### Rozwiązanie: Osobne modele

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
│           └── CatalogBook.php  ← Book z: title, description, categories
│
├── Acquisition/       # Kontekst: Zakupy (TODO)
│   └── Domain/
│       └── Entity/
│           └── PurchasedBook.php  ← Book z: price, supplier, invoice
│
└── Membership/        # Kontekst: Członkostwo (TODO)
    └── Domain/
        └── Entity/
            └── BorrowingHistory.php  ← Historia z: userId, books[], dates
```

### Lending BC: Book

```php
namespace App\Lending\Domain\Entity;

class Book
{
    private BookId $id;
    private string $title;
    private string $author;
    private string $isbn;
    private bool $isAvailable = true;

    public function borrow(): void
    {
        if (!$this->isAvailable) {
            throw new \DomainException('Book is not available');
        }
        $this->isAvailable = false;
    }

    public function return(): void
    {
        $this->isAvailable = true;
    }
}
```

### Catalog BC: CatalogBook

```php
namespace App\Catalog\Domain\Entity;

class CatalogBook
{
    private CatalogBookId $id;
    private string $title;
    private Isbn $isbn;
    private Author $author;
    private ?string $description;
    private int $popularity = 0;
    private Collection $categories;

    public function incrementPopularity(): void
    {
        $this->popularity++;
    }

    public function addCategory(Category $category): void
    {
        $this->categories->add($category);
    }
}
```

### Porównanie

| Aspekt | Lending.Book | Catalog.CatalogBook |
|--------|--------------|---------------------|
| **Cel** | Zarządzanie dostępnością | Prezentacja metadanych |
| **Pola** | id, title, isAvailable | id, title, description, categories |
| **Metody** | borrow(), return() | incrementPopularity(), addCategory() |
| **Relacje** | Loan, User | Author, Category |

### Tabela kontekstów

| Kontekst | Odpowiedzialność | Encje | Status |
|----------|------------------|-------|--------|
| **Lending** | Wypożyczenia, zwroty, kary | Book, User, Loan | ✅ |
| **Catalog** | Przeglądanie, wyszukiwanie, metadane | CatalogBook, Author, Category | ✅ |
| **Shared** | Eventy, kontrakty między BC | - | ✅ |
| **Membership** | Członkostwo, karty biblioteczne | Member, LibraryCard | 📋 TODO |
| **Acquisition** | Zakupy, dostawcy, faktury | PurchaseOrder, Supplier | 📋 TODO |

---

## Komunikacja między kontekstami

Konteksty muszą się komunikować, ale nie powinny być ściśle powiązane. Główne metody:

### 1. Domain Events (zalecane)

```
┌──────────────────┐                      ┌──────────────────┐
│     CATALOG      │                      │     LENDING      │
│                  │                      │                  │
│ AddBookToCalog   │──BookAddedToCatalog─►│ CreateBook       │
│ CommandHandler   │      Event           │ EventHandler     │
│                  │                      │                  │
│ UpdatePopularity │◄─BookBorrowedEvent───│ BorrowBook       │
│ EventHandler     │                      │ CommandHandler   │
└──────────────────┘                      └──────────────────┘
```

**Zalety:**
- Luźne powiązanie (loose coupling)
- Można dodawać handlery bez zmiany emitenta
- Naturalnie asynchroniczne

**Przykład:**

```php
// Catalog emituje event
$this->eventPublisher->publish(
    new BookAddedToCatalogEvent(
        $catalogBook->id()->value(),
        $catalogBook->title(),
        $catalogBook->isbn()->value(),
        $author->firstName() . ' ' . $author->lastName()
    )
);

// Lending nasłuchuje i tworzy swoją wersję Book
class CreateBookOnBookAddedToCatalog
{
    public function __invoke(BookAddedToCatalogEvent $event): void
    {
        $book = new Book(
            new BookId($event->bookId()),
            $event->title(),
            $event->authorName(),
            $event->isbn(),
            new DateTimeImmutable()
        );

        $this->bookRepository->save($book);
    }
}
```

### 2. Shared Kernel

Współdzielone Value Objects i interfejsy:

```php
// Shared/Contract/BookInfoProviderInterface.php
interface BookInfoProviderInterface
{
    public function getBookInfo(string $bookId): ?BookInfoDto;
}

// Catalog implementuje jako adapter
class CatalogBookInfoProvider implements BookInfoProviderInterface
{
    public function getBookInfo(string $bookId): ?BookInfoDto
    {
        $book = $this->repository->findById(new CatalogBookId($bookId));

        return $book ? new BookInfoDto(
            $book->id()->value(),
            $book->title(),
            $book->author()->fullName()
        ) : null;
    }
}

// Lending używa przez interfejs
class SomeService
{
    public function __construct(
        private BookInfoProviderInterface $bookInfoProvider
    ) {}
}
```

### 3. API Calls (dla rozproszonych systemów)

```php
// Konteksty jako osobne mikroserwisy
class RemoteBookInfoProvider implements BookInfoProviderInterface
{
    public function getBookInfo(string $bookId): ?BookInfoDto
    {
        $response = $this->httpClient->get("/api/catalog/books/{$bookId}");

        return BookInfoDto::fromArray($response->json());
    }
}
```

---

## Anti-Corruption Layer

### Problem: Zewnętrzne systemy mają inne modele

Gdy integrujesz się z zewnętrznym systemem (np. stary legacy system, zewnętrzne API), ich model może być zupełnie inny od twojego.

### Rozwiązanie: ACL jako "tłumacz"

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│                 │     │                 │     │                 │
│  Twoja Domena   │────►│      ACL        │────►│ Zewnętrzny      │
│                 │     │  (Translator)   │     │ System          │
│  Book           │     │                 │     │ BookRecord      │
│                 │     │                 │     │                 │
└─────────────────┘     └─────────────────┘     └─────────────────┘
```

**Przykład:**

```php
// Zewnętrzny system ma inny model
class LegacyBookRecord
{
    public int $BOOK_ID;
    public string $BOOK_TITLE;
    public int $IS_AVAILABLE_FLAG;  // 0 lub 1
    public string $BORROWER_ID;
}

// ACL tłumaczy na nasz model
class LegacyBookAdapter
{
    public function toDomainBook(LegacyBookRecord $record): Book
    {
        return new Book(
            new BookId((string) $record->BOOK_ID),
            $record->BOOK_TITLE,
            '', // author - legacy nie ma
            '', // isbn - legacy nie ma
            new DateTimeImmutable()
        );
    }

    public function toLegacyRecord(Book $book): LegacyBookRecord
    {
        $record = new LegacyBookRecord();
        $record->BOOK_ID = (int) $book->id()->value();
        $record->BOOK_TITLE = $book->title();
        $record->IS_AVAILABLE_FLAG = $book->isAvailable() ? 1 : 0;

        return $record;
    }
}
```

---

## Praktyczne wskazówki

### Jak identyfikować Bounded Contexts?

1. **Słuchaj języka ekspertów domenowych**
   - Czy używają tych samych słów w różnych znaczeniach?
   - Czy różne zespoły/działy mają różne perspektywy?

2. **Szukaj granic odpowiedzialności**
   - Kto jest odpowiedzialny za jakie dane?
   - Które procesy biznesowe są niezależne?

3. **Analizuj zmiany**
   - Czy zmiany w jednym obszarze wpływają na inne?
   - Co można zmienić bez wpływu na resztę systemu?

### Błędy do uniknięcia

| Błąd | Opis | Rozwiązanie |
|------|------|-------------|
| **Zbyt duże BC** | Wszystko w jednym kontekście | Szukaj naturalnych granic |
| **Zbyt małe BC** | Każda encja to osobny kontekst | Grupuj powiązane koncepcje |
| **Współdzielone encje** | Ta sama encja w wielu BC | Każdy BC ma własny model |
| **Synchroniczna komunikacja** | BC wywołuje BC bezpośrednio | Używaj eventów |
| **Ignorowanie języka** | Techniczne nazwy zamiast biznesowych | Ubiquitous Language |

### Struktura katalogów

```
src/
├── Lending/                    # Bounded Context
│   ├── Domain/                 # Model domenowy
│   ├── Application/            # Use cases
│   ├── Infrastructure/         # Adaptery
│   └── Presentation/           # Controllers
│
├── Catalog/                    # Kolejny BC
│   ├── Domain/
│   ├── Application/
│   ├── Infrastructure/
│   └── Presentation/
│
└── Shared/                     # Współdzielone
    ├── Domain/                 # Interfejsy eventów
    ├── Contract/               # Kontrakty między BC
    └── Infrastructure/         # Messenger
```

---

## Następne kroki

- [Warstwy aplikacji](layers.md) - Szczegóły Domain, Application, Infrastructure
- [Domain Events](../cqrs/events.md) - Komunikacja przez eventy
- [Porty i Adaptery](ports-and-adapters.md) - Implementacja granic

[< Powrót do README](../../README.md)
