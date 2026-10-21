<?php

namespace App\Controller;

use App\Repository\AnnouncementRepository;
use App\Repository\BookRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/announcements')]
class AnnouncementController extends AbstractController
{
    private const PER_PAGE = 12;

    #[Route('', name: 'announcement_index', methods: ['GET'])]
    public function index(
        Request $request,
        AnnouncementRepository $announcements,
        BookRepository $books,
    ): Response {
        $term = trim((string) $request->query->get('q', ''));
        $page = max(1, $request->query->getInt('page', 1));

        $result = $announcements->searchActive($term, $page, self::PER_PAGE);
        $items = iterator_to_array($result);

        return $this->render('announcement/index.html.twig', [
            'announcements' => $items,
            'booksByAnnouncement' => $books->findActiveByAnnouncements($items),
            'term' => $term,
            'page' => $page,
            'pages' => max(1, (int) ceil(count($result) / self::PER_PAGE)),
            'total' => count($result),
        ]);
    }

    #[Route('/{id}', name: 'announcement_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(
        int $id,
        AnnouncementRepository $announcements,
        BookRepository $books,
    ): Response {
        // Un admin peut ouvrir une annonce désactivée (pour la modérer), pas les autres.
        $announcement = $this->isGranted('ROLE_ADMIN')
            ? $announcements->find($id)
            : $announcements->findActiveById($id);

        if (null === $announcement) {
            throw $this->createNotFoundException('Annonce introuvable.');
        }

        return $this->render('announcement/show.html.twig', [
            'announcement' => $announcement,
            'books' => $books->findActiveByAnnouncements([$announcement])[$announcement->getId()] ?? [],
        ]);
    }
}