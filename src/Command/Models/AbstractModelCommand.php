<?php

namespace Charcoal\Conductor\Command\Models;

use Charcoal\Conductor\Command\AbstractCommand;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\SplFileInfo;
use Throwable;

abstract class AbstractModelCommand extends AbstractCommand
{
    public function loadObjects($modelFactory, OutputInterface $output, callable $filterFunction = null)
    {
        $finder = new Finder();
        $finder->files()->in($this->getProjectDir() . '/src/')->name('*.php');
        $models = [];

        foreach ($finder as $file) {
            /** @var SplFileInfo $file */
            $namespace = str_replace('/', '\\', '/' . $file->getRelativePath());

            if (
                !(
                    (
                        (is_callable($filterFunction) && $filterFunction($namespace)) ||
                        !is_callable($filterFunction)
                    ) &&
                    !str_contains($namespace, 'Transformer') &&
                    !str_contains($namespace, 'Shared') &&
                    !str_contains($file->getFilename(), 'Abstract') &&
                    !str_contains($file->getFilename(), 'Interface') &&
                    !str_contains($file->getFilename(), 'Trait')
                )
            ) {
                continue;
            }

            $class_name = rtrim($namespace, '\\') . '\\' . $file->getFilenameWithoutExtension();

            try {
                $newModel = $modelFactory->create($class_name);
                $models[] = $newModel;
            } catch (Throwable $e) {
                if ($output->isDebug()) {
                    $output->writeln("ModelFactory failed to create model using class '$class_name': " . $e->getMessage());
                }
                continue;
            }
        }

        return $models;
    }

    public function loadModels($modelFactory, OutputInterface $output): array
    {
        return $this->loadObjects($modelFactory, $output, function ($namespace) {
            return (
                str_contains($namespace, 'Object') ||
                str_contains($namespace, 'Model')
            );
        });
    }

    public function loadAttachments($modelFactory, OutputInterface $output): array
    {
        return $this->loadObjects($modelFactory, $output, function ($namespace) {
            return (
                str_contains($namespace, 'Attachment')
            );
        });
    }

    public function kebabcaseModelName(string $modelName): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $modelName));
    }

    public function kebabcaseNamespace(string $namespace): string
    {
        return strtolower(preg_replace('/(?<!^)([a-z])([A-Z])/', '$1-$2', $namespace));
    }

    public function snakecaseModelName(string $modelName): string
    {
        $kebabcase = $this->kebabcaseModelName($modelName);
        return str_replace('-', '_', $kebabcase);
    }

    public function snakecaseNamespace(string $namespace): string
    {
        $kebabcase = $this->kebabcaseNamespace($namespace);
        $snakeNamespace = str_replace('/', '_', str_replace('-', '_', $kebabcase));

        // Remove models/objects from snakeNamespace.
        $snakeNamespaceParts = explode('_', $snakeNamespace);
        array_splice($snakeNamespaceParts, 1, 1);
        $snakeNamespace = implode('_', $snakeNamespaceParts);

        return $snakeNamespace;
    }
}
