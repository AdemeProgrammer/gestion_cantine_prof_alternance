<?php

namespace App\Entity;

use App\Repository\PromoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: PromoRepository::class)]
#[UniqueEntity(fields: ['annee_debut'], message: 'Une promotion pour {{ value }} existe déjà.')]
#[ORM\HasLifecycleCallbacks]
class Promo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // unicité déjà gérée par ta migration précédente
    #[ORM\Column(unique: true)]
    private ?int $annee_debut = null;

    #[ORM\Column]
    private ?int $annee_fin = null;

    /**
     * @var Collection<int, Description>
     */
    #[ORM\OneToMany(targetEntity: Description::class, mappedBy: 'refPromo')]
    private Collection $descriptions;

    /**
     * @var Collection<int, Calendrier>
     */
    #[ORM\OneToMany(targetEntity: Calendrier::class, mappedBy: 'refPromo')]
    private Collection $calendriers;

    public function __construct()
    {
        $this->descriptions = new ArrayCollection();
        $this->calendriers  = new ArrayCollection();
    }

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
        // calcul immédiat pour la création
        $this->annee_fin = $annee_debut + 1;
        return $this;
    }

    public function getAnneeFin(): ?int
    {
        return $this->annee_fin;
    }

    // setter facultatif (non utilisé par le formulaire)
    public function setAnneeFin(int $annee_fin): static
    {
        $this->annee_fin = $annee_fin;
        return $this;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function computeAnneeFin(): void
    {
        if ($this->annee_debut !== null) {
            $this->annee_fin = $this->annee_debut + 1;
        }
    }

    public function getAnneeLabel(): ?string
    {
        return $this->annee_debut !== null
            ? sprintf('%d-%d', $this->annee_debut, $this->annee_debut + 1)
            : null;
    }

    /** @return Collection<int, Description> */
    public function getDescriptions(): Collection
    {
        return $this->descriptions;
    }

    public function addDescription(Description $description): static
    {
        if (!$this->descriptions->contains($description)) {
            $this->descriptions->add($description);
            $description->setRefPromo($this);
        }
        return $this;
    }

    public function removeDescription(Description $description): static
    {
        if ($this->descriptions->removeElement($description)) {
            if ($description->getRefPromo() === $this) {
                $description->setRefPromo(null);
            }
        }
        return $this;
    }

    /** @return Collection<int, Calendrier> */
    public function getCalendriers(): Collection
    {
        return $this->calendriers;
    }

    public function addCalendrier(Calendrier $calendrier): static
    {
        if (!$this->calendriers->contains($calendrier)) {
            $this->calendriers->add($calendrier);
            $calendrier->setRefPromo($this);
        }
        return $this;
    }

    public function removeCalendrier(Calendrier $calendrier): static
    {
        if ($this->calendriers->removeElement($calendrier)) {
            if ($calendrier->getRefPromo() === $this) {
                $calendrier->setRefPromo(null);
            }
        }
        return $this;
    }
}
