<?php

namespace Admin\Controller\Planning;

use Admin\Controller\DashboardController;
use App\Controller\BaseController;
use App\Entity\Booking;
use App\Entity\Court;
use App\Entity\Date;
use App\Entity\InterfacMatch;
use App\Enum\BookingType;
use App\Form\MatchBookingForm;
use App\Form\MatchType;
use Doctrine\ORM\EntityManager;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MatchController extends BaseController
{
    public function __construct(
        private EntityManager $entityManager,
    )
    { 
    }
}