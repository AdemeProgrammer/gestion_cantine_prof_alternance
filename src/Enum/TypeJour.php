<?php

namespace App\Enum;

enum TypeJour: string
{
    case SEMAINE = 'Carte Bancaire';
    case VACANCES = 'Chèque';
    case FERIE = 'Espece';
    case EXAMEN = 'Prélèvement';
    case VACANCES_BTS = 'Vacances BTS';

}
