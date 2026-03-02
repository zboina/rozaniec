<?php

namespace Rozaniec\RozaniecBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Rozaniec\RozaniecBundle\Model\RozaniecUserInterface;
use Rozaniec\RozaniecBundle\Repository\UczestnikRepository;

#[ORM\Entity(repositoryClass: UczestnikRepository::class)]
#[ORM\Table(name: 'rozaniec_uczestnik')]
#[ORM\UniqueConstraint(name: 'uczestnik_roza_pozycja', columns: ['roza_id', 'pozycja'])]
class Uczestnik
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Roza::class, inversedBy: 'uczestnicy')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Roza $roza = null;

    #[ORM\Column(nullable: true)]
    private ?int $pozycja = null;

    #[ORM\Column(length: 100)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    private ?string $lastName = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telefon = null;

    #[ORM\Column(type: 'json', options: ['default' => '["email"]'])]
    private array $notifyChannels = ['email'];

    #[ORM\ManyToOne(targetEntity: RozaniecUserInterface::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?RozaniecUserInterface $user = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRoza(): ?Roza
    {
        return $this->roza;
    }

    public function setRoza(?Roza $roza): static
    {
        $this->roza = $roza;
        return $this;
    }

    public function getPozycja(): ?int
    {
        return $this->pozycja;
    }

    public function setPozycja(?int $pozycja): static
    {
        $this->pozycja = $pozycja;
        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getFullName(): string
    {
        return trim(($this->firstName ?? '') . ' ' . ($this->lastName ?? ''));
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getTelefon(): ?string
    {
        return $this->telefon;
    }

    public function setTelefon(?string $telefon): static
    {
        $this->telefon = $telefon;
        return $this;
    }

    /** @return string[] */
    public function getNotifyChannels(): array
    {
        return $this->notifyChannels;
    }

    /** @param string[] $channels */
    public function setNotifyChannels(array $channels): static
    {
        $this->notifyChannels = $channels;
        return $this;
    }

    public function hasNotifyChannel(string $channel): bool
    {
        return in_array($channel, $this->notifyChannels, true);
    }

    /**
     * Zwraca kanały, które uczestnik MA włączone ORAZ posiada dane kontaktowe.
     * email w notifyChannels + email !== null → email
     * sms w notifyChannels + telefon !== null → sms
     *
     * @return string[]
     */
    public function getEffectiveChannels(): array
    {
        $effective = [];

        if (in_array('email', $this->notifyChannels, true) && $this->email !== null) {
            $effective[] = 'email';
        }

        if (in_array('sms', $this->notifyChannels, true) && $this->telefon !== null) {
            $effective[] = 'sms';
        }

        return $effective;
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function __toString(): string
    {
        return $this->getFullName();
    }
}
