<?php

namespace OCA\Facturation\service;

use Dompdf\Dompdf;
use Dompdf\Options;

class TemplateService
{
    private $invoiceHeadTemplate;
    private $invoiceStyle;
    private $invoiceFooterTemplate;
    private $invoiceInformationsTemplate;
    private $invoiceTableTemplate;

    public function __construct()
    {
        $this->invoiceHeadTemplate = file_get_contents('src/template/invoice_head_template.html');
        $this->invoiceStyle = file_get_contents('src/template/invoice_style.html');
        $this->invoiceFooterTemplate = file_get_contents('src/template/invoice_footer_template.html');
        $this->invoiceInformationsTemplate = file_get_contents('src/template/invoice_informations_template.html');
        $this->invoiceTableTemplate = file_get_contents('src/template/invoice_table_template.html');
    }

    public function initTemplate()
    {
        $htmlHead = $this->generateInvoiceHtmlHead();
        $htmlInformations = $this->generateInvoiceHtmlInformations();
        $htmlTable = $this->generateInvoiceHtmlTable();
        $htmlFooter = $this->generateInvoiceFooter();

        $htmlTemplate =
            $htmlHead .
            '<body> <div> <div class="invoice">' .
                $htmlInformations .
                $htmlTable .
                $htmlFooter .
            '</div> </div> </body>';

            return $htmlTemplate;
    }

    private function generateInvoiceHtmlHead()
    {
        return str_replace('{{ style }}',$this->invoiceStyle, $this->invoiceHeadTemplate);
    }

    private function generateInvoiceHtmlInformations()
    {
        $invoicer = $_ENV['INVOICER'];
        $billed = $_ENV['BILLED'];
        $ibanSwift = $_ENV['IBAN_SWIFT'];

        $invoiceInformationsTemplate = str_replace('{{ invoicer }}', $invoicer, $this->invoiceInformationsTemplate);
        $invoiceInformationsTemplate = str_replace('{{ ibanSwift }}', $ibanSwift, $invoiceInformationsTemplate);
        $invoiceInformationsTemplate = str_replace('{{ billed }}', $billed, $invoiceInformationsTemplate);
        $invoiceDate = date('Y-m-d');
        $invoiceReference = $invoiceDate . '_' . uniqid();
        $invoiceInformationsTemplate = str_replace('{{ invoiceReference }}', $invoiceReference, $invoiceInformationsTemplate);
        $invoiceInformationsTemplate = str_replace('{{ invoiceDate }}', $invoiceDate, $invoiceInformationsTemplate);

        return $invoiceInformationsTemplate;
    }


    private function generateInvoiceHtmlTable()
    {
        return $this->invoiceTableTemplate;
    }

    private function generateInvoiceFooter()
    {
        return $this->invoiceFooterTemplate;
    }
}
