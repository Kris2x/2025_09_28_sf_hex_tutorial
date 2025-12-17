<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

/**
 * Port do publikowania Domain Events.
 *
 * Domena definiuje CO chce publikować (interfejs).
 * Infrastruktura definiuje JAK (Messenger, RabbitMQ, etc.).
 */
interface EventPublisherInterface
{
    public function publish(DomainEventInterface $event): void;

    /**
     * Publikuje wiele eventów naraz.
     *
     * @param DomainEventInterface[] $events
     */
    public function publishAll(array $events): void;
}
