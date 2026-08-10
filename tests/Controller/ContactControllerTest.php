<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ContactControllerTest extends WebTestCase
{
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
