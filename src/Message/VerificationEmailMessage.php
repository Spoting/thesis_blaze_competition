<?php

namespace App\Message;

final class VerificationEmailMessage
{
    private string $verificationToken;
    private string $recipientEmail;
    private string $expiration;
    private string $identifier;
    // private array $formData;

    public function __construct(string $identifier, string $verificationToken, string $recipientEmail, string $expiration)
    {
        $this->identifier = $identifier;
        $this->verificationToken = $verificationToken;
        $this->recipientEmail = $recipientEmail;
        $this->expiration = $expiration;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getVerificationToken(): string
    {
        return $this->verificationToken;
    }

    public function getRecipientEmail(): string
    {
        return $this->recipientEmail;
    }

    public function getExpiration(): string
    {
        return $this->expiration;
    }
}