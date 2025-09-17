<?php

namespace App\Entity;

use App\Repository\PromoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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
        $this->calendriers = new ArrayCollection();
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

    /**
     * @return Collection<int, Description>
     */
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
            // set the owning side to null (unless already changed)
            if ($description->getRefPromo() === $this) {
                $description->setRefPromo(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Calendrier>
     */
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
            // set the owning side to null (unless already changed)
            if ($calendrier->getRefPromo() === $this) {
                $calendrier->setRefPromo(null);
            }
        }

        return $this;
    }
}
