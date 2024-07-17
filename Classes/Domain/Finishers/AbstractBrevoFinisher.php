<?php

declare(strict_types=1);

namespace Remind\Brevo\Domain\Finishers;

use Brevo\Client\Api\ContactsApi;
use Remind\Brevo\Service\BrevoService;
use TYPO3\CMS\Form\Domain\Finishers\AbstractFinisher;

abstract class AbstractBrevoFinisher extends AbstractFinisher
{
    protected ContactsApi $contactsApi;

    public function __construct(
        BrevoService $brevoService,
    ) {
        $this->contactsApi = $brevoService->getContactsApi();
    }
}
