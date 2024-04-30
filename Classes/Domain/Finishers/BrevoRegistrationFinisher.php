<?php

declare(strict_types=1);

namespace Remind\Brevo\Domain\Finishers;

use Brevo\Client\Api\ContactsApi;
use Brevo\Client\Configuration;
use Brevo\Client\Model\CreateDoiContact;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Form\Domain\Finishers\AbstractFinisher;

class BrevoRegistrationFinisher extends AbstractFinisher
{
/**
  * This method is called in the concrete finisher whenever self::execute() is called.
  *
  * Override and fill with your own implementation!
  * @return null|string
  */
    protected function executeInternal()
    {
        $apiKey = getenv('BREVO_API_KEY');
        $listIds = $this->parseOption('listIds');
        $listIds = GeneralUtility::intExplode(',', $listIds, true);
        $templateId = (int) $this->parseOption('templateId');
        $redirectPage = (int) $this->parseOption('redirectPage');
        $formValues = $this->finisherContext->getFormValues();
        $formRuntime = $this->finisherContext->getFormRuntime();
        $formDefinition = $formRuntime->getFormDefinition();

        $config = Configuration::getDefaultConfiguration()->setApiKey('api-key', $apiKey);
        $client = new Client([
            RequestOptions::CONNECT_TIMEOUT => 5,
            RequestOptions::TIMEOUT => 5,
        ]);
        $apiInstance = new ContactsApi($client, $config);
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
        $apiInstance->createDoiContact($createDoiContact);
    }
}
