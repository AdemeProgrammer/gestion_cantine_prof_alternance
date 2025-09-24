<?php

namespace App\Enum;

enum TypeJour: string
{
    case SEMAINE = 'Semaine';
    case VACANCES = 'Vacances';
    case FERIE = 'Férié';
    case WEEKEND = 'Weekend';
    case EXAMEN = 'Examen';
    case VACANCES_BTS = 'Vacances BTS';

}
