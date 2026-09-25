<?php

declare(strict_types=1);

namespace Remind\Brevo\Service;

use Brevo\Brevo;
use Brevo\Contacts\ContactsClient;
use TYPO3\CMS\Form\Domain\Finishers\Exception\FinisherException;

class BrevoService
{
    private ContactsClient $contactsClient;

    public function __construct()
    {
        $apiKey = getenv('BREVO_API_KEY');
        if (!$apiKey) {
            throw new FinisherException('Environment variable BREVO_API_KEY is not set', 1729085090);
        }
        $client = new Brevo(
            apiKey: $apiKey,
        );
        /** @phpstan-ignore-next-line argument.type */
        $this->contactsClient = $client->getContacts();
    }

    public function getContactsClient(): ContactsClient
    {
        return $this->contactsClient;
    }
}
