# Architektura Hexagonalna w Symfony - System Biblioteki Online

Projekt edukacyjny demonstrujący implementację **architektury hexagonalnej** (Ports and Adapters) z podziałem na **Bounded Contexts** zgodnie z Domain-Driven Design.

## Stack technologiczny

- PHP 8.2+
- Symfony 7.3
- Doctrine ORM 3.5
- PostgreSQL (Docker)

## Szybki start

```bash
# 1. Klonowanie
git clone <repo-url>
cd 2025_09_28_sf_hex_tutorial

# 2. Zależności
composer install

# 3. Baza danych
docker-compose up -d

# 4. Migracje i dane testowe
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load

# 5. Serwer
symfony server:start
```

## Bounded Contexts

| Kontekst | Odpowiedzialność | Status |
|----------|------------------|--------|
| **Lending** | Wypożyczenia, zwroty, kary | ✅ Zaimplementowany |
| **Catalog** | Metadane książek, autorzy, kategorie | ✅ Zaimplementowany |
| **Shared** | Eventy, kontrakty, współdzielone DTO | ✅ Zaimplementowany |
| **Membership** | Członkostwo, karty biblioteczne | 📋 TODO |
| **Acquisition** | Zakupy książek, dostawcy | 📋 TODO |

## Struktura projektu

```
src/
├── Lending/              # BC: Wypożyczenia
│   ├── Domain/           #   Encje, Value Objects, Repository Interfaces
│   ├── Application/      #   Commands, Handlers, Queries, EventHandlers
│   ├── Infrastructure/   #   Doctrine Repositories, Types
│   └── Presentation/     #   REST Controllers
│
├── Catalog/              # BC: Katalog
│   ├── Domain/           #   CatalogBook, Author, Category
│   ├── Application/      #   Commands, Handlers, Queries
│   ├── Infrastructure/   #   Repositories, ContractAdapters
│   └── Presentation/     #   REST Controllers
│
└── Shared/               # Współdzielone między BC
    ├── Domain/           #   DomainEventInterface, EventPublisherInterface
    ├── Application/      #   CommandBusInterface
    ├── Contract/         #   Interfejsy komunikacji między BC
    └── Infrastructure/   #   MessengerEventPublisher, MessengerCommandBus
```

## API Endpoints

### Lending BC
| Metoda | Endpoint | Opis |
|--------|----------|------|
| GET | `/api/books/` | Lista dostępnych książek |
| POST | `/api/books/{id}/borrow` | Wypożycz książkę |
| POST | `/api/books/{id}/return` | Zwróć książkę |

### Catalog BC
| Metoda | Endpoint | Opis |
|--------|----------|------|
| GET | `/api/catalog/books` | Wyszukaj książki |
| GET | `/api/catalog/books/{id}` | Szczegóły książki |
| POST | `/api/catalog/books` | Dodaj książkę |
| GET | `/api/catalog/categories` | Lista kategorii |

## Dokumentacja

### Architektura
- [Architektura Hexagonalna](docs/architecture/hexagonal.md) - Czym jest, dlaczego warto, korzyści
- [Bounded Contexts](docs/architecture/bounded-contexts.md) - Podział na moduły biznesowe
- [Warstwy aplikacji](docs/architecture/layers.md) - Domain, Application, Infrastructure, Presentation
- [Porty i Adaptery](docs/architecture/ports-and-adapters.md) - Serce architektury hexagonalnej

### CQRS i Eventy
- [Commands i Handlers](docs/cqrs/commands-and-handlers.md) - Wzorzec Command/Handler
- [Domain Events](docs/cqrs/events.md) - Komunikacja między kontekstami

### Praktyka
- [API Reference](docs/api.md) - Szczegółowa dokumentacja API
- [Testowanie](docs/testing.md) - Strategia testowania
- [Potencjalne ulepszenia](docs/improvements.md) - Co można poprawić dla pełnej zgodności z wzorcami

## Komendy

```bash
# Serwer deweloperski
symfony server:start

# Docker (baza danych)
docker-compose up -d

# Migracje
php bin/console doctrine:migrations:migrate

# Fixtures (dane testowe)
php bin/console doctrine:fixtures:load

# Walidacja schematu
php bin/console doctrine:schema:validate

# Testy
php bin/phpunit
```

## Architektura w pigułce

```
                 ┌─────────────────────────────────────┐
  HTTP Request ──►│         PRESENTATION               │
                 │    (Controllers, REST API)          │
                 └──────────────┬──────────────────────┘
                                │
                                ▼
                 ┌─────────────────────────────────────┐
                 │         APPLICATION                 │
                 │  (Commands, Handlers, Queries)      │
                 └──────────────┬──────────────────────┘
                                │
                                ▼
                 ┌─────────────────────────────────────┐
                 │           DOMAIN                    │◄── Serce aplikacji
                 │  (Entities, Value Objects, Ports)   │    Czysta logika biznesowa
                 └──────────────▲──────────────────────┘
                                │ implementuje
                 ┌──────────────┴──────────────────────┐
                 │       INFRASTRUCTURE                │──► Database
                 │  (Doctrine, Messenger, External)    │──► External APIs
                 └─────────────────────────────────────┘
```

**Kluczowa zasada:** Zależności wskazują do środka. Infrastruktura zależy od domeny, nie odwrotnie.

## Licencja

MIT

---

*Projekt edukacyjny demonstrujący architekturę hexagonalną z podziałem na Bounded Contexts.*
