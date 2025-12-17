# Membership Bounded Context

## TODO: Implementacja

Ten moduł będzie odpowiedzialny za:
- Rejestracja i zarządzanie członkami biblioteki
- Karty biblioteczne
- Typy członkostwa (student, senior, standardowy)
- Historia użytkownika
- Powiadomienia

## Planowana struktura

```
Membership/
├── Domain/
│   ├── Entity/
│   │   ├── Member.php           # Pełny profil członka
│   │   ├── LibraryCard.php      # Karta biblioteczna
│   │   └── MembershipType.php   # Typ członkostwa
│   ├── ValueObject/
│   │   ├── MemberId.php
│   │   ├── CardNumber.php
│   │   └── ContactInfo.php
│   └── Repository/
│       └── MemberRepositoryInterface.php
├── Application/
│   ├── Command/
│   │   ├── RegisterMemberCommand.php
│   │   ├── RenewMembershipCommand.php
│   │   └── IssueLibraryCardCommand.php
│   └── Query/
│       └── GetMemberProfileQuery.php
├── Infrastructure/
│   └── Doctrine/
│       └── Repository/
└── Presentation/
    └── Controller/
        └── MemberController.php
```

## Różnica między Membership a Lending

W kontekście **Membership**:
- `Member` to pełny profil użytkownika (dane kontaktowe, typ członkostwa, historia)
- Fokus na zarządzanie członkami

W kontekście **Lending**:
- `User` zawiera tylko dane potrzebne do wypożyczenia (id, limit wypożyczeń)
- Fokus na operacje wypożyczania
