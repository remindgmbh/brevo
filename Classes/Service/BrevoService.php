<?php

declare(strict_types=1);

namespace Remind\Brevo\Service;

use Brevo\Client\Api\ContactsApi;
use Brevo\Client\Configuration;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

class BrevoService
{
    private ContactsApi $contactsApi;

    public function __construct()
    {
        $apiKey = getenv('BREVO_API_KEY');
        $config = Configuration::getDefaultConfiguration()->setApiKey('api-key', $apiKey);
        $client = new Client([
            RequestOptions::CONNECT_TIMEOUT => 5,
            RequestOptions::TIMEOUT => 5,
        ]);
        $this->contactsApi = new ContactsApi($client, $config);
    }

    public function getContactsApi()
    {
        return $this->contactsApi;
    }
}
