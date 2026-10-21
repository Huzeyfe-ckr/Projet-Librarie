<?php

namespace App\Controller;

use App\Entity\Announcement;
use App\Entity\Book;
use App\Entity\User;
use App\Form\BookPublicationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/books')]
class BookController extends AbstractController
{
    #[Route('/new', name: 'book_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(BookPublicationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            /** @var User $user */
            $user = $this->getUser();

            $announcement = new Announcement();
            $announcement
                ->setUser($user)
                ->setTitle($data['title'])
                ->setDescription($data['description'])
                ->setType($data['type'])
                ->setPrice(
                    null !== $data['price']
                        ? number_format((float) $data['price'], 2, '.', '')
                        : null
                );

            $book = new Book();
            $book
                ->setAnnouncement($announcement)
                ->setTitle($data['title'])
                ->setAuthor($data['author'])
                ->setIsbn($data['isbn'])
                ->setGenre($data['genre'])
                ->setBookCondition($data['bookCondition']);

            $em->persist($announcement);
            $em->persist($book);
            $em->flush();

            $this->addFlash('success', 'Ton livre a bien été publié.');

            return $this->redirectToRoute('app_home');
        }

        return $this->render('book/new.html.twig', [
            'form' => $form,
        ]);
    }
}