# Testowanie

[< Powrót do README](../README.md)

## Spis treści
- [Strategia testowania](#strategia-testowania)
- [Struktura testów](#struktura-testów)
- [Testy jednostkowe domeny](#testy-jednostkowe-domeny)
- [Testy handlerów](#testy-handlerów)
- [Testy integracyjne](#testy-integracyjne)
- [Testy funkcjonalne](#testy-funkcjonalne)
- [In-Memory Repositories](#in-memory-repositories)

---

## Strategia testowania

Architektura hexagonalna ułatwia testowanie poprzez separację warstw:

```
┌─────────────────────────────────────────────────────────────────┐
│                                                                 │
│   PIRAMIDA TESTÓW                                               │
│                                                                 │
│                        ┌───────────┐                            │
│                        │ E2E/      │ ← Mało, wolne             │
│                        │ Functional│                            │
│                        └─────┬─────┘                            │
│                    ┌─────────┴─────────┐                        │
│                    │   Integration     │ ← Średnio              │
│                    │   (Repositories)  │                        │
│                    └─────────┬─────────┘                        │
│          ┌───────────────────┴───────────────────┐              │
│          │          Unit Tests                    │ ← Dużo,     │
│          │   (Domain, Handlers, Value Objects)   │   szybkie   │
│          └───────────────────────────────────────┘              │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Podział testów

| Typ | Co testuje | Zależności | Szybkość |
|-----|------------|------------|----------|
| **Unit** | Domena, Handlers, VOs | Żadne / Mocki | Bardzo szybkie |
| **Integration** | Repozytoria, Doctrine | Baza danych | Średnie |
| **Functional** | API endpoints | Cała aplikacja | Wolne |

---

## Struktura testów

```
tests/
├── Unit/                           # Testy bez I/O
│   ├── Lending/
│   │   ├── Domain/
│   │   │   ├── Entity/
│   │   │   │   ├── BookTest.php
│   │   │   │   ├── UserTest.php
│   │   │   │   └── LoanTest.php
│   │   │   └── ValueObject/
│   │   │       ├── BookIdTest.php
│   │   │       └── EmailTest.php
│   │   └── Application/
│   │       └── Command/
│   │           ├── BorrowBookCommandHandlerTest.php
│   │           └── ReturnBookCommandHandlerTest.php
│   │
│   └── Catalog/
│       ├── Domain/
│       │   └── ValueObject/
│       │       └── IsbnTest.php
│       └── Application/
│           └── Command/
│               └── AddBookToCatalogCommandHandlerTest.php
│
├── Integration/                    # Testy z bazą danych
│   ├── Lending/
│   │   └── Repository/
│   │       ├── DoctrineBookRepositoryTest.php
│   │       └── DoctrineLoanRepositoryTest.php
│   └── Catalog/
│       └── Repository/
│           └── DoctrineCatalogBookRepositoryTest.php
│
└── Functional/                     # Testy HTTP end-to-end
    ├── Lending/
    │   └── Controller/
    │       └── BookControllerTest.php
    └── Catalog/
        └── Controller/
            └── CatalogControllerTest.php
```

---

## Testy jednostkowe domeny

Testy domeny są **najważniejsze** - testują logikę biznesową bez żadnych zależności.

### Test encji Book

```php
namespace App\Tests\Unit\Lending\Domain\Entity;

use App\Lending\Domain\Entity\Book;
use App\Lending\Domain\ValueObject\BookId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class BookTest extends TestCase
{
    private function createBook(): Book
    {
        return new Book(
            new BookId('book-1'),
            'Test Title',
            'Test Author',
            '978-0-000-00000-0',
            new DateTimeImmutable()
        );
    }

    public function testNewBookIsAvailable(): void
    {
        $book = $this->createBook();

        $this->assertTrue($book->isAvailable());
    }

    public function testCanBorrowAvailableBook(): void
    {
        $book = $this->createBook();

        $book->borrow();

        $this->assertFalse($book->isAvailable());
    }

    public function testCannotBorrowUnavailableBook(): void
    {
        $book = $this->createBook();
        $book->borrow();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Book is not available for borrowing');

        $book->borrow(); // Druga próba
    }

    public function testCanReturnBorrowedBook(): void
    {
        $book = $this->createBook();
        $book->borrow();

        $book->return();

        $this->assertTrue($book->isAvailable());
    }

    public function testCannotReturnAvailableBook(): void
    {
        $book = $this->createBook();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Book is already available');

        $book->return();
    }
}
```

### Test Value Object

```php
namespace App\Tests\Unit\Lending\Domain\ValueObject;

use App\Lending\Domain\ValueObject\Email;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class EmailTest extends TestCase
{
    public function testValidEmail(): void
    {
        $email = new Email('test@example.com');

        $this->assertSame('test@example.com', $email->value());
    }

    public function testInvalidEmailThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email format');

        new Email('not-an-email');
    }

    public function testEmptyEmailThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Email('');
    }

    public function testEquality(): void
    {
        $email1 = new Email('test@example.com');
        $email2 = new Email('test@example.com');
        $email3 = new Email('other@example.com');

        $this->assertTrue($email1->equals($email2));
        $this->assertFalse($email1->equals($email3));
    }
}
```

### Test BookId

```php
namespace App\Tests\Unit\Lending\Domain\ValueObject;

use App\Lending\Domain\ValueObject\BookId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BookIdTest extends TestCase
{
    public function testValidBookId(): void
    {
        $bookId = new BookId('book-123');

        $this->assertSame('book-123', $bookId->value());
    }

    public function testEmptyBookIdThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('BookId cannot be empty');

        new BookId('');
    }

    public function testToString(): void
    {
        $bookId = new BookId('book-123');

        $this->assertSame('book-123', (string) $bookId);
    }
}
```

---

## Testy handlerów

Handlery testujemy z **mockami** repozytoriów.

### Test BorrowBookCommandHandler

```php
namespace App\Tests\Unit\Lending\Application\Command;

use App\Lending\Application\Command\BorrowBookCommand;
use App\Lending\Application\Command\BorrowBookCommandHandler;
use App\Lending\Domain\Entity\Book;
use App\Lending\Domain\Entity\User;
use App\Lending\Domain\Repository\BookRepositoryInterface;
use App\Lending\Domain\Repository\LoanRepositoryInterface;
use App\Lending\Domain\Repository\UserRepositoryInterface;
use App\Lending\Domain\ValueObject\BookId;
use App\Lending\Domain\ValueObject\Email;
use App\Lending\Domain\ValueObject\UserId;
use App\Shared\Domain\Event\EventPublisherInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class BorrowBookCommandHandlerTest extends TestCase
{
    private BookRepositoryInterface $bookRepository;
    private UserRepositoryInterface $userRepository;
    private LoanRepositoryInterface $loanRepository;
    private EventPublisherInterface $eventPublisher;
    private BorrowBookCommandHandler $handler;

    protected function setUp(): void
    {
        $this->bookRepository = $this->createMock(BookRepositoryInterface::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->loanRepository = $this->createMock(LoanRepositoryInterface::class);
        $this->eventPublisher = $this->createMock(EventPublisherInterface::class);

        $this->handler = new BorrowBookCommandHandler(
            $this->bookRepository,
            $this->userRepository,
            $this->loanRepository,
            $this->eventPublisher
        );
    }

    public function testHandleSuccessfully(): void
    {
        // Arrange
        $user = new User(
            new UserId('user-1'),
            'Jan Kowalski',
            new Email('jan@test.pl'),
            new DateTimeImmutable()
        );

        $book = new Book(
            new BookId('book-1'),
            'Test Book',
            'Test Author',
            '978-0-000-00000-0',
            new DateTimeImmutable()
        );

        $this->userRepository
            ->method('findById')
            ->willReturn($user);

        $this->bookRepository
            ->method('findById')
            ->willReturn($book);

        $this->loanRepository
            ->expects($this->once())
            ->method('save');

        $this->eventPublisher
            ->expects($this->once())
            ->method('publish');

        // Act
        $command = new BorrowBookCommand('user-1', 'book-1');
        ($this->handler)($command);

        // Assert
        $this->assertFalse($book->isAvailable());
        $this->assertEquals(1, $user->activeLoanCount());
    }

    public function testThrowsWhenUserNotFound(): void
    {
        $this->userRepository
            ->method('findById')
            ->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('User not found');

        $command = new BorrowBookCommand('user-1', 'book-1');
        ($this->handler)($command);
    }

    public function testThrowsWhenBookNotFound(): void
    {
        $user = new User(
            new UserId('user-1'),
            'Jan',
            new Email('jan@test.pl'),
            new DateTimeImmutable()
        );

        $this->userRepository
            ->method('findById')
            ->willReturn($user);

        $this->bookRepository
            ->method('findById')
            ->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Book not found');

        $command = new BorrowBookCommand('user-1', 'book-1');
        ($this->handler)($command);
    }

    public function testThrowsWhenBookNotAvailable(): void
    {
        $user = new User(
            new UserId('user-1'),
            'Jan',
            new Email('jan@test.pl'),
            new DateTimeImmutable()
        );

        $book = new Book(
            new BookId('book-1'),
            'Test Book',
            'Author',
            'ISBN',
            new DateTimeImmutable()
        );
        $book->borrow(); // Już wypożyczona

        $this->userRepository
            ->method('findById')
            ->willReturn($user);

        $this->bookRepository
            ->method('findById')
            ->willReturn($book);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Book is not available');

        $command = new BorrowBookCommand('user-1', 'book-1');
        ($this->handler)($command);
    }
}
```

---

## Testy integracyjne

Testy repozytoriów z prawdziwą bazą danych.

### Test DoctrineBookRepository

```php
namespace App\Tests\Integration\Lending\Repository;

use App\Lending\Domain\Entity\Book;
use App\Lending\Domain\ValueObject\BookId;
use App\Lending\Infrastructure\Doctrine\Repository\DoctrineBookRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DoctrineBookRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private DoctrineBookRepository $repository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();

        $this->repository = new DoctrineBookRepository($this->entityManager);

        // Czyść bazę przed każdym testem
        $this->entityManager->createQuery('DELETE FROM App\Lending\Domain\Entity\Book')->execute();
    }

    public function testSaveAndFindById(): void
    {
        $book = new Book(
            new BookId('book-test'),
            'Test Title',
            'Test Author',
            '978-0-000-00000-0',
            new DateTimeImmutable()
        );

        $this->repository->save($book);

        $this->entityManager->clear(); // Wymuś odczyt z bazy

        $found = $this->repository->findById(new BookId('book-test'));

        $this->assertNotNull($found);
        $this->assertEquals('Test Title', $found->title());
        $this->assertTrue($found->isAvailable());
    }

    public function testFindAvailable(): void
    {
        $available = new Book(
            new BookId('book-1'),
            'Available Book',
            'Author',
            '978-0-000-00000-1',
            new DateTimeImmutable()
        );

        $borrowed = new Book(
            new BookId('book-2'),
            'Borrowed Book',
            'Author',
            '978-0-000-00000-2',
            new DateTimeImmutable()
        );
        $borrowed->borrow();

        $this->repository->save($available);
        $this->repository->save($borrowed);

        $this->entityManager->clear();

        $availableBooks = $this->repository->findAvailable();

        $this->assertCount(1, $availableBooks);
        $this->assertEquals('Available Book', $availableBooks[0]->title());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }
}
```

---

## Testy funkcjonalne

Testy HTTP end-to-end.

### Test BookController

```php
namespace App\Tests\Functional\Lending\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BookControllerTest extends WebTestCase
{
    public function testListAvailableBooks(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/books/');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testBorrowBook(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/books/book-1/borrow',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['userId' => 'user-1'])
        );

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('Book borrowed successfully', $data['message']);
    }

    public function testBorrowBookWithoutUserId(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/books/book-1/borrow',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([])
        );

        $this->assertResponseStatusCodeSame(400);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('userId is required', $data['error']);
    }

    public function testBorrowUnavailableBook(): void
    {
        $client = static::createClient();

        // Wypożycz pierwszy raz
        $client->request(
            'POST',
            '/api/books/book-1/borrow',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['userId' => 'user-1'])
        );

        // Próba drugi raz
        $client->request(
            'POST',
            '/api/books/book-1/borrow',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['userId' => 'user-2'])
        );

        $this->assertResponseStatusCodeSame(400);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('not available', $data['error']);
    }
}
```

---

## In-Memory Repositories

Dla szybszych testów jednostkowych.

### InMemoryBookRepository

```php
namespace App\Lending\Infrastructure\InMemory;

