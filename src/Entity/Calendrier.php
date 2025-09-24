<?php

namespace App\Entity;

use App\Enum\TypeJour;
use App\Repository\CalendrierRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    #[ORM\ManyToOne(inversedBy: 'calendriers')]
    private ?Promo $refPromo = null;

    /**
     * @var Collection<int, Repas>
     */
    #[ORM\OneToMany(targetEntity: Repas::class, mappedBy: 'refCalendrier')]
    private Collection $repas;

    public function __construct()
    {
        $this->repas = new ArrayCollection();
    }

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

    public function getRefPromo(): ?Promo
    {
        return $this->refPromo;
    }

    public function setRefPromo(?Promo $refPromo): static
    {
        $this->refPromo = $refPromo;

        return $this;
    }

    /**
     * @return Collection<int, Repas>
     */
    public function getRepas(): Collection
    {
        return $this->repas;
    }

    public function addRepas(Repas $repas): static
    {
        if (!$this->repas->contains($repas)) {
            $this->repas->add($repas);
            $repas->setRefCalendrier($this);
        }

        return $this;
    }
    public function getPromo(): ?Promo
    {
        return $this->promo;
    }

    public function setPromo(?Promo $promo): self
    {
        $this->promo = $promo;
        return $this;
    }

    public function removeRepas(Repas $repas): static
    {
        if ($this->repas->removeElement($repas)) {
            // set the owning side to null (unless already changed)
            if ($repas->getRefCalendrier() === $this) {
                $repas->setRefCalendrier(null);
            }
        }

        return $this;
    }
}
