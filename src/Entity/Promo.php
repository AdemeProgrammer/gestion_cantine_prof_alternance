<?php

namespace App\Entity;

use App\Repository\PromoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PromoRepository::class)]
class Promo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $annee_debut = null;

    #[ORM\Column]
    private ?int $annee_fin = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAnneeDebut(): ?int
    {
        return $this->annee_debut;
    }

    public function setAnneeDebut(int $annee_debut): static
    {
        $this->annee_debut = $annee_debut;

        return $this;
    }

    public function getAnneeFin(): ?int
    {
        return $this->annee_fin;
    }

    public function setAnneeFin(int $annee_fin): static
    {
        $this->annee_fin = $annee_fin;

        return $this;
    }
}
