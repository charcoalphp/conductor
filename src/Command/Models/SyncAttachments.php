<?php

namespace Charcoal\Conductor\Command\Models;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Charcoal\Conductor\Traits\TimerTrait;
use Charcoal\Model\ModelInterface;
use Charcoal\Source\DatabaseSource;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\Table;
use Charcoal\Property\AbstractProperty;
use Stecman\Component\Symfony\Console\BashCompletion\Completion\CompletionAwareInterface;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Output\ConsoleOutput;
use Charcoal\Attachment\Object\Attachment;

class SyncAttachments extends AbstractModelCommand implements CompletionAwareInterface
{
    use TimerTrait;

    private $isDryRun = false;

    /**
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('attachments:sync')
            ->setDescription('Synchronize the database with attachment model definitions.')
            ->addOption('create-only', null, InputOption::VALUE_NONE, 'Create only')
            ->addOption('dry', null, InputOption::VALUE_NONE, 'Dry-run')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command synchronizes your attachments table structure with attachments defined within your Charcoal project
EOF
            );
    }

    public function completeArgumentValues($argumentName, CompletionContext $context)
    {
        set_error_handler(function ($errno, $errstr) {
            return true;
        });

        if (!$this->validateProject()) {
            return [];
        }

        ob_start();
        $attachments = array_map(function ($attachment) {
            /** @var Attachment $attachment */
            return $attachment->objType();
        }, $this->getAttachments(new ConsoleOutput()));
        ob_end_clean();

        $word    = $context->getCurrentWord();
        $suggestions = [];

        if (empty($word)) {
            return $attachments;
        }

        foreach ($attachments as $attachment) {
            if (strpos($attachment, $word) !== false) {
                $suggestions[] = $attachment;
            }
        }

        return $suggestions;
    }

    public function completeOptionValues($optionName, CompletionContext $context)
    {
        return [];
    }

    private function getAttachments(OutputInterface $output)
    {
        ob_start();
        $modelFactory = $this->getProjectApp()->getContainer()->get('model/factory');
        ob_end_clean();

        if (!$modelFactory) {
            return [];
        }

        $attachments = $this->loadAttachments($modelFactory, $output);
        $attachments = array_filter($attachments, function ($attachment) {
            return !empty($attachment->metadata()->get('sources'));
        });

        return $attachments;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->validateProject()) {
            $this->writeError('Your project is not a valid Charcoal project', $output);
            return self::$FAILURE;
        }

        ob_start();
        $modelFactory = $this->getProjectApp()->getContainer()->get('model/factory');
        ob_end_clean();

        if (!$modelFactory) {
            $this->writeError('Failed to get model factory from app container', $output);
            return self::$FAILURE;
        }

        $attachments = $this->getAttachments($output);
        if (empty($attachments)) {
            $this->writeError('No attachments were found in the current directory.', $output);
            return self::$FAILURE;
        }

        $this->isDryRun = $input->getOption('dry');
        if ($this->isDryRun) {
            $output->writeln('<fg=yellow;bg=red;options=bold> - DRY RUN - </>');
        }

        $do_create = true;
        $do_update = ($input->getOption('create-only') ?? false) == false;

        $output->writeln(sprintf(
            '%s Attachments table',
            ($do_create && $do_update) ? 'Creating/Updating' : ($do_create ? 'Creating' : 'Updating')
        ));

        $createCount = 0;
        $updateCount = 0;
        $fieldCache = [];

        foreach ($attachments as $attachment) {
            try {
                if ($do_create && !$attachment->source()->tableExists()) {
                    $this->createTable($attachment, $output);
                    $createCount++;
                } elseif ($do_update) {
                    // Check for conflicts with existing fields
                    $this->updateTable($attachment, $output, $fieldCache);

                    $updateCount++;
                }
            } catch (\Throwable $th) {
                $this->writeError($th->getMessage(), $output);
                $output->writeln(sprintf(
                    '<fg=red>Failed to synchronize attachment: %s</>',
                    $attachment::objType()
                ));
            }
        }

        // Conflicts
        $fieldConflicts = array_filter($fieldCache, function ($item) {
            $typeHasProblems       = !empty($item['type']) ? count($item['type']) > 1 : false;
            $defaultValHasProblems = !empty($item['default_val']) ? count($item['default_val']) > 1 : false;
            $allowNullHasProblems  = !empty($item['allow_null']) ? count($item['allow_null']) > 1 : false;

            return $typeHasProblems || $defaultValHasProblems || $allowNullHasProblems;
        });

        if (!empty($fieldConflicts)) {
            $output->writeln('');
            $output->writeln(sprintf(
                '<fg=red>%s</>',
                'WARNING - You have some property conflicts'
            ));
            foreach ($fieldConflicts as $key => $value) {
                $output->writeln('');
                $output->writeln(sprintf('Property <fg=yellow;options=bold>%s</>:', $key));
                foreach ($value['type'] as $type => $attachments) {
                    $output->writeln(sprintf('- These attachments define the type as: <fg=yellow;options=bold>%s</>', $type));
                    foreach ($attachments as $attachment) {
                        $output->writeln(sprintf('-- %s', $attachment));
                    }
                }
            }
        } else {
            // Do actual update here.
            if (!$this->isDryRun) {
                foreach ($attachments as $attachment) {
                    $this->timer()->start();
                    /** @var DatabaseSource $source */
                    $source = $attachment->source();
                    $source->alterTable();

                    $output->writeln(sprintf(
                        '<fg=green>Updated %s - %ss</>',
                        $source->table(),
                        $this->timer()->stop()
                    ));
                }
            }
        }

        $output->writeln('<info>All Done!</info>');

        $messages = [];

        if ($createCount) {
            $messages[] = sprintf(
                'created %s %s',
                $createCount,
                $createCount == 1 ? 'table' : 'tables'
            );
        }

        if ($updateCount) {
            $messages[] = sprintf(
                'updated %s %s',
                $updateCount,
                $updateCount == 1 ? 'table' : 'tables'
            );
        }

        $message = ucfirst(implode(' and ', $messages));

        if (!empty($message)) {
            $output->writeln(sprintf('<info>%s</info>', $message));
        }

        return self::$SUCCESS;
    }

    private function createTable(ModelInterface $attachment, OutputInterface $output)
    {
        $class_name = get_class($attachment);
        $output->writeln(sprintf('<fg=green>Creating table</> for <fg=yellow;options=bold>%s</>', $class_name));

        if (!$this->isDryRun) {
            /** @var DatabaseSource $source */
            $source = $attachment->source();
            $source->createTable();

            $output->writeln(sprintf(
                '<fg=green>Created table: %s - %ss</>',
                $source->table(),
                $this->timer()->stop()
            ));
        }
    }

    private function updateTable(ModelInterface $attachment, OutputInterface $output, &$fieldCache = [])
    {
        $class_name = get_class($attachment);
        $changes = $this->getChanges($attachment, $output);

        if (!empty($changes['alterations'])) {
            $output->writeln(sprintf('<fg=blue>Updating attachments table</> for <fg=yellow;options=bold>%s</>', $class_name));
            $changes['table']->render();
        } else {
            $output->writeln(sprintf('Skipping <fg=yellow;options=bold>%s</>: already up-to-date', $class_name));
        }

        if (!empty($changes['properties'])) {
            // Check for conflicts
            foreach ($changes['properties'] as $property => $aspects) {
                if (empty($fieldCache[$property])) {
                    $fieldCache[$property] = [];
                }

                foreach ($aspects as $aspect => $value) {
                    $fieldCache[$property][$aspect][$value][] = $class_name;
                }
            }
        }
    }

    private function getChanges(ModelInterface $attachment, OutputInterface $output): ?array
    {
        /** @var DatabaseSource $source */
        $source = $attachment->source();
        $fields = $this->getAttachmentFields($attachment);
        $tableStructure = $source->tableStructure();

        $table = (new Table($output))->setHeaders(['Property', 'Aspect', 'Old', 'New']);
        $alterations = 0;
        $properties = [];

        foreach ($fields as $field) {
            $ident = $field->ident();

            if (!array_key_exists($ident, $tableStructure)) {
                $fieldSql = $field->sql();
                if ($fieldSql) {
                    $alterations++;
                    $table->addRow([$ident, '--', '--', 'CREATE']);
                    $properties[$ident]['type'] = strtolower($field->sqlType());
                }
            } else {
                // The key exists. Validate.
                $col   = $tableStructure[$ident];

                $properties[$ident] = [
                    'type'        => strtolower($field->sqlType()),
                    'allow_null'  => $field->allowNull() ? 'true' : 'false',
                    'default_val' => !empty($field->defaultVal()) ? $field->defaultVal() : 'EMPTY'
                ];

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

        return [
            'properties'  => $properties,
            'alterations' => $alterations ?? false,
            'table'       => $table ?? false,
        ];
    }

    private function getAttachmentFields(ModelInterface $attachment)
    {
        /** @var Attachment $attachment */
        $properties = array_keys($attachment->metadata()->properties());

        $fields = [];
        foreach ($properties as $propertyIdent) {
            /** @var AbstractProperty $prop */
            $prop = $attachment->property($propertyIdent);
            if (!$prop || !$prop['active'] || !$prop['storable']) {
                continue;
            }

            $val = $attachment->propertyValue($propertyIdent);
            foreach ($prop->fields($val) as $fieldIdent => $field) {
                $fields[$field->ident()] = $field;
            }
        }

        return $fields;
    }
}
