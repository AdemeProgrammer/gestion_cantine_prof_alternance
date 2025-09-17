<?php

namespace App\Entity;

use App\Enum\TypeJour;
use App\Repository\CalendrierRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CalendrierRepository::class)]
class Calendrier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $date = null;

    #[ORM\Column(enumType: TypeJour::class)]
    private ?TypeJour $type_jour = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTime
    {
        return $this->date;
    }

    public function setDate(\DateTime $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getTypeJour(): ?TypeJour
    {
        return $this->type_jour;
    }

    public function setTypeJour(TypeJour $type_jour): static
    {
        $this->type_jour = $type_jour;

        return $this;
    }
}
