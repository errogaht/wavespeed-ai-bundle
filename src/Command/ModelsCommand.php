<?php

declare(strict_types=1);

namespace Errogaht\WaveSpeedAiBundle\Command;

use Errogaht\WaveSpeedAiBundle\Enum\InputModality;
use Errogaht\WaveSpeedAiBundle\Model\ModelCatalog;
use Errogaht\WaveSpeedAiBundle\Model\ModelCriteria;
use Errogaht\WaveSpeedAiBundle\Model\ModelSelector;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Lets humans and agents shortlist video models by semantic capability and base cost. */
#[AsCommand(name: 'wavespeed:models', description: 'List and recommend WaveSpeed video models from the bundled catalog')]
final class ModelsCommand extends Command
{
    public function __construct(
        private readonly ModelCatalog $catalog,
        private readonly ModelSelector $selector,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Model type, e.g. text-to-video')
            ->addOption('input', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Required modality: text, image, video, audio')
            ->addOption('max-base-price', null, InputOption::VALUE_REQUIRED, 'Maximum advertised base price in USD')
            ->addOption('native-audio', null, InputOption::VALUE_NONE, 'Require native audio controls')
            ->addOption('multi-image', null, InputOption::VALUE_NONE, 'Require multiple reference images')
            ->addOption('multi-video', null, InputOption::VALUE_NONE, 'Require multiple reference videos')
            ->addOption('query', 'q', InputOption::VALUE_REQUIRED, 'Search IDs and descriptions')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum rows', '30');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $modalities = [];
            foreach ((array) $input->getOption('input') as $value) {
                if (\is_string($value)) {
                    $modalities[] = InputModality::from($value);
                }
            }
            if ([] === $modalities) {
                $modalities = [InputModality::Text];
            }
            $max = $input->getOption('max-base-price');
            $criteria = new ModelCriteria(
                $modalities,
                \is_string($input->getOption('type')) ? $input->getOption('type') : null,
                is_numeric($max) ? (float) $max : null,
                (bool) $input->getOption('native-audio'),
                (bool) $input->getOption('multi-image'),
                (bool) $input->getOption('multi-video'),
                \is_string($input->getOption('query')) ? $input->getOption('query') : null,
            );
            $models = $this->selector->recommend($criteria, (int) $input->getOption('limit'));
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::INVALID;
        }
        $rows = [];
        foreach ($models as $model) {
            $rows[] = [
                $model->id,
                $model->type,
                '$'.number_format($model->basePriceUsd, 4),
                implode(',', array_map(static fn (InputModality $modality): string => $modality->value, $model->inputModalities())),
                $model->supportsNativeAudio() ? 'yes' : 'no',
            ];
        }
        $io->title(\sprintf('WaveSpeed video models (%d bundled, %d matches)', \count($this->catalog->video()), \count($models)));
        $io->table(['Model', 'Type', 'Base price', 'Inputs', 'Audio'], $rows);
        $io->note('Base price is only a floor. Run wavespeed:price with final inputs before generation.');

        return Command::SUCCESS;
    }
}
