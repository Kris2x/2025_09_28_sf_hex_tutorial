# Catalog Bounded Context

## Obecna implementacja

Ten moduł obecnie zawiera **Event Handler** reagujący na zdarzenia z modułu Lending:

```
Catalog/
└── Application/
    └── EventHandler/
        └── UpdateBookPopularityOnBookBorrowed.php
```

### UpdateBookPopularityOnBookBorrowed

Handler nasłuchuje na `BookBorrowedEvent` emitowany przez Lending:

```php
#[AsMessageHandler(bus: 'event.bus')]
class UpdateBookPopularityOnBookBorrowed
{
    public function __invoke(BookBorrowedEvent $event): void
    {
        // Aktualizuje popularność książki w katalogu
    }
}
```

**Przykład luźnego powiązania:**
- Lending emituje event i nie wie, kto nasłuchuje
- Catalog reaguje na event bez bezpośredniej zależności od Lending

---

## TODO: Pełna implementacja

Ten moduł będzie odpowiedzialny za:
- Zarządzanie katalogiem książek (pełne metadane)
- Autorzy i ich biografie
- Kategorie i tagi
- Recenzje i oceny
- Wyszukiwanie w katalogu

## Planowana struktura

```
Catalog/
├── Domain/
│   ├── Entity/
│   │   ├── CatalogBook.php      # Książka z pełnymi metadanymi
│   │   ├── Author.php           # Autor
│   │   └── Category.php         # Kategoria
│   ├── ValueObject/
│   │   ├── ISBN.php
│   │   └── Rating.php
│   └── Repository/
│       ├── CatalogBookRepositoryInterface.php
│       └── AuthorRepositoryInterface.php
├── Application/
│   ├── Command/
│   │   ├── AddBookToCatalogCommand.php
│   │   └── UpdateBookMetadataCommand.php
│   ├── Query/
│   │   ├── SearchBooksQuery.php
│   │   └── GetBookDetailsQuery.php
│   └── EventHandler/             # ✅ Zaimplementowane
│       └── UpdateBookPopularityOnBookBorrowed.php
├── Infrastructure/
│   └── Doctrine/
│       └── Repository/
└── Presentation/
    └── Controller/
        └── CatalogController.php
```

## Różnica między Catalog a Lending

W kontekście **Catalog**:
- `CatalogBook` zawiera pełne metadane (opis, streszczenie, okładka, recenzje)
- Fokus na wyszukiwanie i przeglądanie

W kontekście **Lending**:
- `Book` zawiera tylko dane potrzebne do wypożyczenia (id, tytuł, dostępność)
- Fokus na operacje wypożyczania/zwrotu
