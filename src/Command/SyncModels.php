<?php

namespace Charcoal\Conductor\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Charcoal\Conductor\Traits\ModelAwareTrait;
use Charcoal\Conductor\Traits\TimerTrait;
use Charcoal\Model\Model;
use Charcoal\Model\ModelInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class SyncModels extends AbstractCommand
{
    use TimerTrait;
    use ModelAwareTrait;

    private $model_classes;

    /**
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('models:db')
            ->setDescription('Synchronize the database with model definitions.')
            ->addArgument('operation', InputArgument::OPTIONAL, 'update or create')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command displays a list of all registered models/objects within your Charcoal project
EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->validateProject()) {
            $output->write('Your project is not a valid Charcoal project');
            exit();
        }

        $model_factory = $this->getProjectApp()->getContainer()->get('model/factory');

        if (!$model_factory) {
            $output->writeln('Failed to get model factory from app container.');
            return 500;
        }

        $models = $this->loadModels($model_factory, $output);
        $modelsCount = count($models);

        if (empty($modelsCount)) {
            $output->writeln('No models were found in the current directory.');
            return 0;
        }

        switch ($input->getArgument('operation')) {
            case 'create':
                $do_create = true;
                break;

            case 'update':
                $do_update = true;
                break;

            default:
                $do_create = $do_update = true;
                break;
        }

        $output->writeln(sprintf(
            '%s %d Model%s',
            ($do_create && $do_update) ? 'Creating/Updating' : ($do_create ? 'Creating' : 'Updating'),
            $modelsCount,
            $modelsCount === 1 ? '' : 's'
        ));

        foreach ($models as $model) {
            $this->timer()->start();

            if (empty($model->metadata()->get('sources'))) {
                continue;
            }

            if ($do_create && !$model->source()->tableExists()) {
                $this->createTable($model, $output);
            } elseif ($do_update) {
                $this->updateTable($model, $output);
            }
        }

        $outputStyle = new OutputFormatterStyle('red', '#ff0', ['bold', 'blink']);
        $output->getFormatter()->setStyle('fire', $outputStyle);
        $output->writeln('<info>All Done!</info>');

        return 0;
    }

    private function createTable(ModelInterface $model, OutputInterface $output)
    {
        $class_name = get_class($model);
        $output->write(sprintf('<fg=green;>Creating</> table for <fg=yellow;options=bold>%s</>', $class_name));
        $model->source()->createTable();
        $output->writeln(sprintf(' - %ss', $this->timer()->stop()));
    }

    private function updateTable(ModelInterface $model, OutputInterface $output)
    {
        $class_name = get_class($model);
        $output->write(sprintf('Updating table for <fg=yellow;options=bold>%s</>', $class_name));
        $model->source()->alterTable();
        $output->writeln(sprintf(' - %ss', $this->timer()->stop()));
    }
}
