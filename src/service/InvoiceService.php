<?php

namespace OCA\Facturation\service;

use Dompdf\Dompdf;
use Dompdf\Options;

class invoiceService
{
    private $dompdf;
    private $options;
    private $templateService;
    private const VAT = 0.2;

    private $presentationPrices = [
        '1' => 25,
        '2' => 30,
        '3' => 35,
        '4' => 40,
        '5' => 45,
        '6' => 50,
        '7' => 55,
    ];

    private $mentoringPrices = [
        '1' => '20',
        '2' => '25',
        '3' => '30',
        '4' => '35',
        '5' => '40',
        '6' => '45',
        '7' => '50',
    ];

    public function __construct()
    {
        $this->templateService = new TemplateService();
        $this->options = new Options();
        $this->options->set('isHtml5ParserEnabled', true);
        $this->options->set('isRemoteEnabled', true);

        $this->dompdf = new Dompdf($this->options);
    }

    public function generateinvoice($invoiceDatas)
    {
        $htmlcssTemplate = $this->templateService->initTemplate();
        $datas = $this->applyInvoiceRules($invoiceDatas);
        $completeInvoiceDatas = $this->calculateTotals($datas);
        $htmlInvoice = $this->buildInvoceView($htmlcssTemplate, $completeInvoiceDatas);
        $this->dompdf->loadHtml($htmlInvoice);
        $this->dompdf->render();
        $output = $this->dompdf->output();
        $invoiceFileName = 'invoice_' . date('Y-m-d') . '.pdf';
        file_put_contents($invoiceFileName, $output);
    }

    private function applyInvoiceRules($invoiceDatas)
    {
        $formattedData = [];
        foreach ($invoiceDatas as $key => $invoiceData) {
            $line = [];
            $sessionDate = new \DateTime($invoiceData['sessionDate']);
            $sessionDate->modify('+1 hour'); // Add 1 hour
            $line['photo'] = $invoiceData['recipient']['profilePicture'];
            $line['studentName'] = $invoiceData['recipient']['displayableName'];
            $line['sessionDate'] = $sessionDate->format('Y-m-d');
            $line['sessionTime'] = $sessionDate->format('H:i');
            $line['sessionType'] = $invoiceData['type'];
            $line['sessionStatus'] = $this->getStatus($invoiceData['status']);
            $line['projectLevel'] = $this->wichTypeOfProject($invoiceData);
            $line['projectTitle'] = $invoiceData['projectTitle'];

            // on mentoring type in case of annnulation durationDetails is null
            if ($invoiceData['durationDetails'] === null) {
                $line['sessionDurations'] = null;
            } else {
                $line['sessionDurations'] = $this->getDurations($invoiceData['durationDetails'], $invoiceData['sessionTerms']);
            }

            // exceptions
            $line['sessionInExpection'] = $invoiceData['videoConference']['unusedReason'] == null ? false : true;
            $line['sessionPrices'] = $this->getPrices(
                $line['studentName'],
                $line['sessionType'],
                $line['projectLevel'],
                $line['sessionDurations'],
                $line['sessionInExpection'],
                $invoiceData['status']
            );
            $formattedData[$key] = $line;
        }

        return $formattedData;
    }

    private function getStatus($status)
    {
        switch ($status) {
            case 'completed':
                return 'Terminée';
            case 'canceled':
                return 'Annulée';
            case 'marked student as absent':
                return 'Etudiant Absent';
            case 'late canceled':
                return 'Annulation tardive';
        }
    }

    private function wichTypeOfProject($projectLevel)
    {
        switch ($projectLevel['type']) {
            case 'mentoring':
                return $projectLevel['projectMentoringLevel'];
            case 'presentation':
                return $projectLevel['projectPresentationLevel'];
        }
    }

