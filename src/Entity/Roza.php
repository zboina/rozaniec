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

    #[ORM\Column(length: 20, options: ['default' => 'pierwszy_dzien'])]
    private string $rotacjaTryb = 'pierwszy_dzien';

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $rotacjaDzien = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: Uczestnik::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Uczestnik $zelator = null;

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

    public function getRotacjaTryb(): string
    {
        return $this->rotacjaTryb;
    }

    public function setRotacjaTryb(string $rotacjaTryb): static
    {
        $this->rotacjaTryb = $rotacjaTryb;
        return $this;
    }

    public function getRotacjaDzien(): ?int
    {
        return $this->rotacjaDzien;
    }

    public function setRotacjaDzien(?int $rotacjaDzien): static
    {
        $this->rotacjaDzien = $rotacjaDzien;
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

    public function getZelator(): ?Uczestnik
    {
        return $this->zelator;
    }

    public function setZelator(?Uczestnik $zelator): static
    {
        $this->zelator = $zelator;
        return $this;
    }

    public function __toString(): string
    {
        return $this->nazwa ?? '';
    }
}
