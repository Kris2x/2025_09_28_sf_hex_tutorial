# Acquisition Bounded Context

## TODO: Implementacja

Ten moduł będzie odpowiedzialny za:
- Zakupy nowych książek
- Zarządzanie dostawcami
- Budżet biblioteki
- Zamówienia i faktury
- Przyjęcia do magazynu

## Planowana struktura

```
Acquisition/
├── Domain/
│   ├── Entity/
│   │   ├── PurchaseOrder.php    # Zamówienie
│   │   ├── Supplier.php         # Dostawca
│   │   └── Invoice.php          # Faktura
│   ├── ValueObject/
│   │   ├── Money.php
│   │   ├── OrderNumber.php
│   │   └── SupplierCode.php
│   └── Repository/
│       ├── PurchaseOrderRepositoryInterface.php
│       └── SupplierRepositoryInterface.php
├── Application/
│   ├── Command/
│   │   ├── CreatePurchaseOrderCommand.php
│   │   ├── ReceiveDeliveryCommand.php
│   │   └── ProcessInvoiceCommand.php
│   └── Query/
│       └── GetPendingOrdersQuery.php
├── Infrastructure/
│   └── Doctrine/
│       └── Repository/
└── Presentation/
    └── Controller/
        └── AcquisitionController.php
```

## Integracja z innymi kontekstami

- Po przyjęciu dostawy → dodaj książki do **Catalog**
- Książki z katalogu mogą być wypożyczane w **Lending**
