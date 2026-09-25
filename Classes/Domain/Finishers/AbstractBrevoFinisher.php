<?php

declare(strict_types=1);

namespace Remind\Brevo\Domain\Finishers;

use Brevo\Contacts\ContactsClient;
use Remind\Brevo\Service\BrevoService;
use TYPO3\CMS\Form\Domain\Finishers\AbstractFinisher;

abstract class AbstractBrevoFinisher extends AbstractFinisher
{
    protected ContactsClient $contactsClient;

    public function __construct(
        BrevoService $brevoService,
    ) {
        $this->contactsClient = $brevoService->getContactsClient();
    }
}
