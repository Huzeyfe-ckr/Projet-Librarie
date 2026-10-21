<?php

namespace App\Controller\Admin;

use App\Entity\Book;
use App\Enum\BookCondition;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class BookCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Book::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('title', 'Titre');
        yield TextField::new('author', 'Auteur');
        yield TextField::new('isbn', 'ISBN')->hideOnIndex();
        yield TextField::new('genre', 'Genre');

        yield ChoiceField::new('bookCondition', 'État')
            ->setChoices(BookCondition::cases());

        yield AssociationField::new('announcement', 'Annonce');

        yield DateTimeField::new('inactivatedAt', 'Désactivé le')
            ->setHelp('Renseigne une date pour désactiver le livre, vide pour le réactiver.');
    }
}