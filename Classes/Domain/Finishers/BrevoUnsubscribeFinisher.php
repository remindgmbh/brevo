<?php

declare(strict_types=1);

namespace Remind\Brevo\Domain\Finishers;

use Brevo\Client\Model\RemoveContactFromList;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class BrevoUnsubscribeFinisher extends AbstractBrevoFinisher
{
    protected function executeInternal()
    {
        $listIds = $this->parseOption('listIds');
        $listIds = GeneralUtility::intExplode(',', $listIds, true);
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
                    $contact['emails'] = [$value];
                    break;
                }
            }
        }

        if (!empty($contact['emails'])) {
            foreach ($listIds as $listId) {
                $this->contactsApi->removeContactFromList($listId, $contact);
            }
        }
    }
}
