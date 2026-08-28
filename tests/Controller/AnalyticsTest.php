<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The Matomo tracker is gated on the prod environment. These tests run in
 * test, so they pin the negative case – a regression that drops the gate
 * would start tracking local and CI traffic silently.
 */
final class AnalyticsTest extends WebTestCase
{
    public function testTrackerIsAbsentOutsideProduction(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('_paq', (string) $client->getResponse()->getContent());
    }

    public function testTrackerIsAbsentOnTheLegalPagesToo(): void
    {
        $client = static::createClient();

        foreach (['/impressum', '/datenschutz'] as $path) {
            $client->request('GET', $path);

            self::assertResponseIsSuccessful();
            self::assertStringNotContainsString('_paq', (string) $client->getResponse()->getContent());
        }
    }
}
