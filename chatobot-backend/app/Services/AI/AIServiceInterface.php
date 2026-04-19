<?php

namespace App\Services\AI;

interface AIServiceInterface
{
    public function send($messagesContext): string;
    public function stream($messagesContext): \Generator;
}
