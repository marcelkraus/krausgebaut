<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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
