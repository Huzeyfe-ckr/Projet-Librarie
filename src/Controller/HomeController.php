<?php

namespace App\Controller;

use App\Entity\Book;
use App\Entity\User;
use App\Repository\AnnouncementRepository;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        AnnouncementRepository $announcements,
        BookRepository $books,
        EntityManagerInterface $em,
    ): Response {
        $latest = $announcements->findLatestActive(6);

        return $this->render('home/index.html.twig', [
            'latestAnnouncements' => $latest,
            'booksByAnnouncement' => $books->findActiveByAnnouncements($latest),
            'stats' => [
                'announcements' => $announcements->countActive(),
                'books' => $em->getRepository(Book::class)->count(['inactivatedAt' => null]),
                'users' => $em->getRepository(User::class)->count(['inactivatedAt' => null, 'deletedAt' => null]),
            ],
        ]);
    }
}