
# KSeF Client

Biblioteka PHP do komunikacji z API KSeF 2.0 z możliwością autoryzacji przez certyfikat KSeF - jedyną możliwość autoryzacji od 1 stycznia 2027 roku. Biblioteka jest bardzo prosta w użyciu i nie wielka.

## Setup

```
composer require szymon-s292/ksef-client
```

## Klasy biblioteki
* Enum 'KsefMode' - dostępne środowiska KSeF w jakim ma działać biblioteka. (::TEST - Testowe, ::DEMO - Przedprodukcyjne, ::PROD - Produkcyjne)

* Klasa 'Auth' - uwierzytelnianie przez certyfikat, token i możliwość automatycznego nimi zarządzania w bazie MySQL (TokenManager).

```php
use KSeFClient\KsefMode;
use KSeFClient\Auth;

$auth = new Auth(
    "1091041978",   // nip podmiotu
    KsefMode::TEST, // środowisko KSeF
    null,           // token KSeF - puste jeżeli uwierzytelniasz się przez certyfikat
    $ksef_cert,     // certyfikat KSeF 
    $ksef_pkey,     // klucz prywatny KSeF 
    $pkey_pass,     // hasło do klucza prywatnego KSeF 
    false,          // czy używać automatycznego przechowywania tokenów w bazie
);
```

* Klasa 'InteractiveSession' - zarządzanie sesją interaktywną do wysyłki faktur i pobierania ich statusu.

```php
use KSeFClient\KsefMode;
use KSeFClient\InteractiveSession;

$interactive_session = new InteractiveSession(
    $auth,         // klasa 'Auth' utworzona wcześniej do uwierzytelnienia w KSeF
    KsefMode::TEST // środowisko KSeF
);
```

* Klasa 'KsefApi' - bezpośrednia interakcja z API 

```php
use KSeFClient\KsefApi;
use KSeFClient\KsefMode;

$ksef_api = new KsefApi(
    KsefMode::TEST, // środowisko KSeF
);
```

## Przykłady użycia

## Autoryzacja

#### Pobranie tokenów dostępowych przy użyciu autoryzacji certyfikatem.

```php
require_once __DIR__ . '/../vendor/autoload.php';

use KSeFClient\KsefMode;
use KSeFClient\Auth;

// wczytanie certyfikatu i klucza prywatnego
$ksef_cert = file_get_contents(__DIR__ . "/1091041978.crt");
$ksef_pkey = file_get_contents(__DIR__ . "/1091041978.key");
$pkey_pass = "HASLO_KLUCZA_PRYWATNEGO";

// utworzenie klasy z autoryzacją certyfikatem
$auth = new Auth("1091041978", KsefMode::TEST, null, $ksef_cert, $ksef_pkey, $pkey_pass, false);

// wygenerowanie podpisu XAdES, utworzenie sesji i pobranie tokenów autoryzujących
$tokens = $auth->auth_with_xades();

// zwracana wartość
/* 
[
    "access_token" => "",
    "access_token_expires_at" => "",
    "refresh_token" => "",
    "refresh_token_expires_at" => ""
]
*/
```

#### Pobranie tokenów dostępowych przy użyciu autoryzacji tokenem.

```php
require_once __DIR__ . '/../vendor/autoload.php';

use KSeFClient\KsefMode;
use KSeFClient\Auth;

// utworzenie klasy z autoryzacją tokenem
$auth = new Auth("1091041978", KsefMode::TEST, $ksef_token, null, null, null, false);

// autoryzacja tokenem KSeF
$tokens = $auth->auth_with_token();

// zwracana wartość
/* 
[
    "access_token" => "",
    "access_token_expires_at" => "",
    "refresh_token" => "",
    "refresh_token_expires_at" => ""
]
*/
```

** W obu powyższych przpadkach należy samodzielnie zapisać pobrane tokeny **

***

#### Funkcja automatycznego zarządzenia i odświeżania tokenów. Klasa 'Auth' zarządza wtedy tokenami w tabelach `tokens` i `subjects` i odświeża je automatycznie.

