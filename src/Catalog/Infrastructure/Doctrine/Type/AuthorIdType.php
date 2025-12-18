<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine\Type;

use App\Catalog\Domain\ValueObject\AuthorId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class AuthorIdType extends Type
{
    public const NAME = 'author_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?AuthorId
    {
        return $value ? new AuthorId($value) : null;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        return $value instanceof AuthorId ? $value->value() : $value;
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
