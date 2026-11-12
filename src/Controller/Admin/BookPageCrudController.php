<?php

namespace App\Controller\Admin;

use App\Entity\BookPage;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

class BookPageCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return BookPage::class;
    }
}
