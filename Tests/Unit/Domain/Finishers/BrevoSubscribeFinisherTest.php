<?php

declare(strict_types=1);

namespace Remind\Brevo\Tests\Unit\Domain\Finishers;

use Brevo\Contacts\ContactsClient;
use Brevo\Contacts\Requests\CreateDoiContactRequest;
use Brevo\Contacts\Requests\GetContactInfoRequest;
use Brevo\Contacts\Requests\UpdateContactRequest;
use Brevo\Exceptions\BrevoApiException;
use Exception;
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
    protected bool $resetSingletonInstances = true;

    #[Test]
    public function contactExistsReturnsTrueWhenContactInfoIsFound(): void
    {
        $contactsClient = $this->createMock(ContactsClient::class);
        $contactsClient
            ->expects($this->once())
            ->method('getContactInfo')
            ->with(
                'jane@example.com',
                $this->callback(static function (GetContactInfoRequest $request): bool {
                    return $request->identifierType === 'email_id';
                })
            )
            ->willReturn(null);

        $brevoService = $this->createMock(BrevoService::class);
        $brevoService
            ->method('getContactsClient')
            ->willReturn($contactsClient);

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
        $contactsClient = $this->createMock(ContactsClient::class);
        $contactsClient
            ->expects($this->once())
            ->method('getContactInfo')
            ->with(
                'unknown@example.com',
                $this->callback(static function (GetContactInfoRequest $request): bool {
                    return $request->identifierType === 'email_id';
                })
            )
            ->willThrowException(new BrevoApiException('Not found', 404, ['error' => 'not found']));

        $brevoService = $this->createMock(BrevoService::class);
        $brevoService
            ->method('getContactsClient')
            ->willReturn($contactsClient);

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
        $contactsClient = $this->createMock(ContactsClient::class);
        $contactsClient
            ->expects($this->once())
            ->method('getContactInfo')
            ->with(
                'error@example.com',
                $this->callback(static function (GetContactInfoRequest $request): bool {
                    return $request->identifierType === 'email_id';
                })
            )
            ->willThrowException(new BrevoApiException('Server error', 500, ['error' => 'server']));

        $brevoService = $this->createMock(BrevoService::class);
        $brevoService
            ->method('getContactsClient')
            ->willReturn($contactsClient);

        $finisher = new class ($brevoService) extends BrevoSubscribeFinisher {
            public function contactExistsProxy(string $email): bool
            {
                return $this->contactExists($email);
            }
        };

        $this->expectException(BrevoApiException::class);
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

        $contactsClient = new class extends ContactsClient {
            private bool $updateContactCalled = false;

            private ?UpdateContactRequest $receivedRequest = null;

            private ?string $receivedIdentifier = null;

            public function __construct()
            {
            }

            public function wasUpdateContactCalled(): bool
            {
                return $this->updateContactCalled;
            }

            public function getReceivedRequest(): ?UpdateContactRequest
            {
                return $this->receivedRequest;
            }

            public function getReceivedIdentifier(): ?string
            {
                return $this->receivedIdentifier;
            }

            /** @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter */
            public function updateContact(string|int $identifier, UpdateContactRequest $request = new UpdateContactRequest(), ?array $_options = null): void
            {
                $this->updateContactCalled = true;
                $this->receivedIdentifier = (string) $identifier;
                $this->receivedRequest = $request;
            }

            /** @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter */
            public function createDoiContact(CreateDoiContactRequest $_request, ?array $_options = null): void
            {
                throw new Exception('createDoiContact should not be called for existing contacts');
            }
        };

        $brevoService = $this->createMock(BrevoService::class);
        $brevoService
            ->method('getContactsClient')
            ->willReturn($contactsClient);

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