    private function getDurations($durationsDetails, $sessionTerms)
    {
        $sessionsDurations = [];
        foreach ($durationsDetails as $key => $durationsDetail) {
            switch ($key) {
                case 'billedDuration':
                    preg_match('/PT(?:(\d+)M)?(?:(\d+)S)?/', $durationsDetail, $matches);
                    $sessionsDurations['billedDuration'] = [
                        'minutes' => (int) $matches[1] ?? 0,
                        'seconds' => (int) $matches[2] ?? 0,
                        'string' => ($matches[1] ?? '0') . ' Minutes ' . ($matches[2] ?? '0') . ' Seconde '
                    ];
                    break;
                case 'effectiveDuration':
                    preg_match('/PT(?:(\d+)M)?(?:(\d+)S)?/', $durationsDetail, $matches);
                    $sessionsDurations['effectiveDuration'] = [
                        'minutes' => (int) $matches[1] ?? 0,
                        'seconds' => (int) $matches[2] ?? 0,
                        'string' => ($matches[1] ?? '0') . ' Minutes ' . ($matches[2] ?? '0') . ' Seconde '
                    ];
                    break;
                case 'waitingDurations':
                    preg_match('/PT(?:(\d+)M)?(?:(\d+)S)?/', $durationsDetail[0]['duration'], $matches);
                    $sessionsDurations['mentorWaitingDuration'] = [
                        'minutes' => ($matches[1] === "" ? '0' : (int) $matches[1]),
                        'seconds' => ($matches[2] === "" ? '0' : (int) $matches[2]),
                        'string' => ($matches[1] === "" ? '0' : (int) $matches[1]) . ' Minutes '.
                                    ($matches[2] === "" ? '0' : (int) $matches[2]) . ' Seconde '
                    ];
                    break;
                default:
                    break;
            }
        }

        // on presentation type there is no sessionTerms
        if ($sessionTerms !== null) {
            preg_match('/PT(?:(\d+)M)?(?:(\d+)S)?/', $sessionTerms['duration'], $matches);
            $sessionsDurations['baseDuration'] = [
                'minutes' => (int) $matches[1] ?? 0,
                'string' => ($matches[1] ?? 0) . ' Minutes'
            ];
        } else {
            $sessionsDurations['baseDuration'] = [
                'minutes' => 30,
                'string' => '30 Minutes'
            ];
        }

        return $sessionsDurations;
    }

    private function getPrices($id, $projectType, $projectLevel, $sessionDurations, $isInException, $sessionStatus)
    {
        $sessionPrices = [];
        $coefficient = 1;
        $basePrice = ($projectType === 'presentation') ? $this->presentationPrices[$projectLevel] : $this->mentoringPrices[$projectLevel];
        $sessionPrices['basePrice'] = $basePrice;

        /**
         *  case for all type
         */
        //canceled case
        if ($sessionStatus === 'canceled') {
            $sessionPrices['billedPrice'] = 0;
            return $sessionPrices;
        }

        /**
         *  case for presentation type
         */
        if ($projectType === 'presentation') {
            if($sessionStatus === 'marked student as absent' || $sessionStatus === 'late canceled') {
                $sessionPrices['billedPrice'] = $basePrice * 0.5;
                return $sessionPrices;
            }
            $sessionPrices['billedPrice'] = $basePrice;
            return $sessionPrices;
        }


        /**
         *  case for mentoring type
         */

        switch ($sessionDurations['baseDuration']['minutes']) {
            case 15:
                $coefficient *= 0.4;
                break;
            case 30:
                $coefficient *= 0.72;
                break;
            case 45:
                $coefficient *= 1;
                break;
            case 60:
                $coefficient *= 1.2;
                break;
        }

        // absent case,late canceled case
        if($sessionStatus === 'marked student as absent' || $sessionStatus === 'late canceled') {
            $sessionPrices['billedPrice'] = $basePrice * $coefficient * 0.5;
            return $sessionPrices;
        }

        // exception case
        if ($isInException) {
            $sessionPrices['billedPrice'] = $basePrice * $coefficient;

            return $sessionPrices;
        }

        $sessionDurationInsecond = $sessionDurations['billedDuration']['minutes'] * 60 + $sessionDurations['billedDuration']['seconds'];
        $baseDurationInsecond = $sessionDurations['baseDuration']['minutes'] * 60;
        $percentOfBaseDuration = ($sessionDurationInsecond / $baseDurationInsecond) * 100;

        switch ($percentOfBaseDuration) {
            case $percentOfBaseDuration < 50:
                $coefficient *= 0;
                break;
            case $percentOfBaseDuration >= 50 && $percentOfBaseDuration < 100:
                $coefficient *= 0.5;
                break;
            case $percentOfBaseDuration >= 100:
                $coefficient *= 1;
                break;
        }

        $sessionPrices['basePrice'] = $basePrice;
        $sessionPrices['billedPrice'] = $basePrice * $coefficient;

        return $sessionPrices;
    }

