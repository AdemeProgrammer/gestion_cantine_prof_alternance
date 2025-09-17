<?php

namespace App\Enum;

enum MoyenPaiement: string
{
    case CB = 'Carte Bancaire';
    case CHEQUE = 'Chèque';
    case ESPECE = 'Espece';
    case PRELEVEMENT = 'Prélèvement';

}
