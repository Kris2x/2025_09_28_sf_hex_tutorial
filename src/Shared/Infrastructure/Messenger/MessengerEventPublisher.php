<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messenger;

use App\Shared\Domain\Event\DomainEventInterface;
use App\Shared\Domain\Event\EventPublisherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Adapter: Publikuje Domain Events przez Symfony Messenger.
 *
 * To jest INFRASTRUKTURA - implementuje interfejs z domeny.
 * Można łatwo wymienić na RabbitMQ, Redis, czy inny transport.
 */
final readonly class MessengerEventPublisher implements EventPublisherInterface
{
    public function __construct(
        private MessageBusInterface $eventBus
    ) {
    }

    public function publish(DomainEventInterface $event): void
    {
        $this->eventBus->dispatch($event);
    }

    public function publishAll(array $events): void
    {
        foreach ($events as $event) {
            $this->publish($event);
        }
    }
}
