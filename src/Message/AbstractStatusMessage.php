<?php 

namespace App\Message;

abstract class AbstractStatusMessage
{
    private string $messageCreationDate;
    private int $delayTime;
    private string $organizerEmail;

    public function __construct(string $messageCreationDate, int $delayTime, string $organizerEmail)
    {
        $this->messageCreationDate = $messageCreationDate;// new \DateTime()->format('Y-m-d H:i:s');
        $this->delayTime = $delayTime;
        $this->organizerEmail = $organizerEmail;
    }

    public function getMessageCreationDate(): string
    {
        return $this->messageCreationDate;
    }

    public function getDelayTime(): int
    {
        return $this->delayTime;
    }

    public function getOrganizerEmail(): string
    {
        return $this->organizerEmail;
    }
}
