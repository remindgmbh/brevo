<?php

declare(strict_types=1);

namespace Remind\Brevo\Domain\Finishers;

use Brevo\Client\Model\CreateDoiContact;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;

class BrevoRegistrationFinisher extends AbstractBrevoFinisher
{
    protected function executeInternal()
    {
        $listIds = $this->parseOption('listIds');
        $listIds = GeneralUtility::intExplode(',', $listIds, true);
        $templateId = (int) $this->parseOption('templateId');
        $redirectPage = (int) $this->parseOption('redirectPage');
        $formValues = $this->finisherContext->getFormValues();
        $formRuntime = $this->finisherContext->getFormRuntime();
        $formDefinition = $formRuntime->getFormDefinition();

        $createDoiContact = new CreateDoiContact();

        $attributes = [];
        foreach ($formValues as $key => $value) {
            $element = $formDefinition->getElementByIdentifier($key);
            $properties = $element->getProperties();
            $brevoAttribute = $properties['brevoAttribute'] ?? null;
            if ($brevoAttribute) {
                if ($brevoAttribute === 'EMAIL') {
                    $createDoiContact->setEmail($value);
                } else {
                    $type = $element->getType();
                    if ($type === 'Checkbox') {
                        $value = (bool) $value;
                    }
                    $attributes[$brevoAttribute] = $value;
                }
            }
        }

        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $redirectionUrl = $uriBuilder
            ->setRequest($formRuntime->getRequest())
            ->setCreateAbsoluteUri(true)
            ->setTargetPageUid($redirectPage)
            ->build();

        $createDoiContact->setAttributes((object)$attributes);
        $createDoiContact->setIncludeListIds($listIds);
        $createDoiContact->setTemplateId($templateId);
        $createDoiContact->setRedirectionUrl($redirectionUrl);
        $this->contactsApi->createDoiContact($createDoiContact);
    }
}
