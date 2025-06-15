<?php

namespace App\Message;

class SendVerificationEmailMessage
{
    private string $verificationToken;
    private string $email;
    private string $expiration;
    // private array $formData;

    public function __construct(string $verificationToken, string $email, string $expiration)
    {
        $this->verificationToken = $verificationToken;
        $this->email = $email;
        $this->expiration = $expiration;
    }

    public function getVerificationToken(): string
    {
        return $this->verificationToken;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getExpiration(): string
    {
        return $this->expiration;
    }
}