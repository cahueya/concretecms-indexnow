<?php
namespace Concrete\Package\Indexnow\Command\Task\Controller;

use Concrete\Core\Command\Task\Controller\ControllerInterface;
use Concrete\Core\Controller\AbstractController;
use Concrete\Core\Command\Task\Input\Definition\Definition;
use Concrete\Core\Command\Task\Input\InputInterface;
use Concrete\Core\Command\Task\TaskInterface;
use Concrete\Core\Command\Task\Runner\TaskRunnerInterface;
use Concrete\Package\Indexnow\Command\Task\Runner\IndexNowTaskRunner;

class IndexnowReconcileController extends AbstractController implements ControllerInterface
{
    public function getName(): string { return t('IndexNow: Reconcile Searchable Pages'); }
    public function getDescription(): string { return t('Scans searchable pages and places their current URLs into the IndexNow queue.'); }
    public function getTaskRunner(TaskInterface $task, InputInterface $input): TaskRunnerInterface { return new IndexNowTaskRunner($task, 'reconcile'); }
    public function getConsoleCommandName(): string { return 'indexnow:reconcile'; }
    public function getHelpText(): string { return t('Rebuilds the IndexNow queue from searchable Concrete pages.'); }
    public function getInputDefinition(): ?Definition { return new Definition(); }
}
