<?php

namespace App\Enum;

enum Statut: string
{
    case ATTENTE = 'En attente';
    case PAYE = 'Payé';
    case CONTESTE = 'Contesté';

}
