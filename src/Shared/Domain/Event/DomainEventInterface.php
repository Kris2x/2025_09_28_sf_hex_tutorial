<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

/**
 * Marker interface dla wszystkich Domain Events.
 *
 * Domain Event reprezentuje coś, co WYDARZYŁO SIĘ w domenie.
 * Jest niezmienną informacją o przeszłości - dlatego immutable.
 */
interface DomainEventInterface
{
    /**
     * Kiedy wydarzenie miało miejsce.
     */
    public function occurredAt(): \DateTimeImmutable;
}
