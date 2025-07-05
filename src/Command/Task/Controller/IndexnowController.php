<?php
namespace Concrete\Package\Indexnow\Command\Task\Controller;

use Concrete\Core\Command\Task\Controller\ControllerInterface;
use Concrete\Core\Controller\AbstractController;
use Concrete\Core\Command\Task\Input\InputInterface;
use Concrete\Core\Command\Task\TaskInterface;
use Concrete\Core\Command\Task\Runner\TaskRunnerInterface;
use Concrete\Core\Command\Task\Runner\CommandTaskRunner;
use Concrete\Core\Command\Task\Traits\DashboardTaskRunnerTrait;
use Concrete\Core\Command\Task\Input\Definition\Definition;
use Concrete\Core\Command\Task\Input\Argument;
use Concrete\Core\Command\Task\Input\Option;
use Concrete\Package\Indexnow\Index\Command\IndexnowCommmand;

class IndexnowController extends AbstractController implements ControllerInterface
{
    //use Concrete\Core\Command\Task\Traits\DashboardTaskRunnerTrait;

    public function getName(): string
    {
        return t('IndexNow: Bulk Submit All URLs');
    }

    public function getDescription():string
    {
        return t('Sends all public/searchable URLs to IndexNow in bulk.');
    }

    public function getTaskRunner(TaskInterface $task, InputInterface $input): TaskRunnerInterface
    {
        $app = \Concrete\Core\Support\Facade\Facade::getFacadeApplication();

        $command = new \Concrete\Package\Indexnow\Index\Command\IndexnowCommand();
        $command->setApplication($app);
        
        $result = $command->run();
        
        return new CommandTaskRunner($task, $command, $result ?: t('Index submitted successfully.'));
    }

    // Implement the missing methods from the ControllerInterface

    /**
     * Returns the console command name (e.g., for CLI tasks)
     *
     * @return string
     */
    public function getConsoleCommandName(): string
    {
        return 'indexnow:submit'; // Example command name for console usage
    }

    /**
     * Provides help text for the console command
     *
     * @return string
     */
    public function getHelpText(): string
    {
        return t('This command submits the index to indexnow API.');
    }



    /**
     * Returns the input definition for the console command
     *
     * @return \Concrete\Core\Command\Task\Input\Definition\Definition|null
     */
    public function getInputDefinition(): ?Definition
    {
        // Create a new InputDefinition object
        $definition = new Definition();
        return $definition;
    }

    public function executeTask(TaskInterface $task, $input)
    {
        $taskRunner = $this->getTaskRunner($task, $input);  // Get the task runner (CommandTaskRunner)
        
        return $this->executeTask($task, $input);  // Pass the TaskInterface ($task) to executeTask() from the trait
    }
}
