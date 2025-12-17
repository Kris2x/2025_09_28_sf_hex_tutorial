<?php

declare(strict_types=1);

namespace App\Lending\Domain\Repository;

use App\Lending\Domain\Entity\User;
use App\Lending\Domain\ValueObject\UserId;
use App\Lending\Domain\ValueObject\Email;

interface UserRepositoryInterface
{
    public function save(User $user): void;

    public function findById(UserId $id): ?User;

    public function findByEmail(Email $email): ?User;

    /** @return User[] */
    public function findAll(): array;

    public function remove(User $user): void;
}
