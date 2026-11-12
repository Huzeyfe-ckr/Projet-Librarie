<?php

namespace App\Controller;

use App\Entity\Exchange;
use App\Entity\User;
use App\Exception\ExchangeException;
use App\Form\ExchangeMessageType;
use App\Repository\AnnouncementRepository;
use App\Repository\ExchangeRepository;
use App\Repository\MessageRepository;
use App\Security\Voter\ExchangeVoter;
use App\Service\ExchangeManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/exchanges')]
#[IsGranted('ROLE_USER')]
class ExchangeController extends AbstractController
{
    /**
     * Actions possibles sur un échange : droit à vérifier (ExchangeVoter), méthode d'ExchangeManager et message de succès.
     */
    private const TRANSITIONS = [
        'accept' => [ExchangeVoter::ACCEPT, 'accept', 'Demande acceptée : l\'annonce est maintenant réservée.'],
        'refuse' => [ExchangeVoter::REFUSE, 'refuse', 'Demande refusée.'],
        'complete' => [ExchangeVoter::COMPLETE, 'complete', 'Échange terminé, merci !'],
        'cancel' => [ExchangeVoter::CANCEL, 'cancel', 'Échange annulé.'],
    ];

    public function __construct(
        private readonly ExchangeManager $exchangeManager,
    ) {
    }

    #[Route('', name: 'exchange_index', methods: ['GET'])]
    public function index(ExchangeRepository $exchanges): Response
    {
        return $this->render('exchange/index.html.twig', [
            'exchanges' => $exchanges->findForUser($this->getAppUser()),
        ]);
    }

    #[Route('/request/{id}', name: 'exchange_request', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function request(
        int $id,
        Request $request,
        AnnouncementRepository $announcements,
        ExchangeRepository $exchanges,
    ): Response {
        $announcement = $announcements->findActiveById($id);
        if (null === $announcement) {
            throw $this->createNotFoundException('Annonce introuvable.');
        }

        $back = $this->redirectToRoute('announcement_show', ['id' => $id]);

        if (!$this->isCsrfTokenValid('exchange_request_'.$id, $request->request->getString('_token'))) {
            $this->addFlash('error', 'Session expirée, réessaie.');

            return $back;
        }

        $user = $this->getAppUser();

        // Une seule demande par utilisateur et par annonce : on renvoie vers la conversation existante
        $existing = $exchanges->findOneBy(['announcement' => $announcement, 'requester' => $user]);
        if (null !== $existing) {
            return $this->redirectToRoute('exchange_show', ['id' => $existing->getId()]);
        }

        $text = $request->request->getString('message');
        if (mb_strlen($text) > 2000) {
            $this->addFlash('error', 'Ton message est trop long (2000 caractères maximum).');

            return $back;
        }

        try {
            $exchange = $this->exchangeManager->request($announcement, $user, $text);
        } catch (ExchangeException $e) {
            $this->addFlash('error', $e->getMessage());

            return $back;
        }

        $this->addFlash('success', 'Ta demande a bien été envoyée.');

        return $this->redirectToRoute('exchange_show', ['id' => $exchange->getId()]);
    }

    #[Route('/{id}', name: 'exchange_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(ExchangeVoter::VIEW, subject: 'exchange')]
    public function show(Exchange $exchange, Request $request, MessageRepository $messages): Response
    {
        $announcement = $exchange->getAnnouncement();

        // Annonce désactivée (modération) : la conversation n'est plus accessible
        if (null !== $announcement->getInactivatedAt()) {
            throw $this->createNotFoundException('Cette annonce n\'est plus disponible.');
        }

        $form = $this->createForm(ExchangeMessageType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && !$this->isGranted(ExchangeVoter::MESSAGE, $exchange)) {
            $this->addFlash('error', 'Cet échange est clos, tu ne peux plus y envoyer de message.');

            return $this->redirectToRoute('exchange_show', ['id' => $exchange->getId()]);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $this->exchangeManager->sendMessage($exchange, $this->getAppUser(), $form->get('content')->getData());

            return $this->redirectToRoute('exchange_show', ['id' => $exchange->getId(), '_fragment' => 'bottom']);
        }

        $user = $this->getAppUser();
        $requester = $exchange->getRequester();

        return $this->render('exchange/show.html.twig', [
            'exchange' => $exchange,
            'announcement' => $announcement,
            'messages' => $messages->findActiveByExchange($exchange),
            'other' => $user->getId() === $requester->getId() ? $announcement->getUser() : $requester,
            'isOwner' => $user->getId() === $announcement->getUser()->getId(),
            'form' => $form,
        ]);
    }

    /**
     * Accepter, refuser, terminer ou annuler un échange (boutons de la conversation).
     */
    #[Route('/{id}/{action}', name: 'exchange_transition', requirements: ['id' => '\d+', 'action' => 'accept|refuse|complete|cancel'], methods: ['POST'])]
    public function transition(Exchange $exchange, string $action, Request $request): Response
    {
        [$attribute, $method, $success] = self::TRANSITIONS[$action];
        $back = $this->redirectToRoute('exchange_show', ['id' => $exchange->getId(), '_fragment' => 'bottom']);

        if (!$this->isCsrfTokenValid('exchange_'.$action.'_'.$exchange->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Session expirée, réessaie.');

            return $back;
        }

        $this->denyAccessUnlessGranted($attribute, $exchange, 'Tu ne peux pas faire cette action sur cet échange.');

        try {
            $this->exchangeManager->{$method}($exchange, $this->getAppUser());
            $this->addFlash('success', $success);
        } catch (ExchangeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $back;
    }

    private function getAppUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
