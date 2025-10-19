<?php

namespace App\Form\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Entity\ParticipantConfirmationInfo;

class MatchConfirmationInfo
{
    /** @var Collection<int, ParticipantConfirmationInfo> */
    private Collection $infos;

    public function __construct(iterable $infos = [])
    {
        $this->infos = new ArrayCollection(is_array($infos) ? $infos : iterator_to_array($infos));
    }

    /** 
     * @return Collection<int, ParticipantConfirmationInfo> 
     */
    public function getInfos(): Collection
    {
        return $this->infos;
    }

    /**
     *  @param Collection<int, ParticipantConfirmationInfo> $infos 
     */
    public function setInfos(Collection $infos): void
    {
        $this->infos = $infos;
    }
}
