<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Rend un enum « traduisible » à partir de sa méthode label().
 *
 * Grâce à TranslatableInterface, les formulaires Symfony (EnumType) et EasyAdmin (ChoiceField)
 * affichent automatiquement le libellé français au lieu du nom technique du case.
 */
trait LabelledEnumTrait
{
    abstract public function label(): string;

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $this->label();
    }
}
