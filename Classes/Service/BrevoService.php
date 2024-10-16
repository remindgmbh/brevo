<?php

declare(strict_types=1);

namespace Remind\Brevo\Service;

use Brevo\Client\Api\ContactsApi;
use Brevo\Client\Configuration;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use TYPO3\CMS\Form\Domain\Finishers\Exception\FinisherException;

class BrevoService
{
    private ContactsApi $contactsApi;

    public function __construct()
    {
        $apiKey = getenv('BREVO_API_KEY');
        if (!$apiKey) {
            throw new FinisherException('Environment variable BREVO_API_KEY is not set', 1729085090);
        }
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
