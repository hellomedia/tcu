<?php

namespace App\Twig\Runtime;

use App\Entity\Registration;
use App\Service\AvailabilityGrid;
use Twig\Extension\RuntimeExtensionInterface;

class AvailabilityRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private AvailabilityGrid $grid,
    ) {
    }

    /**
     * @return array{status: string, answered: int, partial: int, total: int}
     */
    public function availabilitySummary(Registration $registration): array
    {
        return $this->grid->summary($registration);
    }
}
