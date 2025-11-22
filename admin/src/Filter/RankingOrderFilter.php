<?php

namespace Admin\Filter;

use App\Enum\Ranking;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\ComparisonFilterType as TypeComparisonFilterType;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\ComparisonFilterType;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\ComparisonType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;

final class RankingOrderFilter implements FilterInterface
{
    use FilterTrait;

    public static function new(string $propertyName = 'rankingOrder', ?string $label = 'Ranking'): self
    {
        return (new self())
            ->setFilterFqcn(self::class)
            ->setProperty($propertyName)   // -> rankingOrder column
            ->setLabel($label)
            ->setFormType(TypeComparisonFilterType::class)
            ->setFormTypeOptions([
                // Value is your enum
                'value_type' => EnumType::class,
                'value_type_options' => [
                    'class' => Ranking::class,
                    'required' => false,
                    'placeholder' => '',
                ],
                // Operator: ≥ or ≤
                'comparison_type_options' => [
                    'choices' => [
                        '=' => ComparisonType::EQ,
                        '≥' => ComparisonType::GTE,
                        '≤' => ComparisonType::LTE,
                    ],
                ],
            ]);
    }

    public function apply(
        QueryBuilder $qb,
        FilterDataDto $filterDataDto,
        ?FieldDto $fieldDto,
        EntityDto $entityDto
    ): void {
        /** @var Ranking|null $ranking */
        $ranking = $filterDataDto->getValue();      // the Enum value
        $comparison = $filterDataDto->getComparison(); // one of ComparisonType::* or null

        if (!$ranking instanceof Ranking || $comparison === null) {
            return;
        }

        // Same mapping you use in setRanking()
        $orderMap  = array_flip(array_column(Ranking::cases(), 'name'));
        $rankOrder = $orderMap[$ranking->name] ?? null;

        if ($rankOrder === null) {
            return;
        }

        $alias    = $filterDataDto->getEntityAlias();  // usually "entity"
        $property = $filterDataDto->getProperty();     // "rankingOrder"

        // Translate comparison + enum → numeric comparison on rankingOrder
        if ($comparison === ComparisonType::EQ) {
            $qb
                ->andWhere(sprintf('%s.%s = :rankingOrder', $alias, $property))
                ->setParameter('rankingOrder', $rankOrder);
        } elseif ($comparison === ComparisonType::GTE) {
            $qb
                ->andWhere(sprintf('%s.%s >= :rankingOrder', $alias, $property))
                ->setParameter('rankingOrder', $rankOrder);
        } elseif ($comparison === ComparisonType::LTE) {
            $qb
                ->andWhere(sprintf('%s.%s <= :rankingOrder', $alias, $property))
                ->setParameter('rankingOrder', $rankOrder);
        }
    }
}
