<?php

namespace App\Controller;

use App\Entity\Announcement;
use App\Entity\Book;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(EntityManagerInterface $em): Response
    {
        $announcements = $em->getRepository(Announcement::class);

        return $this->render('home/index.html.twig', [
            'latestAnnouncements' => $announcements->findBy([], ['id' => 'DESC'], 6),
            'stats' => [
                'announcements' => $announcements->count([]),
                'books' => $em->getRepository(Book::class)->count([]),
                'users' => $em->getRepository(User::class)->count([]),
            ],
        ]);
    }
}