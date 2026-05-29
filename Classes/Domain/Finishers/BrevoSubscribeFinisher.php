<?php

declare(strict_types=1);

namespace Remind\Brevo\Domain\Finishers;

use Brevo\Client\ApiException;
use Brevo\Client\Model\CreateDoiContact;
use Brevo\Client\Model\UpdateContact;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;

class BrevoSubscribeFinisher extends AbstractBrevoFinisher
{
    protected function executeInternal(): ?string
    {
        $listIds = $this->parseOption('listIds');
        if (!is_array($listIds)) {
            $listIds = GeneralUtility::intExplode(',', (string) $listIds, true);
        }
        $templateId = (int) $this->parseOption('templateId');
        $redirectPage = (int) $this->parseOption('redirectPage');
        $updateExistingContact = (bool) $this->parseOption('updateExistingContact');
        $formValues = $this->finisherContext->getFormValues();
        $formRuntime = $this->finisherContext->getFormRuntime();
        $formDefinition = $formRuntime->getFormDefinition();

        $createDoiContact = new CreateDoiContact();

        $attributes = [];
        foreach ($formValues as $key => $value) {
            $element = $formDefinition->getElementByIdentifier($key);
            $properties = $element?->getProperties();
            $brevoAttribute = $properties['brevoAttribute'] ?? null;
            if ($brevoAttribute) {
                if ($brevoAttribute === 'EMAIL') {
                    $createDoiContact->setEmail($value);
                } else {
                    $type = $element?->getType();
                    if ($type === 'Checkbox') {
                        $value = (bool) $value;
                    }
                    $attributes[$brevoAttribute] = $value;
                }
            }
        }

        $email = $createDoiContact->getEmail();

        if (
            $updateExistingContact
            && $this->contactExists($email)
        ) {
            $updateContact = new UpdateContact();
            $updateContact->setAttributes((object) $attributes);
            $updateContact->setListIds($listIds);
            /** @phpstan-ignore-next-line argument.type */
            $this->contactsApi->updateContact($updateContact, $email, 'email_id');

            return null;
        }

        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $redirectionUrl = $uriBuilder
            ->setRequest($formRuntime->getRequest())
            ->setCreateAbsoluteUri(true)
            ->setTargetPageUid($redirectPage)
            ->build();

        $createDoiContact->setAttributes((object) $attributes);
        $createDoiContact->setIncludeListIds($listIds);
        $createDoiContact->setTemplateId($templateId);
        $createDoiContact->setRedirectionUrl($redirectionUrl);
        $this->contactsApi->createDoiContact($createDoiContact);

        return null;
    }

    protected function contactExists(string $email): bool
    {
        try {
            /** @phpstan-ignore-next-line argument.type */
            $this->contactsApi->getContactInfo($email, 'email_id');
            return true;
        } catch (ApiException $e) {
            if ($e->getCode() === 404) {
                return false;
            }
            throw $e;
        }
    }
}
