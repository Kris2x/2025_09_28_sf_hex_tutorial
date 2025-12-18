<?php

declare(strict_types=1);

namespace App\Shared\Contract;

use App\Shared\ReadModel\BookBasicInfo;

/**
 * Kontrakt: Dostarczanie podstawowych informacji o książce.
 *
 * Ten interfejs żyje w Shared, bo:
 * - Lending go UŻYWA (potrzebuje info o książce)
 * - Catalog go IMPLEMENTUJE (ma dane o książkach)
 * - Żaden moduł nie "posiada" tego kontraktu
 *
 * W przyszłości inny moduł (np. Notifications) też może go użyć.
 */
interface BookInfoProviderInterface
{
    public function getBookInfo(string $bookId): ?BookBasicInfo;
}
