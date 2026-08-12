<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Carries and validates the data submitted through the contact form.
 * The form is hand-rolled (no symfony/form); this DTO is populated from
 * the request and validated with symfony/validator.
 */
final class ContactRequest
{
    #[Assert\NotBlank(message: 'Bitte geben Sie Ihren Namen an.')]
    #[Assert\Length(max: 120, maxMessage: 'Der Name ist zu lang.')]
    public string $name = '';

    #[Assert\NotBlank(message: 'Bitte geben Sie Ihre E-Mail-Adresse an.')]
    #[Assert\Email(message: 'Bitte geben Sie eine gültige E-Mail-Adresse an.', mode: 'strict')]
    #[Assert\Length(max: 180, maxMessage: 'Die E-Mail-Adresse ist zu lang.')]
    public string $email = '';

    #[Assert\Length(max: 120, maxMessage: 'Der Firmenname ist zu lang.')]
    public string $company = '';

    #[Assert\Length(max: 40, maxMessage: 'Die Telefonnummer ist zu lang.')]
    public string $phone = '';

    #[Assert\NotBlank(message: 'Bitte beschreiben Sie kurz Ihre Idee oder Ihr Vorhaben.')]
    #[Assert\Length(min: 10, max: 3000, minMessage: 'Bitte beschreiben Sie Ihre Idee oder Ihr Vorhaben etwas ausführlicher.', maxMessage: 'Ihre Nachricht ist zu lang.')]
    public string $message = '';
}
