<?php

namespace App\Model;

use App\Enum\AnnouncementType;
use App\Enum\BookCondition;
use Symfony\Component\HttpFoundation\Request;

/**
 * Critères de recherche de la page Annonces, lus depuis l'URL (?q=…&type=…&genre=…).
 *
 * Toute valeur inconnue ou mal formée est simplement ignorée : une URL bricolée ne provoque jamais d'erreur.
 */
final class AnnouncementSearch
{
    public const SORTS = [
        'recent' => 'Plus récentes',
        'price_asc' => 'Prix croissant',
        'price_desc' => 'Prix décroissant',
    ];

    public function __construct(
        public readonly string $term = '',
        public readonly ?AnnouncementType $type = null,
        public readonly ?string $genre = null,
        public readonly ?BookCondition $condition = null,
        public readonly ?float $maxPrice = null,
        public readonly bool $availableOnly = false,
        public readonly string $sort = 'recent',
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $query = $request->query;
        $genre = trim($query->getString('genre'));
        $maxPrice = str_replace(',', '.', trim($query->getString('max_price')));
        $sort = $query->getString('sort', 'recent');

        return new self(
            term: trim($query->getString('q')),
            type: AnnouncementType::tryFrom($query->getString('type')),
            genre: '' !== $genre ? $genre : null,
            condition: BookCondition::tryFrom($query->getString('condition')),
            maxPrice: is_numeric($maxPrice) && (float) $maxPrice >= 0 ? (float) $maxPrice : null,
            availableOnly: $query->getBoolean('available'),
            sort: \array_key_exists($sort, self::SORTS) ? $sort : 'recent',
        );
    }

    /** Au moins un filtre (hors recherche texte et tri) est actif ? */
    public function hasFilters(): bool
    {
        return null !== $this->type || null !== $this->genre || null !== $this->condition
            || null !== $this->maxPrice || $this->availableOnly;
    }
}
