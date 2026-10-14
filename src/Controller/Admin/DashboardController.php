<?php

namespace App\Controller\Admin;

use App\Entity\Announcement;
use App\Entity\Book;
use App\Entity\BookPage;
use App\Entity\Comment;
use App\Entity\Exchange;
use App\Entity\Message;
use App\Entity\User;
use App\Entity\Wishlist;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'stats' => [
                'Utilisateurs' => $this->em->getRepository(User::class)->count([]),
                'Annonces' => $this->em->getRepository(Announcement::class)->count([]),
                'Livres' => $this->em->getRepository(Book::class)->count([]),
                'Échanges' => $this->em->getRepository(Exchange::class)->count([]),
                'Messages' => $this->em->getRepository(Message::class)->count([]),
                'Commentaires' => $this->em->getRepository(Comment::class)->count([]),
            ],
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Administration');
    }

    public function configureMenuItems(): iterable
{
    yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-home');

    yield MenuItem::section('Utilisateurs');
    yield MenuItem::linkTo(UserCrudController::class, 'Utilisateurs', 'fa fa-users');

    yield MenuItem::section('Catalogue');
    yield MenuItem::linkTo(BookCrudController::class, 'Livres', 'fa fa-book');
    yield MenuItem::linkTo(BookPageCrudController::class, 'Pages de livres', 'fa fa-file-lines');
    yield MenuItem::linkTo(AnnouncementCrudController::class, 'Annonces', 'fa fa-bullhorn');

    yield MenuItem::section('Activité');
    yield MenuItem::linkTo(ExchangeCrudController::class, 'Échanges', 'fa fa-right-left');
    yield MenuItem::linkTo(MessageCrudController::class, 'Messages', 'fa fa-envelope');
    yield MenuItem::linkTo(CommentCrudController::class, 'Commentaires', 'fa fa-comments');
    yield MenuItem::linkTo(WishlistCrudController::class, 'Wishlists', 'fa fa-heart');

    yield MenuItem::section('Site');
    yield MenuItem::linkToUrl('Retour au site', 'fa fa-arrow-left', '/');
}
}