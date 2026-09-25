<?php

declare(strict_types=1);

namespace Remind\Brevo\Domain\Finishers;

use Brevo\Contacts\Requests\RemoveContactFromListRequest;
use Brevo\Contacts\Types\RemoveContactFromListRequestBodyEmails;
use Brevo\Exceptions\BrevoApiException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class BrevoUnsubscribeFinisher extends AbstractBrevoFinisher
{
    protected function executeInternal(): ?string
    {
        $listIds = $this->parseOption('listIds');
        if (!is_array($listIds)) {
            $listIds = GeneralUtility::intExplode(',', (string) $listIds, true);
        }
        $formValues = $this->finisherContext->getFormValues();
        $formRuntime = $this->finisherContext->getFormRuntime();
        $formDefinition = $formRuntime->getFormDefinition();

        $emails = [];

        foreach ($formValues as $key => $value) {
            $element = $formDefinition->getElementByIdentifier($key);
            if ($element) {
                $properties = $element->getProperties();
                $brevoAttribute = $properties['brevoAttribute'] ?? null;
                if ($brevoAttribute === 'EMAIL') {
                    $emails[] = $value;
                    break;
                }
            }
        }

        if (!empty($emails)) {
            foreach ($listIds as $listId) {
                try {
                    $this->contactsClient->removeContactFromList(
                        $listId,
                        new RemoveContactFromListRequest([
                            'body' => new RemoveContactFromListRequestBodyEmails([
                                'emails' => $emails,
                            ]),
                        ]),
                    );
                } catch (BrevoApiException $e) {
                    // catch exception if contact is not in list
                    if ($e->getCode() !== 400) {
                        throw $e;
                    }
                }
            }
        }

        return null;
    }
}
