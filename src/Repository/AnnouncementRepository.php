<?php

namespace App\Repository;

use App\Entity\Announcement;
use App\Entity\Book;
use App\Entity\User;
use App\Enum\AnnouncementStatus;
use App\Enum\ExchangeStatus;
use App\Model\AnnouncementSearch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Announcement>
 */
class AnnouncementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Announcement::class);
    }

    /**
     * Annonces visibles : annonce active ET propriétaire ni désactivé ni supprimé.
     */
    private function createActiveQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('a')
            ->innerJoin('a.user', 'u')
            ->addSelect('u')
            ->andWhere('a.inactivatedAt IS NULL')
            ->andWhere('u.inactivatedAt IS NULL')
            ->andWhere('u.deletedAt IS NULL');
    }

    /**
     * @return Announcement[]
     */
    public function findLatestActive(int $limit = 6): array
    {
        return $this->createActiveQueryBuilder()
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Annonces en ligne d'un vendeur (page de profil public), les plus récentes d'abord.
     *
     * @return Announcement[]
     */
    public function findActiveBySeller(User $seller): array
    {
        return $this->createActiveQueryBuilder()
            ->andWhere('a.user = :seller')
            ->setParameter('seller', $seller)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Nombre d'échanges terminés comme vendeur : indicateur de confiance sur le profil.
     */
    public function countCompletedBySeller(User $seller): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.user = :seller')
            ->andWhere('a.status = :completed')
            ->setParameter('seller', $seller)
            ->setParameter('completed', AnnouncementStatus::Completed)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findActiveById(int $id): ?Announcement
    {
        return $this->createActiveQueryBuilder()
            ->andWhere('a.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countActive(): int
    {
        return (int) $this->createActiveQueryBuilder()
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Recherche de la page Annonces : texte libre (annonce et livres), filtres et tri.
     *
     * @return Paginator<Announcement>
     */
    public function searchActive(AnnouncementSearch $search, int $page = 1, int $perPage = 12): Paginator
    {
        $qb = $this->createActiveQueryBuilder()
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if ('' !== $search->term) {
            // Le mot peut se trouver dans l'annonce ou dans l'un de ses livres (titre, auteur, ISBN)
            $qb->andWhere('a.title LIKE :term OR a.description LIKE :term OR EXISTS('.$this->activeBookDql('bt', 'bt.title LIKE :term OR bt.author LIKE :term OR bt.isbn LIKE :term').')')
                ->setParameter('term', '%'.$search->term.'%');
        }
        if (null !== $search->type) {
            $qb->andWhere('a.type = :type')->setParameter('type', $search->type);
        }
        if (null !== $search->genre) {
            $qb->andWhere('EXISTS('.$this->activeBookDql('bg', 'bg.genre = :genre').')')->setParameter('genre', $search->genre);
        }
        if (null !== $search->condition) {
            $qb->andWhere('EXISTS('.$this->activeBookDql('bc', 'bc.bookCondition = :condition').')')->setParameter('condition', $search->condition);
        }
        if (null !== $search->maxPrice) {
            // un don (prix NULL) est gratuit : il passe toujours le filtre
            $qb->andWhere('a.price IS NULL OR a.price <= :maxPrice')->setParameter('maxPrice', $search->maxPrice);
        }
        if ($search->availableOnly) {
            $qb->andWhere('a.status = :available')->setParameter('available', AnnouncementStatus::Available);
        }

        match ($search->sort) {
            'price_asc', 'price_desc' => $qb
                ->addSelect('COALESCE(a.price, 0) AS HIDDEN sortPrice')
                ->orderBy('sortPrice', 'price_asc' === $search->sort ? 'ASC' : 'DESC'),
            default => $qb->orderBy('a.createdAt', 'DESC'),
        };
        $qb->addOrderBy('a.id', 'DESC');

        return new Paginator($qb, false);
    }

    /**
     * Sous-requête « un livre actif de cette annonce vérifie la condition », pour un EXISTS(...).
     */
    private function activeBookDql(string $alias, string $condition): string
    {
        return \sprintf(
            'SELECT 1 FROM %s %2$s WHERE %2$s.announcement = a AND %2$s.inactivatedAt IS NULL AND (%3$s)',
            Book::class,
            $alias,
            $condition,
        );
    }

    /**
     * Toutes les annonces d'un utilisateur, y compris celles qu'il a retirées.
     *
     * @return Announcement[]
     */
    public function findByOwner(User $user): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.inactivatedAt', 'ASC') // les annonces en ligne (NULL) d'abord
            ->addOrderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Nombre de demandes en attente par annonce, pour les annonces d'un utilisateur.
     *
     * @return array<int, int> id d'annonce => nombre de demandes en attente
     */
    public function countPendingRequestsByOwner(User $user): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('a.id AS id, COUNT(e.id) AS pending')
            ->innerJoin('a.exchanges', 'e', 'WITH', 'e.status = :pending')
            ->andWhere('a.user = :user')
            ->setParameter('user', $user)
            ->setParameter('pending', ExchangeStatus::Pending)
            ->groupBy('a.id')
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'pending', 'id');
    }
}
