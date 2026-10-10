<?php

namespace App\Twig\Runtime;

use App\Entity\Group;
use App\Service\GroupColors;
use Twig\Extension\RuntimeExtensionInterface;

class GroupColorRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private GroupColors $groupColors,
    ) {
    }

    public function groupColor(?Group $group): ?string
    {
        return $group === null ? null : $this->groupColors->colorOf($group);
    }
}
