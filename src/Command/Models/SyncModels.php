<?php

namespace Charcoal\Conductor\Command\Models;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Charcoal\Conductor\Traits\ModelAwareTrait;
use Charcoal\Conductor\Traits\TimerTrait;
use Charcoal\Model\ModelInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputArgument;
use Charcoal\Source\DatabaseSource;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\Table;
use Charcoal\Conductor\Command\AbstractCommand;
use Charcoal\Model\Model;
use Charcoal\Property\AbstractProperty;

class SyncModels extends AbstractCommand
{
    use TimerTrait;
    use ModelAwareTrait;

    private $model_classes;
    private $isDryRun = false;

    /**
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('models:sync')
            ->setDescription('Synchronize the database with model definitions.')
            ->addArgument('operation', InputArgument::OPTIONAL, 'update or create')
            ->addOption('dry', null, InputOption::VALUE_NONE, 'Dry-run')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command displays a list of all registered models/objects within your Charcoal project
EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
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
        $models = array_filter($models, function ($model) {
            return !empty($model->metadata()->get('sources'));
        });
        $modelsCount = count($models);

        if (empty($models)) {
            $output->writeln('No models were found in the current directory.');
            return 0;
        }

        $this->isDryRun = $input->getOption('dry');
        if ($this->isDryRun) {
            $output->writeln('<fg=red;options=bold>DRY RUN</>');
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
        $output->write(sprintf('<fg=green>Creating table</> for <fg=yellow;options=bold>%s</>', $class_name));

        /** @var DatabaseSource $source */
        $source = $model->source();
        $source->createTable();

        $output->writeln(sprintf(' - %ss', $this->timer()->stop()));
    }

    private function updateTable(ModelInterface $model, OutputInterface $output)
    {
        $class_name = get_class($model);
        $updateMessage = sprintf('<fg=blue>Updating table</> for <fg=yellow;options=bold>%s</>', $class_name);
        $changes = $this->getChanges($model, $output);
        if ($changes instanceof Table) {
            $output->writeln($updateMessage);
            $changes->render();

            if (!$this->isDryRun) {
                /** @var DatabaseSource $source */
                $source = $model->source();
                $source->alterTable();

                $output->writeln(sprintf(
                    '<fg=green>Updated %s - %ss</>',
                    $source->table(),
                    $this->timer()->stop()
                ));
            }
        } else {
            $output->writeln(sprintf('Skipping <fg=yellow;options=bold>%s</>: already up-to-date', $class_name));
        }
    }

    private function getChanges(ModelInterface $model, OutputInterface $output): ?Table
    {
        /** @var DatabaseSource $source */
        $source = $model->source();
        $fields = $this->getModelFields($model);
        $tableStructure = $source->tableStructure();

        $table = (new Table($output))->setHeaders(['Property', 'Aspect', 'Old', 'New']);
        $alterations = 0;

        foreach ($fields as $field) {
            $ident = $field->ident();

            if (!array_key_exists($ident, $tableStructure)) {
                $fieldSql = $field->sql();
                if ($fieldSql) {
                    $table->addRow([$ident, '--', '--', 'CREATE']);
                }
            } else {
                // The key exists. Validate.
                $col   = $tableStructure[$ident];

                if (strtolower($col['Type']) !== strtolower($field->sqlType())) {
                    // types do not match.
                    $sqlType = strtolower($field->sqlType());
                    $sqlType = preg_replace('/(int)(\(\d+\))/', '$1', $sqlType, 1);

                    if ($sqlType !== strtolower($col['Type'])) {
                        $alterations++;
                        $table->addRow([
                            $ident,
                            'type',
                            strtolower($col['Type']),
                            strtolower($field->sqlType())
                        ]);
                    }
                }

                if ((strtolower($col['Null']) !== 'no') !== $field->allowNull() && empty($col['Key'])) {
                    // Allow null.
                    $alterations++;
                    $table->addRow([
                        $ident,
                        'allow_null',
                        (strtolower($col['Null']) !== 'no') ? 'true' : 'false',
                        $field->allowNull() ? 'true' : 'false'
                    ]);
                }

                if ($col['Default'] !== $field->defaultVal()) {
                    // Change default value.
                    $alterations++;
                    $table->addRow([
                        $ident,
                        'default_val',
                        $col['Default'],
                        $field->defaultVal()
                    ]);
                }
            }
        }

        return $alterations ? $table : null;
    }

    private function getModelFields(ModelInterface $model)
    {
        /** @var Model $model */
        $properties = array_keys($model->metadata()->properties());

        $fields = [];
        foreach ($properties as $propertyIdent) {
            /** @var AbstractProperty $prop */
            $prop = $model->property($propertyIdent);
            if (!$prop || !$prop['active'] || !$prop['storable']) {
                continue;
            }

            $val = $model->propertyValue($propertyIdent);
            foreach ($prop->fields($val) as $fieldIdent => $field) {
                $fields[$field->ident()] = $field;
            }
        }

        return $fields;
    }
}
