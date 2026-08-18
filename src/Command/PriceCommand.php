<?php

declare(strict_types=1);

namespace Errogaht\WaveSpeedAiBundle\Command;

use Errogaht\WaveSpeedAiBundle\Contract\WaveSpeedClientInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Calls the live pricing endpoint without starting a billable prediction. */
#[AsCommand(name: 'wavespeed:price', description: 'Estimate the exact live WaveSpeed price for model inputs')]
final class PriceCommand extends Command
{
    public function __construct(private readonly WaveSpeedClientInterface $client)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('model', InputArgument::REQUIRED, 'WaveSpeed model ID')
            ->addArgument('inputs', InputArgument::REQUIRED, 'JSON object with final model inputs');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $model = $input->getArgument('model');
            $json = $input->getArgument('inputs');
            if (!\is_string($model) || !\is_string($json)) {
                throw new \InvalidArgumentException('Model and JSON inputs are required.');
            }
            $inputs = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
            if (!\is_array($inputs)) {
                throw new \InvalidArgumentException('Inputs must decode to a JSON object.');
            }
            $estimate = $this->client->price($model, $inputs);
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }
        $io->success(\sprintf('Estimated price: %s (no generation submitted)', $estimate->formatted()));

        return Command::SUCCESS;
    }
}
