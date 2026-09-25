<?php

declare(strict_types=1);

namespace Remind\Brevo\Domain\Finishers;

use Brevo\Contacts\Requests\AddContactToListRequest;
use Brevo\Contacts\Requests\CreateDoiContactRequest;
use Brevo\Contacts\Requests\GetContactInfoRequest;
use Brevo\Contacts\Requests\UpdateContactRequest;
use Brevo\Contacts\Types\AddContactToListRequestBodyEmails;
use Brevo\Exceptions\BrevoApiException;
use Throwable;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;

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
        $errorPage = (int) ($this->parseOption('errorPage') ?? 0);
        $updateExistingContact = (bool) $this->parseOption('updateExistingContact');
        $formValues = $this->finisherContext->getFormValues();
        $formRuntime = $this->finisherContext->getFormRuntime();
        $formDefinition = $formRuntime->getFormDefinition();

        $doiContact = [];

        $attributes = [];
        foreach ($formValues as $key => $value) {
            $element = $formDefinition->getElementByIdentifier($key);
            $properties = $element?->getProperties();
            $brevoAttribute = $properties['brevoAttribute'] ?? null;
            if ($brevoAttribute) {
                if ($brevoAttribute === 'EMAIL') {
                    $doiContact['email'] = $value;
                } else {
                    $type = $element?->getType();
                    if ($type === 'Checkbox') {
                        $value = (bool) $value;
                    }
                    $attributes[$brevoAttribute] = $value;
                }
            }
        }

        $email = $doiContact['email'] ?? '';

        try {
            if ($this->contactExists($email)) {
                if ($updateExistingContact) {
                    /** @phpstan-ignore-next-line argument.type */
                    $this->contactsClient->updateContact($email, new UpdateContactRequest([
                            'attributes' => (object) $attributes,
                            'identifierType' => 'email_id',
                            'listIds' => $listIds,
                        ]));
                    return null;
                }

                if (!$this->contactIsSubscribed($email, $listIds)) {
                    /** @phpstan-ignore-next-line argument.type */
                    $this->subscribeContact($email, $listIds);
                    return null;
                }

                if ($errorPage !== 0) {
                    return $this->buildRedirectResponse($errorPage);
                }

                return null;
            }
            /** @phpstan-ignore-next-line argument.type */
            $this->createDoiContact($formRuntime, $doiContact, $redirectPage, $attributes, $listIds, $templateId);

            return null;
        } catch (Throwable) {
            if ($errorPage !== 0) {
                return $this->buildRedirectResponse($errorPage);
            }

            return null;
        }
    }

    protected function createDoiContact(FormRuntime $formRuntime, array $contactData, int $redirectPage, array $attributes, array $listIds, int $templateId): void
    {
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $redirectionUrl = $uriBuilder
            ->setRequest($formRuntime->getRequest())
            ->setCreateAbsoluteUri(true)
            ->setTargetPageUid($redirectPage)
            ->build();

        $contactData['attributes'] = (object) $attributes;
        $contactData['listIds'] = $listIds;
        $contactData['templateId'] = $templateId;
        $contactData['redirectionUrl'] = $redirectionUrl;
        $this->contactsClient->createDoiContact(new CreateDoiContactRequest($contactData));
    }

    protected function subscribeContact(string $email, array $listIds): void
    {
        foreach ($listIds as $listId) {
            $this->contactsClient->addContactToList(
                $listId,
                new AddContactToListRequest([
                    'body' => new AddContactToListRequestBodyEmails([
                        'emails' => [
                            $email,
                        ],
                    ]),
                ])
            );
        }
    }

    protected function contactExists(string $email): bool
    {
        try {
            /** @phpstan-ignore-next-line argument.type */
            $this->contactsClient->getContactInfo($email, new GetContactInfoRequest(['identifierType' => 'email_id']));
            return true;
        } catch (BrevoApiException $e) {
            if ($e->getCode() === 404) {
                return false;
            }
            throw $e;
        }
    }

    protected function contactIsSubscribed(string $email, array $listIds): bool
    {
        /** @phpstan-ignore-next-line argument.type */
        $contact = $this->contactsClient->getContactInfo($email, new GetContactInfoRequest(['identifierType' => 'email_id']));
        foreach ($listIds as $listId) {
            if (in_array($listId, $contact->listIds, true)) {
                return true;
            }
        }
        return false;
    }

    protected function buildRedirectResponse(int $targetPage): string
    {
        $this->finisherContext->cancel();
        $redirectUrl = $this->getTypoScriptFrontendController()->cObj->createUrl([
            'parameter' => $targetPage,
        ]);
        return json_encode(
            [
                'redirectUrl' => $redirectUrl,
                'statusCode' => 303,
            ],
            JSON_THROW_ON_ERROR
        );
    }
}
