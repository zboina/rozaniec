<?php

namespace Rozaniec\RozaniecBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Rozaniec\RozaniecBundle\Model\RozaniecUserInterface;
use Rozaniec\RozaniecBundle\Repository\TajemnicaRepository;

#[ORM\Entity(repositoryClass: TajemnicaRepository::class)]
#[ORM\Table(name: 'rozaniec_tajemnica')]
class Tajemnica
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nazwa = null;

    #[ORM\Column(unique: true)]
    private ?int $pozycja = null;

    #[ORM\ManyToOne(targetEntity: Czesc::class, inversedBy: 'tajemnice')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Czesc $czesc = null;

    #[ORM\ManyToOne(targetEntity: Kolejnosc::class, inversedBy: 'tajemnice')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Kolejnosc $kolejnosc = null;

    #[ORM\ManyToOne(targetEntity: RozaniecUserInterface::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?RozaniecUserInterface $user = null;

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

    public function getPozycja(): ?int
    {
        return $this->pozycja;
    }

    public function setPozycja(int $pozycja): static
    {
        $this->pozycja = $pozycja;
        return $this;
    }

    public function getCzesc(): ?Czesc
    {
        return $this->czesc;
    }

    public function setCzesc(?Czesc $czesc): static
    {
        $this->czesc = $czesc;
        return $this;
    }

    public function getKolejnosc(): ?Kolejnosc
    {
        return $this->kolejnosc;
    }

    public function setKolejnosc(?Kolejnosc $kolejnosc): static
    {
        $this->kolejnosc = $kolejnosc;
        return $this;
    }

    public function getUser(): ?RozaniecUserInterface
    {
        return $this->user;
    }

    public function setUser(?RozaniecUserInterface $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function __toString(): string
    {
        return $this->nazwa ?? '';
    }
}
