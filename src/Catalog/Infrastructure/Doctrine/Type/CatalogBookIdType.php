<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine\Type;

use App\Catalog\Domain\ValueObject\CatalogBookId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class CatalogBookIdType extends Type
{
    public const NAME = 'catalog_book_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?CatalogBookId
    {
        return $value ? new CatalogBookId($value) : null;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        return $value instanceof CatalogBookId ? $value->value() : $value;
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
