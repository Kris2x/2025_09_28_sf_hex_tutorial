<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Value Object: ISBN książki.
 *
 * Waliduje format ISBN-10 lub ISBN-13.
 */
final readonly class Isbn
{
    public function __construct(
        private string $value
    ) {
        $normalized = $this->normalize($this->value);

        if (!$this->isValidIsbn($normalized)) {
            throw new InvalidArgumentException(
                sprintf('Invalid ISBN format: %s', $this->value)
            );
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(Isbn $other): bool
    {
        return $this->normalize($this->value) === $this->normalize($other->value);
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private function normalize(string $isbn): string
    {
        return preg_replace('/[^0-9X]/i', '', $isbn);
    }

    private function isValidIsbn(string $normalized): bool
    {
        $length = strlen($normalized);

        return $length === 10 || $length === 13;
    }
}
