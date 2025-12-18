<?php

declare(strict_types=1);

namespace App\Shared\ReadModel;

/**
 * DTO: Podstawowe informacje o książce.
 *
 * Używany do przekazywania danych między modułami.
 * Zawiera tylko dane potrzebne do odczytu - bez logiki biznesowej.
 *
 * Readonly + public properties = prosty, niemutowalny obiekt danych.
 */
final readonly class BookBasicInfo
{
    public function __construct(
        public string $id,
        public string $title,
        public string $author,
        public string $isbn
    ) {}
}
