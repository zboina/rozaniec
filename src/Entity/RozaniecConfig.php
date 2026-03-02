<?php

namespace Rozaniec\RozaniecBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Rozaniec\RozaniecBundle\Repository\RozaniecConfigRepository;

#[ORM\Entity(repositoryClass: RozaniecConfigRepository::class)]
#[ORM\Table(name: 'rozaniec_config')]
class RozaniecConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private ?string $klucz = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $wartosc = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKlucz(): ?string
    {
        return $this->klucz;
    }

    public function setKlucz(string $klucz): static
    {
        $this->klucz = $klucz;
        return $this;
    }

    public function getWartosc(): ?string
    {
        return $this->wartosc;
    }

    public function setWartosc(?string $wartosc): static
    {
        $this->wartosc = $wartosc;
        return $this;
    }
}