1. Należy utworzyć tabele `tokens` i `subjects` (dump struktury znajduje się w pliku [db.php](https://github.com/szymon-s292/ksef-client/db.php)).

2. W tabeli `subjects` dodać swój podmiot dla którego się uwierzytelnia 

```sql
INSERT INTO `subjects`(`nip`,`name`,`address`,`ksef_mode`) VALUES('1091041978','Podmiot1','ul. Testowa 1','TEST');
```

3. Przed użyciem klasy 'Auth' zdefiniować 4 zmienne połączeniowe do bazy MySQL

```php
define('KSEF_DB_HOST', 'localhost');
define('KSEF_DB_USER', 'ksef-client');
define('KSEF_DB_PASS', '');
define('KSEF_DB_NAME', 'ksef-client');
```

** Uwierzytelnienie KSeF z automatycznym odświeżaniem i zapisywaniem tokenów **

Nie należy używać metod `auth_with_token()` lub `auth_with_xades()` ponieważ powodują one rozpoczęcie nowego procesu autoryzacji. Funckja `get_access_token()` wywoła je automatycznie jeżeli token wygaśnie.

```php
require_once __DIR__ . '/../vendor/autoload.php';

use KSeFClient\KsefMode;
use KSeFClient\Auth;

// utworzenie klasy z autoryzacją certyfikatem i automatycznym zapisem/odświeżaniem (ostatni parametr true)
$auth = new Auth("1091041978", KsefMode::TEST, null, $ksef_cert, $ksef_pkey, $pkey_pass, true);

// pobranie tokenów dostępowych z bazy, odświeżenie lub wygenerowanie nowych
$tokens = $auth->get_access_token();

// zwracana wartość
/* 
[
    "access_token" => "",
    "access_token_expires_at" => "",
]
*/
```

### Wysyłka faktur i pobieranie ich statusu i UPO

#### Rozpoczęcie sesji interaktywnej do wysyłki faktur.

```php
require_once __DIR__ . '/../vendor/autoload.php';

use KSeFClient\KsefMode;
use KSeFClient\Auth;
use KSeFClient\InteractiveSession;

$interactive_session = new InteractiveSession(
    $auth, // wcześniej utworzona klasa 'Auth' do uwierzytelnienia w KSeF
    KsefMode::TEST
);

// zwracany jest numer referencyjny sesji interaktywnej
$session_reference_number = $interactive_session->start_session("FA (3)", "1-0E", "FA"); 
```

#### Wysyłka faktury do KSeF w sesji interaktywnej.

```php
$invoice_reference_number = $interactive_session->send($xml); // wysyłka faktury w formacie XML FA (3) do KSeF w sesji interaktywnej, zwracany jest numer referencyjny faktury w sesji interaktywnej
```

#### Zamykanie sesji interaktywnej.

```php
$interactive_session->close_session();
```

#### Pobieranie statusu faktury z sesji interaktywnej.

```php
$ksef_api = new KsefApi(KsefMode::TEST);
$auth = new Auth("1091041978", KsefMode::TEST, null, $ksef_cert, $ksef_pkey, $pkey_pass, false);
$status = $ksef_api->get_invoice_from_session($auth->get_access_token()['access_token'], $session_reference_number, $invoice_reference_number);

// zwracana wartość taka sama jak w [dokumentacji KSeF](https://api.ksef.mf.gov.pl/docs/v2/index.html#tag/Status-wysylki-i-UPO/paths/~1sessions~1%7BreferenceNumber%7D~1invoices~1%7BinvoiceReferenceNumber%7D/get)

/*
[
    "ordinalNumber" => 1,
    "referenceNumber" => "",
    "invoicingDate" => "",
    "upoDownloadUrl" => "",
    "status" => [
        "code" => 200,
        "description" => "",
        "details" => [],
        "extensions" => {}
    ],
]
*/ 
```

#### Pobieranie UPO faktury
```php
$upo_xml = $ksef_api->download_upo($auth->get_access_token()['access_token'], $status['upoDownloadUrl']);
```
####

### Pobieranie faktur

In progress...