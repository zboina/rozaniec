<?php

namespace Rozaniec\RozaniecBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Rozaniec\RozaniecBundle\Repository\KolejnoscRepository;

#[ORM\Entity(repositoryClass: KolejnoscRepository::class)]
#[ORM\Table(name: 'rozaniec_kolejnosc')]
class Kolejnosc
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $numer = null;

    /** @var Collection<int, Tajemnica> */
    #[ORM\OneToMany(targetEntity: Tajemnica::class, mappedBy: 'kolejnosc')]
    private Collection $tajemnice;

    public function __construct()
    {
        $this->tajemnice = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumer(): ?int
    {
        return $this->numer;
    }

    public function setNumer(int $numer): static
    {
        $this->numer = $numer;
        return $this;
    }

    /** @return Collection<int, Tajemnica> */
    public function getTajemnice(): Collection
    {
        return $this->tajemnice;
    }

    public function __toString(): string
    {
        return (string) $this->numer;
    }
}
