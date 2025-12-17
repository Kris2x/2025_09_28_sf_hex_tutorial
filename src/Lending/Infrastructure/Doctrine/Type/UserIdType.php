<?php

declare(strict_types=1);

namespace App\Lending\Infrastructure\Doctrine\Type;

use App\Lending\Domain\ValueObject\UserId;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Platforms\AbstractPlatform;

class UserIdType extends Type
{
    public const NAME = 'user_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?UserId
    {
        return $value ? new UserId($value) : null;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        return $value instanceof UserId ? $value->value() : $value;
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
