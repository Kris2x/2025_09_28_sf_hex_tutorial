<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

use InvalidArgumentException;

final readonly class CatalogBookId
{
    public function __construct(
        private string $value
    ) {
        if (empty($this->value)) {
            throw new InvalidArgumentException('CatalogBookId cannot be empty');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(CatalogBookId $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
