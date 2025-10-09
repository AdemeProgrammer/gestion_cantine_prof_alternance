<?php

namespace App\Form;

use App\Entity\Facturation;
use App\Entity\Professeur;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FacturationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('mois', ChoiceType::class, [
                'label' => 'Mois',
                'choices' => [
                    'Janvier' => 'janvier',
                    'Février' => 'février',
                    'Mars' => 'mars',
                    'Avril' => 'avril',
                    'Mai' => 'mai',
                    'Juin' => 'juin',
                    'Juillet' => 'juillet',
                    'Août' => 'août',
                    'Septembre' => 'septembre',
                    'Octobre' => 'octobre',
                    'Novembre' => 'novembre',
                    'Décembre' => 'décembre',
                ],
            ])
            ->add('refProfesseur', EntityType::class, [
                'class' => Professeur::class,
                'choice_label' => fn($prof) => $prof->getNom() . ' ' . $prof->getPrenom(),
                'label' => 'Professeur',
            ])
            ->add('nb_repas', IntegerType::class, [
                'label' => 'Nombre de repas',
                'required' => false,
                'disabled' => true,
            ])
            ->add('montant_total', MoneyType::class, [
                'label' => 'Montant total (€)',
                'currency' => 'EUR',
                'required' => false,
                'disabled' => true,
            ])
            ->add('montant_regle', MoneyType::class, [
                'label' => 'Montant réglé (€)',
                'currency' => 'EUR',
                'required' => false,
            ])
            ->add('montant_restant', MoneyType::class, [
                'label' => 'Montant restant (€)',
                'currency' => 'EUR',
                'required' => false,
                'disabled' => true,
            ])
            ->add('report_m_1')
            ->add('statut');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Facturation::class,
        ]);
    }
}

