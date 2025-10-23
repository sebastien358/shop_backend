<?php

namespace App\Entity;

use App\Repository\CommandRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: CommandRepository::class)]
class Command
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['commands'])]
    private ?int $id = null;

    #[ORM\Column(length: 125)]
    #[Groups(['commands'])]
    private ?string $firstName = null;

    #[ORM\Column(length: 125)]
    #[Groups(['commands'])]
    private ?string $lastName = null;

    #[ORM\Column(length: 255)]
    #[Groups(['commands'])]
    private ?string $address = null;

    #[ORM\Column(length: 50)]
    #[Groups(['commands'])]
    private ?string $zipCode = null;

    #[ORM\Column(length: 255)]
    #[Groups(['commands'])]
    private ?string $city = null;

    #[ORM\Column(length: 255)]
    #[Groups(['commands'])]
    private ?string $country = null;

    #[ORM\Column(length: 50)]
    #[Groups(['commands'])]
    private ?string $phoneNumber = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'commands')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['commands'])]
    private ?User $user = null;

    #[ORM\OneToMany(targetEntity: CommandItems::class, mappedBy: 'command', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['commands'])]
    private Collection $commandItems;

    public function __construct()
    {
        $this->commandItems = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getZipCode(): ?string
    {
        return $this->zipCode;
    }

    public function setZipCode(string $zipCode): static
    {
        $this->zipCode = $zipCode;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(string $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(string $phoneNumber): static
    {
        $this->phoneNumber = $phoneNumber;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getCommandItems(): Collection
    {
        return $this->commandItems;
    }

    public function addCommandItem(CommandItems $commandItem): self
    {
        if (!$this->commandItems->contains($commandItem)) {
            $this->commandItems->add($commandItem);
            $commandItem->setCommand($this);
        }

        return $this;
    }

    public function removeCommandItem(CommandItems $commandItem): self
    {
        if ($this->commandItems->contains($commandItem)) {
            $this->commandItems->removeElement($commandItem);
            $commandItem->setCommand(null);
        }

        return $this;
    }
}
