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

    #[ORM\Column]
    private ?bool $est_consomme = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isEstConsomme(): ?bool
    {
        return $this->est_consomme;
    }

    public function setEstConsomme(bool $est_consomme): static
    {
        $this->est_consomme = $est_consomme;

        return $this;
    }
}
