<?php

declare(strict_types=1);

namespace App\Lending\Presentation\Controller;

use App\Lending\Application\Command\BorrowBookCommand;
use App\Lending\Application\Command\ReturnBookCommand;
use App\Lending\Application\Query\GetAvailableBooksQuery;
use App\Shared\Application\Bus\CommandBusInterface;
use DomainException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * REST API Controller dla operacji na książkach.
 *
 * Controller jest "cienki" - tylko:
 * 1. Parsuje request HTTP
 * 2. Tworzy Command i wysyła przez CommandBus
 * 3. Formatuje response
 *
 * Logika biznesowa jest w Handlerach i Domenie!
 * Controller nie zna handlerów - używa abstrakcji CommandBusInterface.
 */
#[Route('/api/books')]
final class BookController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus
    ) {}

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
        Request $request
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['userId'])) {
            return $this->json(['error' => 'userId is required'], 400);
        }

        try {
            $command = new BorrowBookCommand($data['userId'], $bookId);
            $this->commandBus->dispatch($command);
            return $this->json(['message' => 'Book borrowed successfully']);
        } catch (DomainException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{bookId}/return', name: 'book_return', methods: ['POST'])]
    public function returnBook(
        string $bookId,
        Request $request
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['userId'])) {
            return $this->json(['error' => 'userId is required'], 400);
        }

        try {
            $command = new ReturnBookCommand($data['userId'], $bookId);
            $fine = $this->commandBus->dispatch($command);
            return $this->json([
                'message' => 'Book returned successfully',
                'fine' => $fine
            ]);
        } catch (DomainException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
