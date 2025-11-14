<?php

namespace App\Form;

use App\Entity\Description;
use App\Entity\Paiement;
use App\Enum\MoyenPaiement;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PaiementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date_paiement')
            ->add('montant')
            ->add('moyen_paiement', EnumType::class, [
                'class' => MoyenPaiement::class,
                // affichera: Carte Bancaire / Chèque / Espece / Prélèvement
                'choice_label' => function (MoyenPaiement $choice): string {
                    return $choice->value;
                },
                'placeholder' => 'Choisir un moyen de paiement',
            ])
            ->add('ref_description_id', EntityType::class, [
                'class' => Description::class,
                // Nom Prénom du prof - année de début (voire 2024-2026)
                'choice_label' => function (Description $description): string {
                    $prof = $description->getRefProfesseur();
                    $promo = $description->getRefPromo();

                    // texte prof
                    $profTxt = 'Professeur';
                    if ($prof) {
                        $prenom = $prof->getPrenom() ?? '';
                        $nom    = $prof->getNom() ?? '';
                        $full   = trim($prenom.' '.$nom);

                        if ($full !== '') {
                            $profTxt = $full;
                        } elseif ($prof->getEmail()) {
                            $profTxt = $prof->getEmail();
                        }
                    }

                    // texte promo
                    $promoTxt = '';
                    if ($promo) {
                        $anneeDebut = $promo->getAnneeDebut();
                        $anneeFin   = $promo->getAnneeFin();

                        if ($anneeDebut) {
                            // soit "2024-2026", soit juste "2024" si pas de fin
                            $promoTxt = $anneeFin
                                ? sprintf('%d-%d', $anneeDebut, $anneeFin)
                                : (string) $anneeDebut;
                        }
                    }

                    if ($promoTxt === '') {
                        return $profTxt;
                    }

                    return sprintf('%s - %s', $profTxt, $promoTxt);
                },
                'placeholder' => 'Choisir une description',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Paiement::class,
        ]);
    }
}
