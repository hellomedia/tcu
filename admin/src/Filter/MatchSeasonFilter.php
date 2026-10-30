<?php

namespace Admin\Filter;

use App\Entity\Season;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\EntityFilterType;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\ComparisonType;

/**
 * Season of a match: a match has no season of its own, it is the season of its group
 */
final class MatchSeasonFilter implements FilterInterface
{
    use FilterTrait;

    public static function new(string $propertyName = 'season', ?string $label = 'Saison'): self
    {
        return (new self())
            ->setFilterFqcn(self::class)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(EntityFilterType::class)
            ->setFormTypeOption('value_type_options.class', Season::class);
    }

    public function apply(QueryBuilder $qb, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $season = $filterDataDto->getValue();

        if (!$season instanceof Season) {
            return;
        }

        // the 'ea_' prefix avoids reserved words as alias ('group')
        $groupAlias = 'ea_' . $filterDataDto->getParameterName() . '_group';
        $parameterName = $filterDataDto->getParameterName();

        $qb->leftJoin(sprintf('%s.group', $filterDataDto->getEntityAlias()), $groupAlias);

        if (ComparisonType::NEQ === $filterDataDto->getComparison()) {
            $qb->andWhere(sprintf('%1$s.season != :%2$s OR %1$s.season IS NULL', $groupAlias, $parameterName));
        } else {
            $qb->andWhere(sprintf('%s.season = :%s', $groupAlias, $parameterName));
        }

        $qb->setParameter($parameterName, $season);
    }
}
