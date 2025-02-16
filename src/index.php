<?php

namespace OCA\Facturation;

require_once __DIR__ . '/../vendor/autoload.php';

use OCA\Facturation\service\InvoiceService;
use Symfony\Component\Dotenv\Dotenv;
use OCA\Facturation\service\OCApiService;

$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/../.env');


/**
 * Load environment variables
 */
$mentorId = $_ENV['MENTOR_ID'];
$bearerToken = $_ENV['BEARER_TOKEN'];
$before = $_ENV['BEFOR'];
$after = $_ENV['AFTER'];


/**
 * Call OpenClassrooms API and get all sessions and presentations and store them in a session.php file
 */
$ocApi = new OCApiService($mentorId, $bearerToken, $before, $after);
$allResults =  $ocApi->getOCSessionsAndPresentationsApi();

/**
 * Generate invoice and store it in a invoice.pdf file
 */
$allResults = include('sessions.php');
$dataToInvoice =  new InvoiceService();
$invoiceData = $dataToInvoice->generateinvoice($allResults);
