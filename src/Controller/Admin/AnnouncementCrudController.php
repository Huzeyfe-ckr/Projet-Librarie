<?php

namespace App\Controller\Admin;

use App\Entity\Announcement;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class AnnouncementCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Announcement::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('title', 'Titre');
        yield AssociationField::new('user', 'Publié par');

        yield ChoiceField::new('type', 'Type');
        yield ChoiceField::new('status', 'Statut');

        yield MoneyField::new('price', 'Prix')
            ->setCurrency('EUR')
            ->setStoredAsCents(false);

        yield TextareaField::new('description', 'Description')->hideOnIndex();

        yield DateTimeField::new('createdAt', 'Publiée le')
            ->setFormTypeOption('disabled', true);
        yield DateTimeField::new('inactivatedAt', 'Désactivée le')
            ->setHelp('Renseigne une date pour retirer l\'annonce du site, vide pour la réactiver.');
    }
}
