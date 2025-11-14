<?php

namespace App\Form;

use App\Entity\Description;
use App\Entity\Professeur;
use App\Entity\Promo;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DescriptionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('lundi')
            ->add('mardi')
            ->add('mercredi')
            ->add('jeudi')
            ->add('vendredi')
            ->add('refProfesseur', EntityType::class, [
                'class' => Professeur::class,
                'choice_label' => function(Professeur $prof) {
                    return $prof->getNom() . ' ' . $prof->getPrenom(); // Pour faire simple, ici ça permet d'afficher le nom et le prénom des profs au lieu d'afficher leur ID. C'est bien mieux visuellement parlant et ça simplifie grandement le travail.
                },
                'mapped' => false,
                'multiple' => true,
                'placeholder' => 'Sélectionner un professeur',
            ])
            ->add('refPromo', EntityType::class, [
                'class' => Promo::class,
                'choice_label' => function(Promo $promo) {
                    return $promo->getAnneeDebut() . ' - ' . $promo->getAnneeFin();
                },
                'placeholder' => 'Sélectionner une promotion',
            ])
            ->add('prix_u')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Description::class,
        ]);
    }
}


