<?php

namespace Rozaniec\RozaniecBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Rozaniec\RozaniecBundle\Repository\CzescRepository;

#[ORM\Entity(repositoryClass: CzescRepository::class)]
#[ORM\Table(name: 'rozaniec_czesc')]
class Czesc
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nazwa = null;

    /** @var Collection<int, Tajemnica> */
    #[ORM\OneToMany(targetEntity: Tajemnica::class, mappedBy: 'czesc')]
    private Collection $tajemnice;

    public function __construct()
    {
        $this->tajemnice = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNazwa(): ?string
    {
        return $this->nazwa;
    }

    public function setNazwa(string $nazwa): static
    {
        $this->nazwa = $nazwa;
        return $this;
    }

    /** @return Collection<int, Tajemnica> */
    public function getTajemnice(): Collection
    {
        return $this->tajemnice;
    }

    public function __toString(): string
    {
        return $this->nazwa ?? '';
    }
}
