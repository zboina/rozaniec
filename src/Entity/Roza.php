<?php

namespace Rozaniec\RozaniecBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Rozaniec\RozaniecBundle\Repository\RozaRepository;

#[ORM\Entity(repositoryClass: RozaRepository::class)]
#[ORM\Table(name: 'rozaniec_roza')]
class Roza
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nazwa = null;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $ostatniaRotacja = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Uczestnik> */
    #[ORM\OneToMany(targetEntity: Uczestnik::class, mappedBy: 'roza', orphanRemoval: true)]
    private Collection $uczestnicy;

    public function __construct()
    {
        $this->uczestnicy = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
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

    public function getOstatniaRotacja(): ?string
    {
        return $this->ostatniaRotacja;
    }

    public function setOstatniaRotacja(?string $ostatniaRotacja): static
    {
        $this->ostatniaRotacja = $ostatniaRotacja;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Uczestnik> */
    public function getUczestnicy(): Collection
    {
        return $this->uczestnicy;
    }

    public function addUczestnik(Uczestnik $uczestnik): static
    {
        if (!$this->uczestnicy->contains($uczestnik)) {
            $this->uczestnicy->add($uczestnik);
            $uczestnik->setRoza($this);
        }
        return $this;
    }

    public function removeUczestnik(Uczestnik $uczestnik): static
    {
        if ($this->uczestnicy->removeElement($uczestnik)) {
            if ($uczestnik->getRoza() === $this) {
                $uczestnik->setRoza(null);
            }
        }
        return $this;
    }

    public function __toString(): string
    {
        return $this->nazwa ?? '';
    }
}
