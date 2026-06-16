<?php

declare(strict_types=1);

namespace Remind\Brevo\Tests\Unit\Domain\Finishers;

use Brevo\Client\Api\ContactsApi;
use Brevo\Client\ApiException;
use Brevo\Client\Model\UpdateContact;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Remind\Brevo\Domain\Finishers\BrevoSubscribeFinisher;
use Remind\Brevo\Service\BrevoService;
use TYPO3\CMS\Form\Domain\Finishers\FinisherContext;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;
use TYPO3\CMS\Form\Domain\Model\FormElements\FormElementInterface;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(BrevoSubscribeFinisher::class)]
class BrevoSubscribeFinisherTest extends UnitTestCase
{
    #[Test]
    public function contactExistsReturnsTrueWhenContactInfoIsFound(): void
    {
        $contactsApi = $this->createMock(ContactsApi::class);
        $contactsApi
            ->expects($this->once())
            ->method('getContactInfo')
            ->with('jane@example.com', 'email_id');

        $brevoService = $this->createMock(BrevoService::class);
        $brevoService
            ->method('getContactsApi')
            ->willReturn($contactsApi);

        $finisher = new class ($brevoService) extends BrevoSubscribeFinisher {
            public function contactExistsProxy(string $email): bool
            {
                return $this->contactExists($email);
            }
        };

        self::assertTrue($finisher->contactExistsProxy('jane@example.com'));
    }

    #[Test]
    public function contactExistsReturnsFalseOn404ApiException(): void
    {
        $contactsApi = $this->createMock(ContactsApi::class);
        $contactsApi
            ->expects($this->once())
            ->method('getContactInfo')
            ->with('unknown@example.com', 'email_id')
            ->willThrowException(new ApiException('Not found', 404));

        $brevoService = $this->createMock(BrevoService::class);
        $brevoService
            ->method('getContactsApi')
            ->willReturn($contactsApi);

        $finisher = new class ($brevoService) extends BrevoSubscribeFinisher {
            public function contactExistsProxy(string $email): bool
            {
                return $this->contactExists($email);
            }
        };

        self::assertFalse($finisher->contactExistsProxy('unknown@example.com'));
    }

    #[Test]
    public function contactExistsRethrowsNon404ApiException(): void
    {
        $contactsApi = $this->createMock(ContactsApi::class);
        $contactsApi
            ->expects($this->once())
            ->method('getContactInfo')
            ->with('error@example.com', 'email_id')
            ->willThrowException(new ApiException('Server error', 500));

        $brevoService = $this->createMock(BrevoService::class);
        $brevoService
            ->method('getContactsApi')
            ->willReturn($contactsApi);

        $finisher = new class ($brevoService) extends BrevoSubscribeFinisher {
            public function contactExistsProxy(string $email): bool
            {
                return $this->contactExists($email);
            }
        };

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(500);

        $finisher->contactExistsProxy('error@example.com');
    }

    #[Test]
    public function executeInternalUpdatesExistingContactAndSkipsDoiCreation(): void
    {
        $emailElement = $this->createMock(FormElementInterface::class);
        $emailElement
            ->method('getProperties')
            ->willReturn(['brevoAttribute' => 'EMAIL']);

        $optInElement = $this->createMock(FormElementInterface::class);
        $optInElement
            ->method('getProperties')
            ->willReturn(['brevoAttribute' => 'OPT_IN']);
        $optInElement
            ->method('getType')
            ->willReturn('Checkbox');

        $formDefinition = $this->createMock(FormDefinition::class);
        $formDefinition
            ->method('getElementByIdentifier')
            ->willReturnCallback(static function (
                string $identifier
            ) use (
                $emailElement,
                $optInElement,
            ): ?FormElementInterface {
                return match ($identifier) {
                    'email' => $emailElement,
                    'optIn' => $optInElement,
                    default => null,
                };
            });

        $formRuntime = $this->createMock(FormRuntime::class);
        $formRuntime
            ->method('getFormDefinition')
            ->willReturn($formDefinition);

        $finisherContext = $this->createMock(FinisherContext::class);
        $finisherContext
            ->method('getFormValues')
            ->willReturn([
                'email' => 'jane@example.com',
                'optIn' => '1',
            ]);
        $finisherContext
            ->method('getFormRuntime')
            ->willReturn($formRuntime);

        $contactsApi = $this->createMock(ContactsApi::class);
        $contactsApi
            ->expects($this->once())
            ->method('updateContact')
            ->with(
                $this->callback(static function (UpdateContact $updateContact): bool {
                    $attributes = (array) $updateContact->getAttributes();
                    return $attributes['OPT_IN'] === true
                        && $updateContact->getListIds() === [3, 5];
                }),
                'jane@example.com',
                'email_id'
            );
        $contactsApi
            ->expects($this->never())
            ->method('createDoiContact');

        $brevoService = $this->createMock(BrevoService::class);
        $brevoService
            ->method('getContactsApi')
            ->willReturn($contactsApi);

        $finisher = new class ($brevoService) extends BrevoSubscribeFinisher {
            /** @var array<string, mixed> */
            private array $optionMap = [];

            public function executeInternalProxy(): ?string
            {
                return $this->executeInternal();
            }

            public function setFinisherContextForTest(FinisherContext $finisherContext): void
            {
                $this->finisherContext = $finisherContext;
            }

            /**
             * @param array<string, mixed> $options
             */
            public function setOptionsForTest(array $options): void
            {
                $this->optionMap = $options;
            }

            /**
             * @return array<mixed>|bool|float|int|string|null
             */
            protected function parseOption(string $optionName): mixed
            {
                return $this->optionMap[$optionName] ?? null;
            }

            protected function contactExists(string $email): bool
            {
                return $email === 'jane@example.com';
            }
        };

        $finisher->setFinisherContextForTest($finisherContext);
        $finisher->setOptionsForTest([
            'listIds' => '3,5',
            'redirectPage' => 123,
            'templateId' => 99,
            'updateExistingContact' => true,
        ]);

        self::assertNull($finisher->executeInternalProxy());
    }
}
