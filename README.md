# Requirement
- Docker
- Docker compose
- Composer

# How to use
## 1- Create a .env file
- copy .env.sample to .env
- fill the .env file with your data

```bash
MENTOR_ID=
BEARER_TOKEN=
--------------------------
**You will find this env in your OpenClassrooms account inspection page
```

```bash
BEFOR=2025-01-31T23:00:00Z
AFTER=2024-12-31T23:00:00Z
--------------------------
Billing period
exemple For january :
Before is the last day of january 01-31
After is the last day of decembre 12-31
```

```bash
INVOICER="super mentor, 55 Rue du Faubourg Saint-Honoré, 75008 Paris, Mentor ID: , Registration number: 99 999 999 999 099"
IBAN_SWIFT="FR76 50 51 52 ...etc"
BILLED="Open Classrooms SAS 2 C our de L ile Louviers Paris 75004 France SIREN number: 493 861 363 VAT Number: FR87493861363"
--------------------------
invoicer and billed informations

```

## 2- Start the container
```bash
docker compose up -d
docker exec -ti oc-invoice composer install
```

## 3- Generate the invoice
```bash
composer get-oc-invoice
```

The invoice will be in the folder ./oc-facturation/invoice_*.pdf