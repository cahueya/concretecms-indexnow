<?php
namespace Concrete\Package\Indexnow\Command\Task\Controller;

use Concrete\Core\Command\Task\Controller\ControllerInterface;
use Concrete\Core\Controller\AbstractController;
use Concrete\Core\Command\Task\Input\Definition\Definition;
use Concrete\Core\Command\Task\Input\InputInterface;
use Concrete\Core\Command\Task\TaskInterface;
use Concrete\Core\Command\Task\Runner\TaskRunnerInterface;
use Concrete\Package\Indexnow\Command\Task\Runner\IndexNowTaskRunner;

class IndexnowController extends AbstractController implements ControllerInterface
{
    public function getName(): string { return t('IndexNow: Process Queue'); }
    public function getDescription(): string { return t('Submits ready, deduplicated IndexNow URLs in host-specific batches.'); }
    public function getTaskRunner(TaskInterface $task, InputInterface $input): TaskRunnerInterface { return new IndexNowTaskRunner($task, 'process'); }
    public function getConsoleCommandName(): string { return 'indexnow:submit'; }
    public function getHelpText(): string { return t('Processes URLs currently ready in the IndexNow queue.'); }
    public function getInputDefinition(): ?Definition { return new Definition(); }
}
