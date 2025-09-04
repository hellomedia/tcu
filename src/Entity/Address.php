<?php

namespace App\Entity;

use App\Repository\AddressRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use LongitudeOne\Spatial\PHP\Types\Geography\Point;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AddressRepository::class)]
class Address
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'addresses')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $poster = null;

    #[Assert\NotBlank]
    #[Assert\Length(min: 5, max: 100)]
    #[ORM\Column(length: 255)]
    private ?string $street = null;

    #[Assert\NotBlank]
    #[Assert\Length(min: 4, max: 10)]
    #[ORM\Column(length: 10)]
    private ?string $postalCode = null;

    /**
     * postal city 
     * Can be different than urban area / student city
     */
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 50)]
    #[ORM\Column(length: 40)]
    private ?string $city = null;

    #[Assert\NotBlank]
    #[ORM\Column(nullable: true)]
    private ?float $latitude = null;

    #[Assert\NotBlank]
    #[ORM\Column(nullable: true)]
    private ?float $longitude = null;

    #[ORM\Column(type: 'geography', nullable: true, options: ['geometry_type' => 'POINT', 'srid' => 4326])]
    private ?Point $location = null;

    /**
     * Distance to user location (in meters)
     * Unmapped (depends on user location)
     * Populated in search query when radius filter is active
     */
    private ?int $distance = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?StudentCity $studentCity = null;

    /**
     * @var Collection<int, Listing>
     */
    #[ORM\OneToMany(targetEntity: Listing::class, mappedBy: 'address')]
    private Collection $listings;

    public function __construct()
    {
        $this->listings = new ArrayCollection();
    }

    public function __toString()
    {
        return $this->street . ', ' . $this->postalCode . ' ' . $this->city;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPoster(): ?User
    {
        return $this->poster;
    }

    public function setPoster(?User $poster): static
    {
        $this->poster = $poster;

        return $this;
    }

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function setStreet(string $street): static
    {
        $this->street = $street;

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(string $postalCode): static
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $latitude): static
    {
        $this->latitude = $latitude;

        $this->_updateLocation();

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude): static
    {
        $this->longitude = $longitude;

        $this->_updateLocation();

        return $this;
    }

    public function getStudentCity(): ?StudentCity
    {
        return $this->studentCity;
    }

    public function setStudentCity(?StudentCity $studentCity): static
    {
        $this->studentCity = $studentCity;

        return $this;
    }

    /**
     * @return Collection<int, Listing>
     */
    public function getListings(): Collection
    {
        return $this->listings;
    }

    public function addListing(Listing $listing): static
    {
        if (!$this->listings->contains($listing)) {
            $this->listings->add($listing);
            $listing->setAddress($this);
        }

        return $this;
    }

    public function removeListing(Listing $listing): static
    {
        if ($this->listings->removeElement($listing)) {
            // set the owning side to null (unless already changed)
            if ($listing->getAddress() === $this) {
                $listing->setAddress(null);
            }
        }

        return $this;
    }

    public function getLocation(): ?Point
    {
        return $this->location;
    }

    public function setLocation(?Point $location): static
    {
        $this->location = $location;

        return $this;
    }

    private function _updateLocation(): void
    {
        if ($this->latitude !== null && $this->longitude !== null) {
            $this->location = new Point($this->longitude, $this->latitude); // ATTN: order important ! lng lat
        } else {
            $this->location = null;
        }
    }

    public function setDistance(int|float $distance): static
    {
        $this->distance = (int) $distance;

        return $this;
    }

    public function getDistance(): ?int
    {
        return $this->distance;
    }

    public function getFormattedDistance(): ?string
    {
        if ($this->distance < 1000) {
            return round($this->distance/10) * 10 . ' m';
        }

        return \number_format($this->distance / 1000, 1, ',') . ' km';
    }
}
