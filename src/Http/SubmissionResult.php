<?php
namespace Concrete\Package\Indexnow\Http;

class SubmissionResult
{
    public $success;
    public $retryable;
    public $statusCode;
    public $message;
    public $retryAfterSeconds;

    public function __construct($success, $retryable, $statusCode, $message, $retryAfterSeconds = null)
    {
        $this->success = (bool) $success;
        $this->retryable = (bool) $retryable;
        $this->statusCode = $statusCode !== null ? (int) $statusCode : null;
        $this->message = (string) $message;
        $this->retryAfterSeconds = $retryAfterSeconds !== null ? max(0, (int) $retryAfterSeconds) : null;
    }
}
