<?php 

namespace App\Message;

abstract class AbstractDelayMessage
{
    private string $messageCreationDate;
    private int $delayTime;

    public function __construct(string $messageCreationDate, int $delayTime)
    {
        $this->messageCreationDate = $messageCreationDate;// new \DateTime()->format('Y-m-d H:i:s');
        $this->delayTime = $delayTime;
    }

    public function getMessageCreationDate(): string
    {
        return $this->messageCreationDate;
    }

    public function getDelayTime(): int
    {
        return $this->delayTime;
    }
}
