<?php

declare(strict_types=1);

namespace App\Tests\Dto;

use App\Dto\ContactRequest;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ContactRequestTest extends KernelTestCase
{
    private function validator(): ValidatorInterface
    {
        self::bootKernel();

        return static::getContainer()->get(ValidatorInterface::class);
    }

    private function validRequest(): ContactRequest
    {
        $data = new ContactRequest();
        $data->name = 'Max Mustermann';
        $data->email = 'max@example.com';
        $data->message = 'Ich möchte eine neue Website entwickeln lassen.';

        return $data;
    }

    public function testValidRequestHasNoViolations(): void
    {
        self::assertCount(0, $this->validator()->validate($this->validRequest()));
    }

    public function testCompanyAndPhoneAreOptional(): void
    {
        $data = $this->validRequest();
        $data->company = '';
        $data->phone = '';

        self::assertCount(0, $this->validator()->validate($data));
    }

    public function testEmptyRequestViolatesTheThreeRequiredFields(): void
    {
        // name, email and message are required; company and phone are not.
        self::assertGreaterThanOrEqual(3, $this->validator()->validate(new ContactRequest())->count());
    }

    public function testTooShortMessageIsRejected(): void
    {
        $data = $this->validRequest();
        $data->message = 'kurz';

        self::assertGreaterThanOrEqual(1, $this->validator()->validate($data)->count());
    }
}
