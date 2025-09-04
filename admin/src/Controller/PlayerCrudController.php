<?php

namespace Admin\Controller;

use App\Entity\Player;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

class PlayerCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Player::class;
    }
}
