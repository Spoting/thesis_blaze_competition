<?php

namespace App\Message;

class EmailNotificationMessage
{
    private string $competitionId;
    private string $recipientEmail;
    private string $subject;
    private string $templateId;
    private array $templateContext;

    public function __construct(
        string $competitionId,
        string $recipientEmail,
        string $subject,
        string $templateId = 'notification_email',
        array $templateContext = [],
    ) {
        $this->competitionId = $competitionId;
        $this->recipientEmail = $recipientEmail;
        $this->subject = $subject;
        $this->templateId = $templateId;
        $this->templateContext = $templateContext;
    }

    public function getCompetitionId(): string
    {
        return $this->competitionId;
    }

    public function getRecipientEmail(): string
    {
        return $this->recipientEmail;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getTemplateId(): string
    {
        return $this->templateId;
    }

    public function getTemplateContext(): array
    {
        return $this->templateContext;
    }
}