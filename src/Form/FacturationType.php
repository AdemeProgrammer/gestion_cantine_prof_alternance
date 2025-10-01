<?php

namespace App\Form;

use App\Entity\Facturation;
use App\Entity\Professeur;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FacturationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('mois')
            ->add('montant_total')
            ->add('montant_regle')
            ->add('montant_restant')
            ->add('statut')
            ->add('nb_repas')
            ->add('report_m_1')
            ->add('refProfesseur', EntityType::class, [
                'class' => Professeur::class,
                'choice_label' => 'id',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Facturation::class,
        ]);
    }
}
