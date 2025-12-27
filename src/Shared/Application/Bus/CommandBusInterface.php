<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

/**
 * Port: Interfejs do wysyłania komend (CQRS).
 *
 * Warstwa Application definiuje CO chce robić,
 * Infrastructure (adapter) decyduje JAK to zrobić.
 */
interface CommandBusInterface
{
    /**
     * Wysyła komendę do odpowiedniego handlera.
     *
     * @param object $command Komenda do wykonania
     * @return mixed Wynik wykonania komendy (opcjonalny)
     */
    public function dispatch(object $command): mixed;
}