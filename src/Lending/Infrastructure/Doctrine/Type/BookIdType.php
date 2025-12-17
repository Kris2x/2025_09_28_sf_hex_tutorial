<?php

declare(strict_types=1);

namespace App\Lending\Infrastructure\Doctrine\Type;

use App\Lending\Domain\ValueObject\BookId;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Platforms\AbstractPlatform;

class BookIdType extends Type
{
    public const NAME = 'book_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?BookId
    {
        return $value ? new BookId($value) : null;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        return $value instanceof BookId ? $value->value() : $value;
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
