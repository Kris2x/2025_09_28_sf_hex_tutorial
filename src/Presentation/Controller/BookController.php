<?php

declare(strict_types=1);

namespace App\Presentation\Controller;

use App\Application\Command\BorrowBookCommand;
use App\Application\Command\ReturnBookCommand;
use App\Application\Query\GetAvailableBooksQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/books')]
final class BookController extends AbstractController
{
    #[Route('/', name: 'books_available', methods: ['GET'])]
    public function getAvailableBooks(GetAvailableBooksQuery $query): JsonResponse
    {
        $books = $query->execute();

        $data = array_map(fn($book) => [
            'id' => $book->id()->value(),
            'title' => $book->title(),
            'author' => $book->author(),
            'isbn' => $book->isbn(),
            'available' => $book->isAvailable()
        ], $books);

        return $this->json($data);
    }

    #[Route('/{bookId}/borrow', name: 'book_borrow', methods: ['POST'])]
    public function borrowBook(
        string $bookId,
        Request $request,
        BorrowBookCommand $command
    ): JsonResponse {
        $userId = $request->request->get('userId');

        if (!$userId) {
            return $this->json(['error' => 'userId is required'], 400);
        }

        try {
            $command->execute($userId, $bookId);
            return $this->json(['message' => 'Book borrowed successfully']);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{bookId}/return', name: 'book_return', methods: ['POST'])]
    public function returnBook(
        string $bookId,
        Request $request,
        ReturnBookCommand $command
    ): JsonResponse {
        $userId = $request->request->get('userId');

        if (!$userId) {
            return $this->json(['error' => 'userId is required'], 400);
        }

        try {
            $fine = $command->execute($userId, $bookId);
            return $this->json([
                'message' => 'Book returned successfully',
                'fine' => $fine
            ]);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}