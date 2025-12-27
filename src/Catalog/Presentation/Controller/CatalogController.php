<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Controller;

use App\Catalog\Application\Command\AddBookToCatalogCommand;
use App\Catalog\Application\Query\GetCatalogBookDetailsQuery;
use App\Catalog\Application\Query\GetCategoriesQuery;
use App\Catalog\Application\Query\SearchCatalogBooksQuery;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/catalog')]
final class CatalogController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus
    ) {}
    /**
     * GET /api/catalog/books
     * Wyszukuje książki lub zwraca najpopularniejsze.
     *
     * Query params:
     * - q: szukany tekst w tytule
     * - category: slug kategorii
     * - author: ID autora
     */
    #[Route('/books', methods: ['GET'])]
    public function searchBooks(
        Request $request,
        SearchCatalogBooksQuery $query
    ): JsonResponse {
        $searchQuery = $request->query->get('q');
        $category = $request->query->get('category');
        $authorId = $request->query->get('author');

        if ($searchQuery) {
            $books = $query->byTitle($searchQuery);
        } elseif ($category) {
            $books = $query->byCategory($category);
        } elseif ($authorId) {
            $books = $query->byAuthor($authorId);
        } else {
            $books = $query->mostPopular();
        }

        return $this->json(array_map(
            fn($book) => [
                'id' => $book->id()->value(),
                'title' => $book->title(),
                'author' => $book->author()->fullName(),
                'isbn' => $book->isbn()->value(),
                'description' => $book->description(),
                'popularity' => $book->popularity(),
                'publishedAt' => $book->publishedAt()->format('Y-m-d'),
                'categories' => array_map(
                    fn($cat) => ['slug' => $cat->slug(), 'name' => $cat->name()],
                    $book->categories()->toArray()
                ),
            ],
            $books
        ));
    }

    /**
     * GET /api/catalog/books/{id}
     * Pobiera szczegóły książki.
     */
    #[Route('/books/{bookId}', methods: ['GET'])]
    public function getBook(
        string $bookId,
        GetCatalogBookDetailsQuery $query
    ): JsonResponse {
        $book = $query->execute($bookId);

        if ($book === null) {
            return $this->json(['error' => 'Book not found'], 404);
        }

        return $this->json([
            'id' => $book->id()->value(),
            'title' => $book->title(),
            'author' => [
                'id' => $book->author()->id()->value(),
                'name' => $book->author()->fullName(),
                'biography' => $book->author()->biography(),
            ],
            'isbn' => $book->isbn()->value(),
            'description' => $book->description(),
            'popularity' => $book->popularity(),
            'publishedAt' => $book->publishedAt()->format('Y-m-d'),
            'createdAt' => $book->createdAt()->format('Y-m-d H:i:s'),
            'categories' => array_map(
                fn($cat) => [
                    'slug' => $cat->slug(),
                    'name' => $cat->name(),
                    'path' => $cat->path(),
                ],
                $book->categories()->toArray()
            ),
        ]);
    }

    /**
     * POST /api/catalog/books
     * Dodaje nową książkę do katalogu.
     *
     * Ten endpoint:
     * 1. Tworzy Command z danych requestu
     * 2. Wysyła Command przez CommandBus
     * 3. Handler tworzy książkę i publikuje BookAddedToCatalogEvent
     * 4. Lending BC nasłuchuje i tworzy swoją wersję Book
     */
    #[Route('/books', methods: ['POST'])]
    public function addBook(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $requiredFields = ['bookId', 'title', 'isbn', 'authorId', 'authorFirstName', 'authorLastName', 'publishedAt'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return $this->json(['error' => sprintf('%s is required', $field)], 400);
            }
        }

        try {
            $command = new AddBookToCatalogCommand(
                bookId: $data['bookId'],
                title: $data['title'],
                isbn: $data['isbn'],
                authorId: $data['authorId'],
                authorFirstName: $data['authorFirstName'],
                authorLastName: $data['authorLastName'],
                publishedAt: new \DateTimeImmutable($data['publishedAt']),
                description: $data['description'] ?? null
            );

            $book = $this->commandBus->dispatch($command);

            return $this->json([
                'message' => 'Book added to catalog',
                'bookId' => $book->id()->value(),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * GET /api/catalog/categories
     * Zwraca listę kategorii.
     */
    #[Route('/categories', methods: ['GET'])]
    public function getCategories(
        GetCategoriesQuery $query
    ): JsonResponse {
        $categories = $query->rootCategories();

        return $this->json(array_map(
            fn($cat) => [
                'id' => $cat->id()->value(),
                'slug' => $cat->slug(),
                'name' => $cat->name(),
                'hasChildren' => $cat->hasChildren(),
                'children' => array_map(
                    fn($child) => [
                        'id' => $child->id()->value(),
                        'slug' => $child->slug(),
                        'name' => $child->name(),
                    ],
                    $cat->children()->toArray()
                ),
            ],
            $categories
        ));
    }
}
