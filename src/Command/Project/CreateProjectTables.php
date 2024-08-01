<?php

namespace Charcoal\Conductor\Command\Project;

use Charcoal\Conductor\Command\Models\AbstractModelCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Charcoal\Model\ModelInterface;
use Charcoal\Source\DatabaseSource;
use Charcoal\Conductor\Traits\TimerTrait;
use Symfony\Component\Console\Input\InputOption;

class CreateProjectTables extends AbstractModelCommand
{
    use TimerTrait;

    private $isDryRun = false;

    /**
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('project:db')
            ->setDescription('Create required tables for charcoal projects.')
            ->addOption('dry', null, InputOption::VALUE_NONE, 'Dry-run')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command creates a new Charcoal project
EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->validateProject()) {
            $output->write('Your project is not a valid Charcoal project');
            return self::$FAILURE;
        }

        ob_start();
        $modelFactory = $this->getProjectApp()->getContainer()->get('model/factory');
        ob_end_clean();

        if (!$modelFactory) {
            $this->writeError('Failed to get model factory from app container', $output);
            return self::$FAILURE;
        }

        $models = $this->getModels($output);
        if (empty($models)) {
            $this->writeError('No models were found in the current directory.', $output);
            return self::$FAILURE;
        }

        $this->isDryRun = $input->getOption('dry');
        if ($this->isDryRun) {
            $output->writeln('<fg=yellow;bg=red;options=bold> - DRY RUN - </>');
        }

        $do_create = true;
        $createCount = 0;

        foreach ($models as $model) {
            $this->timer()->start();

            try {
                if ($do_create && !$model->source()->tableExists()) {
                    $this->createTable($model, $output);
                    $createCount++;
                }
            } catch (\Throwable $th) {
                $this->timer()->stop();
                $this->writeError($th->getMessage(), $output);
                $output->writeln(sprintf(
                    '<fg=red>Failed to synchronize model: %s</>',
                    $model::objType()
                ));
            }
        }

        return self::$SUCCESS;
    }

    private function createTable(ModelInterface $model, OutputInterface $output)
    {
        $class_name = get_class($model);
        $output->writeln(sprintf('<fg=green>Creating table</> for <fg=yellow;options=bold>%s</>', $class_name));

        if (!$this->isDryRun) {
            /** @var DatabaseSource $source */
            $source = $model->source();
            $source->createTable();

            $output->writeln(sprintf(
                '<fg=green>Created table: %s - %ss</>',
                $source->table(),
                $this->timer()->stop(),
            ));
        }
    }

    private function getModels(OutputInterface $output)
    {
        ob_start();
        $modelFactory = $this->getProjectApp()->getContainer()->get('model/factory');
        ob_end_clean();

        if (!$modelFactory) {
            return [];
        }

        $models = $this->loadAdminModels($modelFactory, $output);
        $models = array_filter($models, function ($model) {
            return !empty($model->metadata()->get('sources'));
        });

        return $models;
    }
}
