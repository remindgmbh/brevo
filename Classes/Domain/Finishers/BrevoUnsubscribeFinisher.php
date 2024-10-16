<?php

declare(strict_types=1);

namespace Remind\Brevo\Domain\Finishers;

use Brevo\Client\ApiException;
use Brevo\Client\Model\RemoveContactFromList;
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

        $contact = new RemoveContactFromList();

        foreach ($formValues as $key => $value) {
            $element = $formDefinition->getElementByIdentifier($key);
            if ($element) {
                $properties = $element->getProperties();
                $brevoAttribute = $properties['brevoAttribute'] ?? null;
                if ($brevoAttribute === 'EMAIL') {
                    $contact->setEmails([$value]);
                    break;
                }
            }
        }

        if (!empty($contact->getEmails())) {
            foreach ($listIds as $listId) {
                try {
                    $this->contactsApi->removeContactFromList($listId, $contact);
                } catch (ApiException $e) {
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
