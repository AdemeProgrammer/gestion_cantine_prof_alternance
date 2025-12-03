<?php

namespace App\Entity;

use App\Repository\DescriptionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DescriptionRepository::class)]
class Description
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?bool $lundi = null;

    #[ORM\Column]
    private ?bool $mardi = null;

    #[ORM\Column]
    private ?bool $mercredi = null;

    #[ORM\Column]
    private ?bool $jeudi = null;

    #[ORM\Column]
    private ?bool $vendredi = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $report = '0';

    #[ORM\ManyToOne(inversedBy: 'descriptions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Professeur $refProfesseur = null;

    #[ORM\ManyToOne(inversedBy: 'descriptions')]
    private ?Promo $refPromo = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $prix_u = null;

    /**
     * @var Collection<int, Paiement>
     */
    #[ORM\OneToMany(targetEntity: Paiement::class, mappedBy: 'ref_description_id')]
    private Collection $paiements;

    public function __construct()
    {
        $this->paiements = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isLundi(): ?bool
    {
        return $this->lundi;
    }

    public function setLundi(bool $lundi): static
    {
        $this->lundi = $lundi;
        return $this;
    }

    public function isMardi(): ?bool
    {
        return $this->mardi;
    }

    public function setMardi(bool $mardi): static
    {
        $this->mardi = $mardi;
        return $this;
    }

    public function isMercredi(): ?bool
    {
        return $this->mercredi;
    }

    public function setMercredi(bool $mercredi): static
    {
        $this->mercredi = $mercredi;
        return $this;
    }

    public function isJeudi(): ?bool
    {
        return $this->jeudi;
    }

    public function setJeudi(bool $jeudi): static
    {
        $this->jeudi = $jeudi;
        return $this;
    }

    public function isVendredi(): ?bool
    {
        return $this->vendredi;
    }

    public function setVendredi(bool $vendredi): static
    {
        $this->vendredi = $vendredi;
        return $this;
    }

    public function getReport(): ?string
    {
        return $this->report;
    }

    public function setReport(string $report): static
    {
        $this->report = $report;
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

    /**
     * Nouveau getter pour Twig
     */
    public function getProfesseur(): ?Professeur
    {
        return $this->refProfesseur;
    }

    public function getRefPromo(): ?Promo
    {
        return $this->refPromo;
    }

    public function setRefPromo(?Promo $refPromo): static
    {
        $this->refPromo = $refPromo;
        return $this;
    }

    public function getPrixU(): ?string
    {
        return $this->prix_u;
    }

    public function setPrixU(string $prix_u): static
    {
        $this->prix_u = $prix_u;
        return $this;
    }

    /**
     * @return Collection<int, Paiement>
     */
    public function getPaiements(): Collection
    {
        return $this->paiements;
    }

    public function addPaiement(Paiement $paiement): static
    {
        if (!$this->paiements->contains($paiement)) {
            $this->paiements->add($paiement);
            $paiement->setRefDescriptionId($this);
        }
        return $this;
    }

    public function removePaiement(Paiement $paiement): static
    {
        if ($this->paiements->removeElement($paiement)) {
            if ($paiement->getRefDescriptionId() === $this) {
                $paiement->setRefDescriptionId(null);
            }
        }
        return $this;
    }
}