use App\Lending\Domain\Entity\Book;
use App\Lending\Domain\Repository\BookRepositoryInterface;
use App\Lending\Domain\ValueObject\BookId;

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
        return array_values(
            array_filter(
                $this->books,
                fn(Book $book) => $book->isAvailable()
            )
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

    // Helpery testowe
    public function clear(): void
    {
        $this->books = [];
    }

    public function count(): int
    {
        return count($this->books);
    }
}
```

### NullEventPublisher

```php
namespace App\Tests\Double;

use App\Shared\Domain\Event\DomainEventInterface;
use App\Shared\Domain\Event\EventPublisherInterface;

/**
 * Null Object - nie publikuje niczego.
 */
final class NullEventPublisher implements EventPublisherInterface
{
    /** @var DomainEventInterface[] */
    private array $events = [];

    public function publish(DomainEventInterface $event): void
    {
        $this->events[] = $event;
    }

    /** @return DomainEventInterface[] */
    public function getPublishedEvents(): array
    {
        return $this->events;
    }

    public function clear(): void
    {
        $this->events = [];
    }
}
```

---

## Uruchamianie testów

```bash
# Wszystkie testy
php bin/phpunit

# Tylko unit testy
php bin/phpunit tests/Unit

# Tylko testy domeny
php bin/phpunit tests/Unit/Lending/Domain

# Konkretny test
php bin/phpunit tests/Unit/Lending/Domain/Entity/BookTest.php

# Z coverage
php bin/phpunit --coverage-html coverage/
```

---

[< Powrót do README](../README.md)
