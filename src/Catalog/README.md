# Catalog Bounded Context

## TODO: Implementacja

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
│   └── Query/
│       ├── SearchBooksQuery.php
│       └── GetBookDetailsQuery.php
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
