<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Reservation;

final class ContactControllerTest extends WebTestCase
{
    use MailerAssertionsTrait;

    public function testHomepageRendersTheForm(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action="/kontakt"]');
    }

    public function testValidSubmissionRedirects(): void
    {
        $client = static::createClient();
        $payload = $this->payload($client, [
            'name' => 'Max Mustermann',
            'email' => 'max@example.com',
            'message' => 'Ich möchte eine neue Website entwickeln lassen.',
        ]);

        $client->request('POST', '/kontakt', $payload);

        self::assertResponseRedirects();
    }

    public function testInvalidSubmissionReRendersWithErrors(): void
    {
        $client = static::createClient();
        $payload = $this->payload($client, [
            'name' => '',
            'email' => 'not-an-email',
            'message' => 'x',
        ]);

        $client->request('POST', '/kontakt', $payload);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Bitte geben Sie Ihren Namen an.');
    }

    public function testExpiredTimestampReRendersInsteadOfFakingSuccess(): void
    {
        $client = static::createClient();
        $payload = $this->payload($client, [
            'name' => 'Max Mustermann',
            'email' => 'max@example.com',
            'message' => 'Ich möchte eine neue Website entwickeln lassen.',
        ], age: 8000);

        $client->request('POST', '/kontakt', $payload);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'zu lange geöffnet');
    }

    public function testFilledHoneypotIsDroppedWithoutSendingAnything(): void
    {
        $client = static::createClient();
        $payload = $this->payload($client, [
            'name' => 'Max Mustermann',
            'email' => 'max@example.com',
            'message' => 'Ich hätte gern ein Angebot für eine neue Website.',
            'website' => 'https://example.com',
        ]);

        $client->request('POST', '/kontakt', $payload);

        // A bot must learn nothing, so the answer is a fake success.
        self::assertResponseRedirects();
        self::assertEmailCount(0);
    }

    public function testSubmissionUnderThreeSecondsIsDroppedWithoutSendingAnything(): void
    {
        $client = static::createClient();
        $payload = $this->payload($client, [
            'name' => 'Max Mustermann',
            'email' => 'max@example.com',
            'message' => 'Ich hätte gern ein Angebot für eine neue Website.',
        ], age: 0);

        $client->request('POST', '/kontakt', $payload);

        self::assertResponseRedirects();
        self::assertEmailCount(0);
    }

    public function testTamperedTimestampSignatureIsDroppedWithoutSendingAnything(): void
    {
        $client = static::createClient();
        $payload = $this->payload($client, [
            'name' => 'Max Mustermann',
            'email' => 'max@example.com',
            'message' => 'Ich hätte gern ein Angebot für eine neue Website.',
        ]);
        $payload['ts_sig'] = 'gefälscht';

        $client->request('POST', '/kontakt', $payload);

        self::assertResponseRedirects();
        self::assertEmailCount(0);
    }

    public function testAValidSubmissionSendsExactlyOneMessageToTheConfiguredRecipient(): void
    {
        $client = static::createClient();
        $payload = $this->payload($client, [
            'name' => 'Max Mustermann',
            'email' => 'max@example.com',
            'message' => 'Ich hätte gern ein Angebot für eine neue Website.',
        ]);

        $client->request('POST', '/kontakt', $payload);

        self::assertEmailCount(1);
        $nachricht = self::getMailerMessage();
        self::assertNotNull($nachricht);
        self::assertStringContainsString('Nachricht von Max Mustermann', (string) $nachricht->getSubject());
        // Replying must reach the sender, not the site's own address.
        self::assertSame('max@example.com', $nachricht->getReplyTo()[0]->getAddress());
    }

    public function testSuccessShowsTheConfirmationInsteadOfTheForm(): void
    {
        $client = static::createClient();
        $payload = $this->payload($client, [
            'name' => 'Max Mustermann',
            'email' => 'max@example.com',
            'message' => 'Ich hätte gern ein Angebot für eine neue Website.',
        ]);

        $client->request('POST', '/kontakt', $payload);
        $client->followRedirect();

        self::assertSelectorNotExists('form[action="/kontakt"]');
    }

    public function testTamperedCsrfTokenIsRejected(): void
    {
        $client = static::createClient();
        $payload = $this->payload($client, [
            'name' => 'Max Mustermann',
            'email' => 'max@example.com',
            'message' => 'Ich hätte gern ein Angebot für eine neue Website.',
        ]);
        $payload['_token'] = 'gefälscht';

        $client->request('POST', '/kontakt', $payload);

        self::assertResponseStatusCodeSame(422);
        self::assertEmailCount(0);
    }

    public function testTransportFailureIsAnsweredAsAFormErrorRatherThanAnErrorPage(): void
    {
        $client = static::createClient();

        // Both lines have to come before the first request. The kernel is
        // rebooted between requests by default, which would drop the
        // replacement; and once the container has handed the mailer out, it
        // refuses to replace it.
        $client->disableReboot();
        static::getContainer()->set(MailerInterface::class, new class implements MailerInterface {
            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                throw new TransportException('Der Test erzwingt einen Transportfehler.');
            }
        });

        $payload = $this->payload($client, [
            'name' => 'Max Mustermann',
            'email' => 'max@example.com',
            'message' => 'Ich hätte gern ein Angebot für eine neue Website.',
        ]);

        $client->request('POST', '/kontakt', $payload);

        // Apache replaces the Symfony error page with its own, so an uncaught
        // transport exception costs the enquiry and shows a bare 500 on the
        // one form this site exists for.
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'konnte gerade nicht zugestellt werden');
    }

    public function testAThrottledSubmissionIsRefusedAndSaysSo(): void
    {
        $client = static::createClient();

        // The limiter is replaced rather than exhausted: its counter lives in
        // the cache and outlives a single run, so a test that actually sent
        // six messages would poison every case that follows.
        $client->disableReboot();
        static::getContainer()->set('limiter.contact_form', new class implements RateLimiterFactoryInterface {
            public function create(?string $key = null): LimiterInterface
            {
                return new class implements LimiterInterface {
                    public function consume(int $tokens = 1): RateLimit
                    {
                        return new RateLimit(0, new \DateTimeImmutable('+1 hour'), false, 5);
                    }

                    public function reserve(int $tokens = 1, ?float $maxTime = null): Reservation
                    {
                        throw new \LogicException('Nicht Teil dieses Tests.');
                    }

                    public function reset(): void
                    {
                        // Intentionally left blank.
                    }
                };
            }
        });

        $payload = $this->payload($client, [
            'name' => 'Max Mustermann',
            'email' => 'max@example.com',
            'message' => 'Ich hätte gern ein Angebot für eine neue Website.',
        ]);

        $client->request('POST', '/kontakt', $payload);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'zu viele Nachrichten');
        self::assertEmailCount(0);
    }

    /**
     * Builds a POST payload with a valid CSRF token (from a rendered form) and
     * a signed timestamp aged `age` seconds — within the valid window by
     * default, or expired when a large age is passed.
     *
     * @param array<string, string> $fields
     *
     * @return array<string, string>
     */
    private function payload(KernelBrowser $client, array $fields, int $age = 5): array
    {
        $crawler = $client->request('GET', '/');
        $token = $crawler->filter('input[name="_token"]')->attr('value');
        $secret = static::getContainer()->getParameter('kernel.secret');

        $timestamp = (string) (time() - $age);

        return array_merge($fields, [
            '_token' => $token,
            'ts' => $timestamp,
            'ts_sig' => hash_hmac('sha256', $timestamp, $secret),
        ]);
    }
}
