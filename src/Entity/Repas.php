<?php

namespace App\Entity;

use App\Repository\RepasRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RepasRepository::class)]
class Repas
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'repas')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Calendrier $refCalendrier = null;

    #[ORM\ManyToOne(inversedBy: 'repas')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Professeur $Professeur = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRefCalendrier(): ?Calendrier
    {
        return $this->refCalendrier;
    }

    public function setRefCalendrier(?Calendrier $refCalendrier): static
    {
        $this->refCalendrier = $refCalendrier;

        return $this;
    }

    public function getProfesseur(): ?Professeur
    {
        return $this->Professeur;
    }

    public function setProfesseur(?Professeur $Professeur): static
    {
        $this->Professeur = $Professeur;

        return $this;
    }
}
