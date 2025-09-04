<?php

namespace Controlroom\Controller;

use App\Entity\Address;
use App\Entity\Listing;
use App\Enum\Condition;
use App\Enum\OfferType;
use App\Repository\AddressRepository;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

class AddressCrudController extends AbstractCrudController
{

    public static function getEntityFqcn(): string
    {
        return Address::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield AssociationField::new('poster');
        
        yield TextField::new('street');
        yield TextField::new('postalCode');
        yield TextField::new('city', 'Postal City');
        
        yield AssociationField::new('studentCity', 'Student City');

        yield AssociationField::new('listings');

        yield NumberField::new('latitude', 'lat')->setFormTypeOption('scale', 7);
        yield NumberField::new('longitude', 'long')->setFormTypeOption('scale', 7);
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        return $queryBuilder
            ->addSelect('poster')
            ->join('entity.poster', 'poster')
            ->addSelect('studentCity')
            ->join('entity.studentCity', 'studentCity')
            ->addSelect('listings')
            ->join('entity.listings', 'listings')
        ;
    }
}