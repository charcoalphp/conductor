<?php

namespace Charcoal\Conductor\Command\Models;

use Charcoal\Conductor\Traits\ModelAwareTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Charcoal\Conductor\Command\AbstractCommand;
use Charcoal\Model\Model;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Console\Question\ChoiceQuestion;

class CreateModel extends AbstractCommand
{
    use ModelAwareTrait;

    /**
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('models:create')
            ->setDescription('Create a new Model.')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command generates a new model/object within your Charcoal project
EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->validateProject()) {
            $output->write('Your project is not a valid Charcoal project');
            return self::$FAILURE;
        }

        /** @var QuestionHelper $questionHelper */
        $questionHelper = $this->getHelper('question');
        $filesystem = new Filesystem();
        $finder = new Finder();

        $modelName = $questionHelper->ask(
            $input,
            $output,
            (new Question('What is the name of your model? (ex. Example, LongExample): '))->setValidator(function ($answer) {
                if (empty($answer)) {
                    throw new \RuntimeException(
                        'The name must not be empty'
                    );
                }

                if (!preg_match('/^[A-Z][a-z](?>[A-Za-z]+)?$/', $answer, $matches)) {
                    throw new \RuntimeException(
                        'The name must be a valid camelcase string'
                    );
                }

                return $answer;
            })
        );

        // Filter by App namespace;
        $projectDirectory = $this->getProjectDir();
        $hasAppNamespace = file_exists($projectDirectory . '/src/App/Object');
        $namespaceToUse = 'App/Object';

        //if (!$hasAppNamespace) {
        if (true) {
            // Try to find the app's custom namespace
            $rootNamespaces = [];

            ob_start();
            $modelFactory = $this->getProjectApp()->getContainer()->get('model/factory');
            ob_end_clean();

            if (!$modelFactory) {
                return self::$FAILURE;
            }

            // Filter out non-app namespaces.
            $models = $this->loadModels($modelFactory, $output);
            $models = array_filter($models, function ($model) {
                /** @var Model $model */
                $class = get_class($model);

                if (strpos(strtolower($class), 'charcoal') !== false) {
                    return false;
                }

                return true;
            });

            foreach ($models as $model) {
                $namespace = get_class($model);
                //if (preg_match('/(.*?\\\.*?)\\\.*$/', $namespace, $matches)) {
                if (preg_match('/^(([a-zA-Z]+?\\\[a-zA-Z]+).*)\\\.*$/', $namespace, $matches)) {
                    if (!empty($matches[2])) {
                        $rootNamespace = $matches[2];

                        if (!in_array($rootNamespace, $rootNamespaces)) {
                            $rootNamespaces[] = $rootNamespace;
                        }

                        if (!empty($matches[1])) {
                            $deepNamespace = $matches[1];

                            if (!in_array($deepNamespace, $rootNamespaces)) {
                                $rootNamespaces[] = $deepNamespace;
                            }
                        }
                    }
                }
            };

            $namespaceCount = count($rootNamespaces);
            if (!$namespaceCount) {
                // No other namespaces found.
            } else {
                if ($namespaceCount == 1) {
                    $namespaceToUse = $rootNamespaces[0];
                } else {
                    // Give the user some options
                    $answer = $questionHelper->ask(
                        $input,
                        $output,
                        (new ChoiceQuestion('Which namespace would you like to use?', $rootNamespaces))
                    );

                    $array_key = array_search($answer, $rootNamespaces);

                    if (!empty($array_key)) {
                        $namespaceToUse = $rootNamespaces[$array_key];
                    }
                }
            }
        }

        $output->writeln('Creating model in namespace: ' . ucfirst($namespaceToUse));

        $namespaceFolder = str_replace('\\', '/', $namespaceToUse);

        try {
            $kebabModelName = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $modelName));
            $kebabNamespace = strtolower(preg_replace('/(?<!^)([a-z])([A-Z])/', '$1-$2', $namespaceFolder));
            $snakeModelName = str_replace('-', '_', $kebabModelName);

            // Make metadata file.
            $modelMetaDirectory = $projectDirectory . '/metadata/' . strtolower($namespaceFolder);

            if (!$filesystem->exists($modelMetaDirectory)) {
                $filesystem->mkdir($modelMetaDirectory);
            }

            $metaFilePath = $modelMetaDirectory . '/' . $kebabModelName . '.json';
            if (!$filesystem->exists($metaFilePath)) {
                $output->writeln('Generating model meta: ' . $metaFilePath);

                $getDefaultContent = file_get_contents(__DIR__ . '/Samples/ModelSample.json');

                // Replace object type
                $getDefaultContent = str_replace('{OBJECT_TYPE}', $kebabModelName, $getDefaultContent);

                // Replace namespace
                $getDefaultContent = str_replace('{NAMESPACE}', $kebabNamespace, $getDefaultContent);

                // Replace snakecase
                $getDefaultContent = str_replace('{OBJECT_TYPE_SNAKE}', $snakeModelName, $getDefaultContent);

                $filesystem->dumpFile($metaFilePath, $getDefaultContent);
            }

            // Make metadata admin file.
            $modelMetaDirectory = $projectDirectory . '/metadata/admin/' . strtolower($namespaceFolder);

            if (!$filesystem->exists($modelMetaDirectory)) {
                $filesystem->mkdir($modelMetaDirectory);
            }

            $metaFilePath = $modelMetaDirectory . '/' . $kebabModelName . '.json';
            if (!$filesystem->exists($metaFilePath)) {
                $output->writeln('Generating model admin meta: ' . $metaFilePath);

                $getDefaultContent = file_get_contents(__DIR__ . '/Samples/ModelSampleAdmin.json');

                // Replace namespace
                $getDefaultContent = str_replace('{NAMESPACE}', $kebabNamespace, $getDefaultContent);

                // Replace object type
                $getDefaultContent = str_replace('{OBJECT_TYPE}', $kebabModelName, $getDefaultContent);

                $filesystem->dumpFile($metaFilePath, $getDefaultContent);
            }

            // Make PHP file.
            $modelDirectory = $projectDirectory . '/src/' . $namespaceFolder;

            if (!$filesystem->exists($modelDirectory)) {
                $filesystem->mkdir($modelDirectory);
            }

            $modelFilePath = $modelDirectory . '/' . $modelName . '.php';
            if (!$filesystem->exists($modelFilePath)) {
                $output->writeln('Generating model class: ' . $modelFilePath);

                $getDefaultContent = file_get_contents(__DIR__ . '/Samples/ModelSample.php');
                // Replace class name.
                $getDefaultContent = str_replace('ModelSample', $modelName, $getDefaultContent);
                // Replace namespace.
                $getDefaultContent = str_replace('App\Object', $namespaceToUse, $getDefaultContent);
                $filesystem->dumpFile($modelFilePath, $getDefaultContent);
            }
        } catch (\Throwable $th) {
            //throw $th;
            $this->writeError($th->getMessage(), $output);
            return self::$FAILURE;
        }

        $output->writeln(sprintf(
            '<fg=green>Created model: %s</>',
            $namespaceToUse . '\\' . $modelName
        ));

        return self::$SUCCESS;
    }
}
