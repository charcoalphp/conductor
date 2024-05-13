<?php

namespace Charcoal\Conductor\Command\Models;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Charcoal\Model\Model;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class CreateModel extends AbstractModelCommand
{
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
        $namespaceToUse = 'App\Object';

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

                    if ($array_key !== false) {
                        $namespaceToUse = $rootNamespaces[$array_key];
                    }
                }
            }
        }

        $output->writeln('Creating model in namespace: ' . ucfirst($namespaceToUse));

        $namespaceFolder = str_replace('\\', '/', $namespaceToUse);

        try {
            $questionHelper = $this->getQuestionHelper();
            $question = new ConfirmationQuestion('Would you like the model to be routable? (Y/n) ');
            $routable = $questionHelper->ask($input, $output, $question);

            $kebabModelName = $this->kebabcaseModelName($modelName);

            // Make metadata file.
            $modelMetaDirectory = $this->getProjectDir() . '/metadata/' . strtolower($namespaceFolder);

            if (!$filesystem->exists($modelMetaDirectory)) {
                $filesystem->mkdir($modelMetaDirectory);
            }

            $metaFilePath = $modelMetaDirectory . '/' . $kebabModelName . '.json';
            if (!$filesystem->exists($metaFilePath)) {
                $output->writeln('Generating model meta: ' . $metaFilePath);

                $modelSampleFile = $routable ? 'ModelSampleRoutable' : 'ModelSample';
                $getDefaultContent = file_get_contents(__DIR__ . sprintf('/Samples/%s.json', $modelSampleFile));

                $filesystem->dumpFile($metaFilePath, $this->placeholdersModelMeta(
                    $getDefaultContent,
                    $modelName,
                    $namespaceFolder
                ));
            }

            // Make metadata admin file.
            $modelMetaDirectory = $this->getProjectDir() . '/metadata/admin/' . strtolower($namespaceFolder);

            if (!$filesystem->exists($modelMetaDirectory)) {
                $filesystem->mkdir($modelMetaDirectory);
            }

            $metaFilePath = $modelMetaDirectory . '/' . $kebabModelName . '.json';
            if (!$filesystem->exists($metaFilePath)) {
                $output->writeln('Generating model admin meta: ' . $metaFilePath);

                $getDefaultContent = file_get_contents(__DIR__ . '/Samples/ModelSampleAdmin.json');

                $filesystem->dumpFile($metaFilePath, $this->placeholdersModelAdminMeta(
                    $getDefaultContent,
                    $modelName,
                    $namespaceFolder
                ));
            }

            // Make PHP file.
            $modelDirectory = $this->getProjectDir() . '/src/' . $namespaceFolder;

            if (!$filesystem->exists($modelDirectory)) {
                $filesystem->mkdir($modelDirectory);
            }

            $modelFilePath = $modelDirectory . '/' . $modelName . '.php';
            if (!$filesystem->exists($modelFilePath)) {
                $output->writeln('Generating model class: ' . $modelFilePath);

                $modelSampleFile = $routable ? 'ModelSampleRoutable' : 'ModelSample';
                $getDefaultContent = file_get_contents(__DIR__ . sprintf('/Samples/%s.php', $modelSampleFile));

                $filesystem->dumpFile($modelFilePath, $this->placeholdersModel(
                    $getDefaultContent,
                    $modelName,
                    $namespaceToUse
                ));
            }
        } catch (\Throwable $th) {
            $this->writeError($th->getMessage(), $output);
            return self::$FAILURE;
        }

        $output->writeln(sprintf(
            '<fg=green>Created model: %s</>',
            $namespaceToUse . '\\' . $modelName
        ));

        return self::$SUCCESS;
    }

    private function placeholdersModelMeta(string $content, string $modelName, string $namespace)
    {
        // Replace object type
        $content = str_replace('{OBJECT_TYPE}', $this->kebabcaseModelName($modelName), $content);

        // Replace namespace
        $content = str_replace('{NAMESPACE}', $this->kebabcaseNamespace($namespace), $content);

        // Replace snakecase
        $content = str_replace('{NAMESPACE_SNAKE}', $this->snakecaseNamespace($namespace), $content);
        $content = str_replace('{OBJECT_TYPE_SNAKE}', $this->snakecaseModelName($modelName), $content);

        return $content;
    }

    private function placeholdersModelAdminMeta($content, string $modelName, string $namespace)
    {
        // Replace namespace
        $content = str_replace('{NAMESPACE}', $this->kebabcaseNamespace($namespace), $content);

        // Replace object type
        $content = str_replace('{OBJECT_TYPE}', $this->kebabcaseModelName($modelName), $content);

        return $content;
    }

    private function placeholdersModel(string $content, string $modelName, string $namespace)
    {
        // Replace class name.
        $content = str_replace('ModelSample', $modelName, $content);

        // Replace namespace.
        $content = str_replace('App\Object', $namespace, $content);

        return $content;
    }
}
