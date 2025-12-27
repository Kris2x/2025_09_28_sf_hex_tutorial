# API Reference

[< Powrót do README](../README.md)

## Spis treści
- [Przegląd](#przegląd)
- [Lending BC](#lending-bc)
- [Catalog BC](#catalog-bc)
- [Kody błędów](#kody-błędów)

---

## Przegląd

### Base URL

```
http://localhost:8000/api
```

### Format

- **Content-Type:** `application/json`
- **Metody:** GET, POST
- **Błędy:** JSON z polem `error`

### Bounded Contexts

| BC | Prefix | Opis |
|----|--------|------|
| Lending | `/api/books` | Wypożyczenia i zwroty |
| Catalog | `/api/catalog` | Katalog, wyszukiwanie, kategorie |

---

## Lending BC

### GET /api/books/ - Lista dostępnych książek

Zwraca listę wszystkich książek dostępnych do wypożyczenia.

**Request:**
```bash
curl http://localhost:8000/api/books/
```

**Response (200 OK):**
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

**Pola odpowiedzi:**

| Pole | Typ | Opis |
|------|-----|------|
| `id` | string | Unikalny identyfikator książki |
| `title` | string | Tytuł książki |
| `author` | string | Autor książki |
| `isbn` | string | Numer ISBN |
| `available` | boolean | Czy dostępna do wypożyczenia |

---

### POST /api/books/{id}/borrow - Wypożycz książkę

Wypożycza książkę dla użytkownika.

**Request:**
```bash
curl -X POST http://localhost:8000/api/books/book-1/borrow \
  -H "Content-Type: application/json" \
  -d '{"userId": "user-1"}'
```

**Parametry URL:**

| Parametr | Typ | Wymagany | Opis |
|----------|-----|----------|------|
| `id` | string | Tak | ID książki do wypożyczenia |

**Body:**

| Pole | Typ | Wymagany | Opis |
|------|-----|----------|------|
| `userId` | string | Tak | ID użytkownika wypożyczającego |

**Response (200 OK):**
```json
{
    "message": "Book borrowed successfully"
}
```

**Response (400 Bad Request):**
```json
{
    "error": "Book is not available for borrowing"
}
```

**Możliwe błędy:**

| Błąd | Opis |
|------|------|
| `userId is required` | Brak userId w body |
| `Book not found` | Książka o podanym ID nie istnieje |
| `User not found` | Użytkownik o podanym ID nie istnieje |
| `Book is not available for borrowing` | Książka już wypożyczona |
| `User cannot borrow more books` | Przekroczony limit wypożyczeń |

---

### POST /api/books/{id}/return - Zwróć książkę

Zwraca wypożyczoną książkę.

**Request:**
```bash
curl -X POST http://localhost:8000/api/books/book-1/return \
  -H "Content-Type: application/json" \
  -d '{"userId": "user-1"}'
```

**Parametry URL:**

| Parametr | Typ | Wymagany | Opis |
|----------|-----|----------|------|
| `id` | string | Tak | ID książki do zwrotu |

**Body:**

| Pole | Typ | Wymagany | Opis |
|------|-----|----------|------|
| `userId` | string | Tak | ID użytkownika zwracającego |

**Response (200 OK):**
```json
{
    "message": "Book returned successfully",
    "fine": 0.0
}
```

**Response z karą (200 OK):**
```json
{
    "message": "Book returned successfully",
    "fine": 5.50
}
```

**Pola odpowiedzi:**

| Pole | Typ | Opis |
|------|-----|------|
| `message` | string | Komunikat sukcesu |
| `fine` | float | Kara za opóźnienie (0 jeśli na czas) |

**Możliwe błędy:**

| Błąd | Opis |
|------|------|
| `userId is required` | Brak userId w body |
| `Book not found` | Książka o podanym ID nie istnieje |
| `Book is already available` | Książka nie była wypożyczona |
| `Loan not found` | Brak aktywnego wypożyczenia |

---

## Catalog BC

### GET /api/catalog/books - Wyszukaj książki

Wyszukuje książki w katalogu. Bez parametrów zwraca najpopularniejsze.

**Request - najpopularniejsze:**
```bash
curl http://localhost:8000/api/catalog/books
```

**Request - wyszukiwanie:**
```bash
curl "http://localhost:8000/api/catalog/books?q=wzorce"
```

**Request - po kategorii:**
```bash
curl "http://localhost:8000/api/catalog/books?category=programming"
```

**Request - po autorze:**
```bash
curl "http://localhost:8000/api/catalog/books?author=author-1"
```

**Query Parameters:**

| Parametr | Typ | Wymagany | Opis |
|----------|-----|----------|------|
| `q` | string | Nie | Szukaj w tytule/opisie |
| `category` | string | Nie | Filtruj po slug kategorii |
| `author` | string | Nie | Filtruj po ID autora |

**Response (200 OK):**
```json
[
    {
        "id": "book-1",
        "title": "Wzorce projektowe",
        "author": "Erich Gamma",
        "isbn": "978-83-246-1493-0",
        "description": "Klasyka wzorców projektowych",
        "popularity": 15,
        "publishedAt": "1994-10-01",
        "categories": [
            {
                "slug": "programming",
                "name": "Programowanie"
            }
        ]
    }
]
```

**Pola odpowiedzi:**

| Pole | Typ | Opis |
|------|-----|------|
| `id` | string | ID książki |
| `title` | string | Tytuł |
| `author` | string | Imię i nazwisko autora |
| `isbn` | string | Numer ISBN |
| `description` | string\|null | Opis książki |
| `popularity` | integer | Liczba wypożyczeń |
| `publishedAt` | string | Data publikacji (YYYY-MM-DD) |
| `categories` | array | Lista kategorii |

---

### GET /api/catalog/books/{id} - Szczegóły książki

Zwraca pełne informacje o książce.

**Request:**
```bash
curl http://localhost:8000/api/catalog/books/book-1
```

**Response (200 OK):**
```json
{
    "id": "book-1",
    "title": "Wzorce projektowe",
    "author": {
        "id": "author-1",
        "name": "Erich Gamma",
        "biography": "Szwajcarski informatyk, współautor GoF..."
    },
    "isbn": "978-83-246-1493-0",
    "description": "Klasyka wzorców projektowych. Książka przedstawia 23 wzorce...",
    "popularity": 15,
    "publishedAt": "1994-10-01",
    "createdAt": "2024-01-15 10:30:00",
    "categories": [
        {
            "slug": "programming",
            "name": "Programowanie",
            "path": "Programowanie"
        },
        {
            "slug": "design-patterns",
            "name": "Wzorce projektowe",
            "path": "Programowanie > Wzorce projektowe"
        }
    ]
}
```

**Response (404 Not Found):**
```json
{
    "error": "Book not found"
}
```

---

### POST /api/catalog/books - Dodaj książkę

Dodaje nową książkę do katalogu. Automatycznie synchronizuje z Lending BC.

**Request:**
```bash
curl -X POST http://localhost:8000/api/catalog/books \
  -H "Content-Type: application/json" \
  -d '{
    "bookId": "book-new",
    "title": "Domain-Driven Design",
    "isbn": "978-0-321-12521-5",
    "authorId": "author-2",
    "authorFirstName": "Eric",
    "authorLastName": "Evans",
    "publishedAt": "2003-08-30",
    "description": "Tackling Complexity in the Heart of Software"
  }'
```

**Body:**

| Pole | Typ | Wymagany | Opis |
|------|-----|----------|------|
| `bookId` | string | Tak | Unikalny ID dla książki |
| `title` | string | Tak | Tytuł książki |
| `isbn` | string | Tak | Numer ISBN |
| `authorId` | string | Tak | ID autora (nowy lub istniejący) |
| `authorFirstName` | string | Tak | Imię autora |
| `authorLastName` | string | Tak | Nazwisko autora |
| `publishedAt` | string | Tak | Data publikacji (YYYY-MM-DD) |
| `description` | string | Nie | Opis książki |

**Response (200 OK):**
```json
{
    "message": "Book added to catalog",
    "bookId": "book-new"
}
```

**Efekty uboczne:**
- Emitowany jest `BookAddedToCatalogEvent`
- Lending BC tworzy swoją wersję Book (przez EventHandler)

**Możliwe błędy:**

| Błąd | Opis |
|------|------|
| `bookId is required` | Brak bookId |
| `title is required` | Brak tytułu |
| `isbn is required` | Brak ISBN |
| `Invalid ISBN format` | Nieprawidłowy format ISBN |
| `Book with this ISBN already exists` | ISBN już istnieje |

---

### GET /api/catalog/categories - Lista kategorii

Zwraca hierarchiczną listę kategorii.

**Request:**
```bash
curl http://localhost:8000/api/catalog/categories
```

**Response (200 OK):**
```json
[
    {
        "id": "cat-1",
        "slug": "programming",
        "name": "Programowanie",
        "hasChildren": true,
        "children": [
            {
                "id": "cat-2",
                "slug": "php",
                "name": "PHP",
                "hasChildren": false,
                "children": []
            },
            {
                "id": "cat-3",
                "slug": "python",
                "name": "Python",
                "hasChildren": false,
                "children": []
            },
            {
                "id": "cat-4",
                "slug": "design-patterns",
                "name": "Wzorce projektowe",
                "hasChildren": false,
                "children": []
            }
        ]
    },
    {
        "id": "cat-5",
        "slug": "fiction",
        "name": "Literatura piękna",
        "hasChildren": false,
        "children": []
    }
]
```

**Pola odpowiedzi:**

| Pole | Typ | Opis |
|------|-----|------|
| `id` | string | ID kategorii |
| `slug` | string | Slug URL-friendly |
| `name` | string | Nazwa wyświetlana |
| `hasChildren` | boolean | Czy ma podkategorie |
| `children` | array | Lista podkategorii |

---

## Kody błędów

### HTTP Status Codes

| Code | Opis | Kiedy |
|------|------|-------|
| `200 OK` | Sukces | Operacja zakończona pomyślnie |
| `400 Bad Request` | Błąd walidacji | Brak wymaganych pól, błąd domenowy |
| `404 Not Found` | Nie znaleziono | Zasób nie istnieje |
| `500 Internal Server Error` | Błąd serwera | Nieoczekiwany błąd |

### Format błędu

```json
{
    "error": "Opis błędu"
}
```

### Przykłady błędów domenowych

```json
// Próba wypożyczenia niedostępnej książki
{
    "error": "Book is not available for borrowing"
}

// Przekroczony limit wypożyczeń
{
    "error": "User cannot borrow more books"
}

// Zwrot książki która nie była wypożyczona
{
    "error": "Book is already available"
}

// Nieprawidłowy format ISBN
{
    "error": "Invalid ISBN format: 123"
}
```

---

## Testowanie API

### Przykładowy scenariusz

```bash
# 1. Dodaj książkę do katalogu
curl -X POST http://localhost:8000/api/catalog/books \
  -H "Content-Type: application/json" \
  -d '{
    "bookId": "book-test",
    "title": "Test Book",
    "isbn": "978-0-000-00000-0",
    "authorId": "author-test",
    "authorFirstName": "John",
    "authorLastName": "Doe",
    "publishedAt": "2024-01-01"
  }'

# 2. Sprawdź czy jest w Lending (po synchronizacji)
curl http://localhost:8000/api/books/

# 3. Wypożycz książkę
curl -X POST http://localhost:8000/api/books/book-test/borrow \
  -H "Content-Type: application/json" \
  -d '{"userId": "user-1"}'

# 4. Sprawdź że jest niedostępna
curl http://localhost:8000/api/books/

# 5. Zwróć książkę
curl -X POST http://localhost:8000/api/books/book-test/return \
  -H "Content-Type: application/json" \
  -d '{"userId": "user-1"}'

# 6. Sprawdź popularność w katalogu (powinna być 1)
curl http://localhost:8000/api/catalog/books/book-test
```

---

[< Powrót do README](../README.md)
