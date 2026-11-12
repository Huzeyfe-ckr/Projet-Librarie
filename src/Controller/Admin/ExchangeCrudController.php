<?php

namespace App\Controller\Admin;

use App\Entity\Exchange;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;

class ExchangeCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Exchange::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('announcement', 'Annonce');
        yield AssociationField::new('requester', 'Demandeur');

        // Les libellés viennent de ExchangeStatus::label()
        yield ChoiceField::new('status', 'Statut');

        yield DateTimeField::new('initiatedAt', 'Demandé le')
            ->setFormTypeOption('disabled', true);
        yield DateTimeField::new('completedAt', 'Terminé le');
    }
}
