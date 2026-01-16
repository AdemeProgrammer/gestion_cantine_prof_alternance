<?php

namespace App\Entity;

use App\Enum\Statut;
use App\Repository\FacturationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FacturationRepository::class)]
class Facturation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $mois = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $montant_total = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $montant_regle = null;

    #[ORM\Column(enumType: Statut::class)]
    private ?Statut $statut = null;

    #[ORM\Column]
    private ?int $nb_repas = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $report_m_1 = null;

    #[ORM\ManyToOne(inversedBy: 'facturations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Professeur $refProfesseur = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMois(): ?string
    {
        return $this->mois;
    }

    public function setMois(string $mois): static
    {
        $this->mois = $mois;

        return $this;
    }

    public function getMontantTotal(): ?string
    {
        return $this->montant_total;
    }

    public function setMontantTotal(string $montant_total): static
    {
        $this->montant_total = $montant_total;

        return $this;
    }

    public function getMontantRegle(): ?string
    {
        return $this->montant_regle;
    }

    public function setMontantRegle(string $montant_regle): static
    {
        $this->montant_regle = $montant_regle;

        return $this;
    }

    public function getMontantRestant(): ?string
    {
        if ($this->montant_total === null || $this->montant_regle === null) {
            return null;
        }

        return bcsub($this->montant_total, $this->montant_regle, 2);
    }

    public function getStatut(): ?Statut
    {
        return $this->statut;
    }

    public function setStatut(Statut $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getNbRepas(): ?int
    {
        return $this->nb_repas;
    }

    public function setNbRepas(int $nb_repas): static
    {
        $this->nb_repas = $nb_repas;

        return $this;
    }

    public function getReportM1(): ?string
    {
        return $this->report_m_1;
    }

    public function setReportM1(string $report_m_1): static
    {
        $this->report_m_1 = $report_m_1;

        return $this;
    }

    public function getRefProfesseur(): ?Professeur
    {
        return $this->refProfesseur;
    }

    public function setRefProfesseur(?Professeur $refProfesseur): static
    {
        $this->refProfesseur = $refProfesseur;

        return $this;
    }
}
