<?php
namespace Concrete\Package\Indexnow\Command\Task\Runner;

use Concrete\Core\Command\Task\Runner\Context\ContextInterface;
use Concrete\Core\Command\Task\Runner\HandlerInterface;
use Concrete\Core\Command\Task\Runner\Response\ResponseInterface;
use Concrete\Core\Command\Task\Runner\Response\TaskCompletedResponse;
use Concrete\Core\Command\Task\Runner\TaskRunnerInterface;
use Concrete\Core\Command\Task\TaskService;
use Concrete\Core\Support\Facade\Facade;
use Concrete\Package\Indexnow\Service\QueueProcessor;
use Concrete\Package\Indexnow\Service\Reconciler;

class IndexNowTaskRunnerHandler implements HandlerInterface
{
    public function boot(TaskRunnerInterface $runner)
    {
        Facade::getFacadeApplication()->make(TaskService::class)->start($runner->getTask());
    }

    public function start(TaskRunnerInterface $runner, ContextInterface $context)
    {
        // Intentionally synchronous: one bounded queue/reconciliation pass per task invocation.
    }

    public function run(TaskRunnerInterface $runner, ContextInterface $context)
    {
        if (!$runner instanceof IndexNowTaskRunner) return;
        if ($runner->getMode() === 'reconcile') {
            $result = (new Reconciler())->reconcile();
        } else {
            $result = (new QueueProcessor())->process();
        }
        $runner->setCompletionMessage($result['message']);
    }

    public function complete(TaskRunnerInterface $runner, ContextInterface $context): ResponseInterface
    {
        $message = $runner instanceof IndexNowTaskRunner ? $runner->getCompletionMessage() : t('IndexNow task completed.');
        $context->getOutput()->write($message);
        Facade::getFacadeApplication()->make(TaskService::class)->complete($runner->getTask());
        return new TaskCompletedResponse($message);
    }
}
