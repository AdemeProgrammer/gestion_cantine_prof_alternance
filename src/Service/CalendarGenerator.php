<?php

namespace App\Service;

use App\Entity\Promo;
use App\Entity\Calendrier;
use App\Enum\TypeJour;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CalendarGenerator
{
    private HttpClientInterface $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * Génère le calendrier pour une promo
     *
     * @param Promo $promo
     * @return Calendrier[]
     */
    public function generate(Promo $promo): array
    {
        $startYear = $promo->getAnneeDebut();
        $endYear   = $promo->getAnneeFin();

        $startDate = new \DateTime("$startYear-09-01");
        $endDate   = new \DateTime(($endYear) . "-08-31");

        // Récupération jours fériés + vacances
        $feries   = $this->loadJoursFeries($startYear, $endYear);
        $vacances = $this->loadVacancesScolaires($startYear, $endYear);

        $results = [];

        $interval = new \DateInterval('P1D');
        $period   = new \DatePeriod($startDate, $interval, $endDate->modify('+1 day'));

        foreach ($period as $date) {
            $cal = new Calendrier();
            $cal->setRefPromo($promo);
            $cal->setDate($date);

            $dayStr = $date->format('Y-m-d');

            if (in_array($dayStr, $feries, true)) {
                $cal->setTypeJour(TypeJour::FERIE);
            } elseif ($this->inVacances($date, $vacances)) {
                $cal->setTypeJour(TypeJour::VACANCES);
            } elseif (in_array($date->format('N'), ['6', '7'])) {
                $cal->setTypeJour(TypeJour::WEEKEND);
            } else {
                $cal->setTypeJour(TypeJour::SEMAINE);
            }

            $results[] = $cal;
        }

        return $results;
    }

    /**
     * Charge les jours fériés depuis l’API nager.at
     */
    private function loadJoursFeries(int $startYear, int $endYear): array
    {
        $dates = [];

        for ($year = $startYear; $year <= $endYear; $year++) {
            $url = "https://date.nager.at/api/v3/PublicHolidays/$year/FR";
            $response = $this->client->request('GET', $url)->toArray();

            foreach ($response as $ferie) {
                $dates[] = $ferie['date']; // format YYYY-MM-DD
            }
        }

        return $dates;
    }

    /**
     * Charge les vacances scolaires depuis data.gouv.fr
     */
    private function loadVacancesScolaires(int $startYear, int $endYear): array
    {
        $start = sprintf('%d-09-01', $startYear);
        $end   = sprintf('%d-08-31', $endYear + 1);

        $baseUrl = "https://data.education.gouv.fr/api/explore/v2.1/catalog/datasets/fr-en-calendrier-scolaire/records";

        // Construction manuelle de l’URL pour éviter l’échappement cassé
        $url = $baseUrl
            . '?select=start_date,end_date,description,zones,annee_scolaire'
            . '&where=' . urlencode("zones = 'Zone C' AND start_date >= '$start' AND start_date <= '$end'")
            . '&order_by=start_date'
            . '&limit=100'
            . '&timezone=Europe/Paris';

        $data = $this->client->request('GET', $url)->toArray();

        $vacances = [];
        foreach ($data['results'] ?? [] as $r) {
            $vacances[] = [
                'start' => new \DateTime($r['start_date']),
                'end'   => new \DateTime($r['end_date']),
                'desc'  => strtolower($r['description']),
            ];
        }

        return $vacances;
    }

    private function inVacances(\DateTime $date, array $vacances): bool
    {
        foreach ($vacances as $v) {
            if ($date >= $v['start'] && $date <= $v['end']) {
                return true;
            }
        }
        return false;
    }
}
