<?php

declare(strict_types=1);

namespace Errogaht\WaveSpeedAiBundle\Command;

use Errogaht\WaveSpeedAiBundle\Model\ModelCatalog;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Prints the complete input contract so an agent can construct a valid request. */
#[AsCommand(name: 'wavespeed:model', description: 'Describe one WaveSpeed model, capabilities, price floor and JSON Schema')]
final class DescribeModelCommand extends Command
{
    public function __construct(private readonly ModelCatalog $catalog)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('model', InputArgument::REQUIRED, 'WaveSpeed model ID')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Return machine-readable JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getArgument('model');
        if (!\is_string($id)) {
            return Command::INVALID;
        }
        $io = new SymfonyStyle($input, $output);
        try {
            $model = $this->catalog->get($id);
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }
        $payload = [
            'id' => $model->id,
            'name' => $model->name,
            'type' => $model->type,
            'description' => $model->description,
            'base_price_usd' => $model->basePriceUsd,
            'api_path' => $model->apiPath,
            'capabilities' => [
                'input_modalities' => array_map(static fn ($modality): string => $modality->value, $model->inputModalities()),
                'native_audio' => $model->supportsNativeAudio(),
                'multiple_images' => $model->supportsMultipleImages(),
                'multiple_videos' => $model->supportsMultipleVideos(),
                'resolutions' => $model->resolutions(),
                'durations' => $model->durations(),
            ],
            'required_inputs' => $model->requiredInputs(),
            'request_schema' => $model->requestSchema,
        ];
        if ((bool) $input->getOption('json')) {
            $output->writeln((string) json_encode($payload, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR));

            return Command::SUCCESS;
        }
        $io->title($model->id);
        $io->text($model->description);
        $io->definitionList(
            ['Type' => $model->type],
            ['Base price' => '$'.number_format($model->basePriceUsd, 6)],
            ['Required inputs' => implode(', ', $model->requiredInputs())],
            ['Native audio controls' => $model->supportsNativeAudio() ? 'yes' : 'no'],
            ['Multiple images' => $model->supportsMultipleImages() ? 'yes' : 'no'],
            ['Multiple videos' => $model->supportsMultipleVideos() ? 'yes' : 'no'],
        );
        $io->section('Request JSON Schema');
        $io->writeln((string) json_encode($model->requestSchema, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    }
}
