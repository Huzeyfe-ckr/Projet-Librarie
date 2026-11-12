<?php

namespace App\Controller;

use App\Entity\Exchange;
use App\Entity\Message;
use App\Entity\User;
use App\Enum\AnnouncementStatus;
use App\Form\ExchangeMessageType;
use App\Repository\AnnouncementRepository;
use App\Repository\ExchangeRepository;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/exchanges')]
#[IsGranted('ROLE_USER')]
class ExchangeController extends AbstractController
{
    #[Route('', name: 'exchange_index', methods: ['GET'])]
    public function index(ExchangeRepository $exchanges): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('exchange/index.html.twig', [
            'exchanges' => $exchanges->findForUser($user),
        ]);
    }

    #[Route('/request/{id}', name: 'exchange_request', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function request(
        int $id,
        Request $request,
        AnnouncementRepository $announcements,
        ExchangeRepository $exchanges,
        EntityManagerInterface $em,
    ): Response {
        $announcement = $announcements->findActiveById($id);
        if (null === $announcement) {
            throw $this->createNotFoundException('Annonce introuvable.');
        }

        $back = $this->redirectToRoute('announcement_show', ['id' => $id]);

        if (!$this->isCsrfTokenValid('exchange_request_' . $id, $request->request->getString('_token'))) {
            $this->addFlash('error', 'Session expirée, réessaie.');

            return $back;
        }

        /** @var User $user */
        $user = $this->getUser();

        if ($announcement->getUser()->getId() === $user->getId()) {
            $this->addFlash('error', 'C\'est ta propre annonce.');

            return $back;
        }

        // Une seule demande par utilisateur et par annonce : on renvoie vers la conversation existante
        $existing = $exchanges->findOneBy(['announcement' => $announcement, 'requester' => $user]);
        if (null !== $existing) {
            return $this->redirectToRoute('exchange_show', ['id' => $existing->getId()]);
        }

        if (AnnouncementStatus::Available !== $announcement->getStatus()) {
            $this->addFlash('error', 'Cette annonce n\'est plus disponible.');

            return $back;
        }

        $text = trim($request->request->getString('message'));
        if (mb_strlen($text) > 2000) {
            $this->addFlash('error', 'Ton message est trop long (2000 caractères maximum).');

            return $back;
        }
        if ('' === $text) {
            $text = sprintf('Bonjour, ton annonce « %s » m\'intéresse. Est-elle toujours disponible ?', $announcement->getTitle());
        }

        $exchange = (new Exchange())
            ->setAnnouncement($announcement)
            ->setRequester($user);

        $message = (new Message())
            ->setExchange($exchange)
            ->setSender($user)
            ->setContent($text)
            ->setSentAt(new \DateTimeImmutable());

        $em->persist($exchange);
        $em->persist($message);
        $em->flush();

        $this->addFlash('success', 'Ta demande a bien été envoyée.');

        return $this->redirectToRoute('exchange_show', ['id' => $exchange->getId()]);
    }

    #[Route('/{id}', name: 'exchange_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function show(
        Exchange $exchange,
        Request $request,
        MessageRepository $messages,
        EntityManagerInterface $em,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $announcement = $exchange->getAnnouncement();
        $owner = $announcement->getUser();
        $requester = $exchange->getRequester();

        // Seuls le demandeur et le propriétaire de l'annonce accèdent à la conversation
        if ($user->getId() !== $requester->getId() && $user->getId() !== $owner->getId()) {
            throw $this->createAccessDeniedException();
        }

        // Annonce désactivée (modération) : la conversation n'est plus accessible
        if (null !== $announcement->getInactivatedAt()) {
            throw $this->createNotFoundException('Cette annonce n\'est plus disponible.');
        }

        $form = $this->createForm(ExchangeMessageType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && !$exchange->getStatus()->isOpen()) {
            $this->addFlash('error', 'Cet échange est clos, tu ne peux plus y envoyer de message.');

            return $this->redirectToRoute('exchange_show', ['id' => $exchange->getId()]);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $message = (new Message())
                ->setExchange($exchange)
                ->setSender($user)
                ->setContent(trim($form->get('content')->getData()))
                ->setSentAt(new \DateTimeImmutable());

            $em->persist($message);
            $em->flush();

            return $this->redirectToRoute('exchange_show', ['id' => $exchange->getId(), '_fragment' => 'bottom']);
        }

        return $this->render('exchange/show.html.twig', [
            'exchange' => $exchange,
            'announcement' => $announcement,
            'messages' => $messages->findActiveByExchange($exchange),
            'other' => $user->getId() === $requester->getId() ? $owner : $requester,
            'form' => $form,
        ]);
    }
}