<?php

namespace Charcoal\Conductor\Command\Models;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Charcoal\Attachment\Object\Attachment;

class CreateAttachment extends AbstractModelCommand
{
    /**
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('attachments:create')
            ->setDescription('Create a new Attachment.')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command generates a new attachment within your Charcoal project
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

        $attachmentName = $questionHelper->ask(
            $input,
            $output,
            (new Question('What is the name of your attachment? (ex. Example, LongExample): '))->setValidator(function ($answer) {
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
        $namespaceToUse = 'App\Model\Attachment';

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
            $attachments = $this->loadAttachments($modelFactory, $output);
            $attachments = array_filter($attachments, function ($attachment) {
                /** @var Attachment $attachment */
                $class = get_class($attachment);

                if (strpos(strtolower($class), 'charcoal') !== false) {
                    return false;
                }

                return true;
            });

            foreach ($attachments as $attachment) {
                $namespace = get_class($attachment);
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

        $output->writeln('Creating attachment in namespace: ' . ucfirst($namespaceToUse));

        $namespaceFolder = str_replace('\\', '/', $namespaceToUse);

        try {
            $kebabAttachmentName = $this->kebabcaseModelName($attachmentName);

            // Make metadata file.
            $attachmentMetaDirectory = $this->getProjectDir() . '/metadata/' . strtolower($namespaceFolder);

            if (!$filesystem->exists($attachmentMetaDirectory)) {
                $filesystem->mkdir($attachmentMetaDirectory);
            }

            $metaFilePath = $attachmentMetaDirectory . '/' . $kebabAttachmentName . '.json';
            if (!$filesystem->exists($metaFilePath)) {
                $output->writeln('Generating attachment meta: ' . $metaFilePath);

                $attachmentSampleFile = 'AttachmentSample';
                $getDefaultContent = file_get_contents(__DIR__ . sprintf('/Samples/attachment/%s.json', $attachmentSampleFile));

                $filesystem->dumpFile($metaFilePath, $this->placeholdersAttachmentMeta(
                    $getDefaultContent,
                    $attachmentName,
                    $namespaceFolder
                ));
            }

            // Make metadata admin file.
            $attachmentMetaDirectory = $this->getProjectDir() . '/metadata/admin/' . strtolower($namespaceFolder);

            if (!$filesystem->exists($attachmentMetaDirectory)) {
                $filesystem->mkdir($attachmentMetaDirectory);
            }

            $metaFilePath = $attachmentMetaDirectory . '/' . $kebabAttachmentName . '.json';
            if (!$filesystem->exists($metaFilePath)) {
                $output->writeln('Generating attachment admin meta: ' . $metaFilePath);

                $getDefaultContent = file_get_contents(__DIR__ . '/Samples/attachment/AttachmentSampleAdmin.json');

                $filesystem->dumpFile($metaFilePath, $this->placeholdersAttachmentAdminMeta(
                    $getDefaultContent,
                    $attachmentName,
                    $namespaceFolder
                ));
            }

            // Make PHP file.
            $attachmentDirectory = $this->getProjectDir() . '/src/' . $namespaceFolder;

            if (!$filesystem->exists($attachmentDirectory)) {
                $filesystem->mkdir($attachmentDirectory);
            }

            $attachmentFilePath = $attachmentDirectory . '/' . $attachmentName . '.php';
            if (!$filesystem->exists($attachmentFilePath)) {
                $output->writeln('Generating attachment class: ' . $attachmentFilePath);

                $attachmentSampleFile = 'AttachmentSample';
                $getDefaultContent = file_get_contents(__DIR__ . sprintf('/Samples/attachment/%s.php', $attachmentSampleFile));

                $filesystem->dumpFile($attachmentFilePath, $this->placeholdersAttachment(
                    $getDefaultContent,
                    $attachmentName,
                    $namespaceToUse
                ));
            }

            // Add to attachments config
            $this->addToAttachmentsConfig($attachmentName, $namespaceFolder, $output);
        } catch (\Throwable $th) {
            $this->writeError($th->getMessage(), $output);
            return self::$FAILURE;
        }

        $output->writeln(sprintf(
            '<fg=green>Created attachment: %s</>',
            $namespaceToUse . '\\' . $attachmentName
        ));

        return self::$SUCCESS;
    }

    private function placeholdersAttachmentMeta(string $content, string $attachmentName, string $namespace)
    {
        // Replace object type
        $content = str_replace('{OBJECT_TYPE}', $this->kebabcaseModelName($attachmentName), $content);

        // Replace namespace
        $content = str_replace('{NAMESPACE}', $this->kebabcaseNamespace($namespace), $content);

        // Replace snakecase
        $content = str_replace('{NAMESPACE_SNAKE}', $this->snakecaseNamespace($namespace), $content);
        $content = str_replace('{OBJECT_TYPE_SNAKE}', $this->snakecaseModelName($attachmentName), $content);

        return $content;
    }

    private function placeholdersAttachmentAdminMeta($content, string $attachmentName, string $namespace)
    {
        // Replace namespace
        $content = str_replace('{NAMESPACE}', $this->kebabcaseNamespace($namespace), $content);

        // Replace object type (Camel)
        $content = str_replace('{OBJECT_NAME}', $attachmentName, $content);

        // Replace object type
        $content = str_replace('{OBJECT_TYPE}', $this->kebabcaseModelName($attachmentName), $content);

        return $content;
    }

    private function placeholdersAttachment(string $content, string $attachmentName, string $namespace)
    {
        // Replace class name.
        $content = str_replace('AttachmentSample', $attachmentName, $content);

        // Replace namespace.
        $content = str_replace('App\Model\Attachment', $namespace, $content);

        return $content;
    }

    private function addToAttachmentsConfig(string $attachmentName, string $namespace, OutputInterface $output)
    {
        $filesystem = new Filesystem();
        $configFilePath = $this->getProjectDir() . '/config/attachments.json';

        if ($filesystem->exists($configFilePath)) {
            $output->writeln('Adding attachment to attachments file: ' . $configFilePath);

            $fileContent = json_decode(file_get_contents($configFilePath), true);

            $fullType = sprintf(
                '%s/%s',
                $this->kebabcaseNamespace($namespace),
                $this->kebabcaseModelName($attachmentName),
            );

            $fileContent['attachments']['attachables'][$fullType] = [
                'label' => $attachmentName,
            ];

            $filesystem->dumpFile($configFilePath, stripslashes(json_encode($fileContent, JSON_PRETTY_PRINT)));
        }
    }
}
