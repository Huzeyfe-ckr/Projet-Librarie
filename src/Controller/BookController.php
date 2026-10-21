<?php

namespace App\Controller;

use App\Entity\Announcement;
use App\Entity\Book;
use App\Entity\User;
use App\Enum\AnnouncementType;
use App\Form\BookPublicationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
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
        // Une ligne de livre vide au départ
        $form = $this->createForm(BookPublicationType::class, ['books' => [[]]]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $books = array_values($data['books']);
            $title = trim((string) ($data['title'] ?? ''));
            $type = $data['type'];
            $isDonation = AnnouncementType::Donation === $type;

            if (count($books) > 1 && '' === $title) {
                $form->get('title')->addError(new FormError('Donne un titre à ton pack de livres.'));
            }
            if (!$isDonation && null === $data['price']) {
                $form->get('price')->addError(new FormError('Indique un prix pour une vente.'));
            }

            if ($form->isValid()) {
                /** @var User $user */
                $user = $this->getUser();

                $announcement = (new Announcement())
                    ->setUser($user)
                    ->setTitle('' !== $title ? $title : $books[0]['title'])
                    ->setDescription($data['description'])
                    ->setType($type)
                    ->setPrice($isDonation ? null : number_format((float) $data['price'], 2, '.', ''));

                $em->persist($announcement);

                foreach ($books as $item) {
                    $book = (new Book())
                        ->setAnnouncement($announcement)
                        ->setTitle($item['title'])
                        ->setAuthor($item['author'])
                        ->setGenre($item['genre'])
                        ->setIsbn($item['isbn'])
                        ->setBookCondition($item['bookCondition']);

                    $em->persist($book);
                }

                $em->flush();

                $this->addFlash('success', count($books) > 1
                    ? sprintf('Ton pack de %d livres a bien été publié.', count($books))
                    : 'Ton livre a bien été publié.');

                return $this->redirectToRoute('announcement_show', ['id' => $announcement->getId()]);
            }
        }

        return $this->render('book/new.html.twig', [
            'form' => $form,
            'maxBooks' => BookPublicationType::MAX_BOOKS,
        ]);
    }
}