    private function calculateTotals($datas)
    {
        $totals = [];
        foreach ($datas as $data) {
            $totals['totalWitoutVat'] += $data['sessionPrices']['billedPrice'];
        }
        $totals['totalVat'] = $totals['totalWitoutVat'] * self::VAT;
        $totals['totalWithVAT'] = $totals['totalWitoutVat'] + $totals['totalVat'];

        $datas['total'] = $totals;

        return $datas;
    }


    private function buildInvoceView($htmlcssTemplate, $completeInvoiceDatas)
    {
        $tableRows = '';

        foreach ($completeInvoiceDatas as $key => $invoiceData) {
            if ($key !== 'total') {
                $tableRows .=
                '<tr>'.
                    '<td class="table-student">'.
                        '<img src=' . htmlspecialchars($invoiceData['photo']) . ' class="photo" />'.
                        '<br />'.
                        htmlspecialchars($invoiceData['studentName']).
                    '</td>'.
                    '<td class="table-date">'.
                        htmlspecialchars($invoiceData['sessionDate']).
                        '<br />'.
                        ' à '.
                        '<br />'.
                        htmlspecialchars($invoiceData['sessionTime']).
                    '</td>'.
                    '<td class="table-title">' . htmlspecialchars($invoiceData['projectTitle']) . '</td>'.
                    '<td class="table-type">' .
                        htmlspecialchars($invoiceData['sessionType']).
                        '<br />'.
                        'Niveau ' . htmlspecialchars($invoiceData['projectLevel']).
                    '</td>'.
                    '<td class="table-status">' .
                        htmlspecialchars($invoiceData['sessionStatus']).
                        '<br />'.
                        (htmlspecialchars($invoiceData['sessionInExpection']) === '1' ? 'Exception' : '').
                    '</td>'.

                    '<td class="table-duration">'.
                        (($invoiceData['sessionDurations'] === null) ? 'NC' :
                        '- Durée facturée : '.htmlspecialchars($invoiceData['sessionDurations']['billedDuration']['string']).
                        '<br />'.
                        '- Durée effective : '.htmlspecialchars($invoiceData['sessionDurations']['effectiveDuration']['string']).
                        '<br />'.
                        '- Temps d\'attente : '.htmlspecialchars($invoiceData['sessionDurations']['mentorWaitingDuration']['string']).
                        '<br />'.
                        '- Temps de base : '.htmlspecialchars($invoiceData['sessionDurations']['baseDuration']['string'])).
                    '</td>'.
                    '<td class="table-type">' . htmlspecialchars($invoiceData['sessionPrices']['basePrice']) . ' € </td>'.
                    '<td class="table-type">' . htmlspecialchars($invoiceData['sessionPrices']['billedPrice']) . ' € </td>'.
                '</tr>';
            }
        }

        $invoiceWithData = str_replace('{{ invoiceDatas }}', $tableRows, $htmlcssTemplate);
        $invoiceWithData = str_replace('{{ totalWitoutVat }}', $completeInvoiceDatas['total']['totalWitoutVat'], $invoiceWithData);
        $invoiceWithData = str_replace('{{ totalVat }}', $completeInvoiceDatas['total']['totalVat'], $invoiceWithData);
        $invoiceWithData = str_replace('{{ totalWithVAT }}', $completeInvoiceDatas['total']['totalWithVAT'], $invoiceWithData);      

        return $invoiceWithData;
    }
}
