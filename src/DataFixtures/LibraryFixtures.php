<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Domain\Entity\Book;
use App\Domain\Entity\User;
use App\Domain\ValueObject\BookId;
use App\Domain\ValueObject\UserId;
use App\Domain\ValueObject\Email;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class LibraryFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Tworzenie testowych użytkowników
        $users = $this->createUsers($manager);

        // Tworzenie testowych książek
        $books = $this->createBooks($manager);

        $manager->flush();
    }

    private function createUsers(ObjectManager $manager): array
    {
        $users = [];
        $testUsers = [
            ['Jan Kowalski', 'jan.kowalski@example.com'],
            ['Anna Nowak', 'anna.nowak@example.com'],
            ['Piotr Wiśniewski', 'piotr.wisniewski@example.com'],
            ['Maria Wójcik', 'maria.wojcik@example.com'],
            ['Tomasz Kowalczyk', 'tomasz.kowalczyk@example.com'],
        ];

        foreach ($testUsers as $index => $userData) {
            $user = new User(
                new UserId('user-' . ($index + 1)),
                $userData[0],
                new Email($userData[1]),
                new DateTimeImmutable('-' . ($index * 30) . ' days')
            );

            $manager->persist($user);
            $users[] = $user;
        }

        return $users;
    }

    private function createBooks(ObjectManager $manager): array
    {
        $books = [];
        $testBooks = [
            [
                'Wzorce projektowe',
                'Erich Gamma',
                '978-83-246-1493-0',
                '1994-10-21'
            ],
            [
                'Czysty kod',
                'Robert C. Martin',
                '978-83-283-6234-4',
                '2008-08-01'
            ],
            [
                'Domain-Driven Design',
                'Eric Evans',
                '978-0-321-12521-7',
                '2003-08-20'
            ],
            [
                'Architektura aplikacji. Wzorce',
                'Martin Fowler',
                '978-83-246-0030-8',
                '2002-11-15'
            ],
            [
                'Refaktoryzacja',
                'Martin Fowler',
                '978-83-283-5186-7',
                '1999-07-08'
            ],
        ];

        foreach ($testBooks as $index => $bookData) {
            $book = new Book(
                new BookId('book-' . ($index + 1)),
                $bookData[0],
                $bookData[1],
                $bookData[2],
                new DateTimeImmutable($bookData[3])
            );

            $manager->persist($book);
            $books[] = $book;
        }

        return $books;
    }
}