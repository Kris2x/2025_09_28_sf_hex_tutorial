<?php

declare(strict_types=1);

namespace App\Lending\Presentation\Controller;

use App\Lending\Application\Command\BorrowBookCommand;
use App\Lending\Application\Command\ReturnBookCommand;
use App\Lending\Application\Query\GetAvailableBooksQuery;
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
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['userId'])) {
            return $this->json(['error' => 'userId is required'], 400);
        }

        $userId = $data['userId'];

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
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['userId'])) {
            return $this->json(['error' => 'userId is required'], 400);
        }

        $userId = $data['userId'];

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
