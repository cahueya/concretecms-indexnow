<?php
namespace Concrete\Package\Indexnow\Command\Task\Runner;

use Concrete\Core\Command\Task\Runner\TaskRunnerInterface;
use Concrete\Core\Command\Task\TaskInterface;

class IndexNowTaskRunner implements TaskRunnerInterface
{
    protected $task;
    protected $mode;
    protected $completionMessage = '';

    public function __construct(TaskInterface $task, $mode)
    {
        $this->task = $task;
        $this->mode = (string) $mode;
    }

    public function getTaskRunnerHandler(): string
    {
        return IndexNowTaskRunnerHandler::class;
    }

    public function getTask()
    {
        return $this->task;
    }

    public function getMode()
    {
        return $this->mode;
    }

    public function setCompletionMessage($message)
    {
        $this->completionMessage = (string) $message;
    }

    public function getCompletionMessage()
    {
        return $this->completionMessage ?: t('IndexNow task completed.');
    }
}
