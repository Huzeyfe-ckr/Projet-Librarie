<?php

namespace App\Controller\Admin;

use App\Entity\Wishlist;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

class WishlistCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Wishlist::class;
    }
}
