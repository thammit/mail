<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Command;

use MEDIAESSENZ\Mail\Utility\ScssParserUtility;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ClearScssParserCacheCommand extends Command
{
    public function configure(): void
    {
        $this->setDescription('This command clears all css and meta files generated by the scss parser');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title($this->getDescription());

        $deletedFilePaths = ScssParserUtility::deleteCacheFiles();

        if ($deletedFilePaths) {
            $io->writeln('Deleted files:');
            foreach ($deletedFilePaths as $deletedFilePath) {
                $io->writeln($deletedFilePath);
            }
        } else {
            $io->writeln('No css/meta files found.');
        }

        return Command::SUCCESS;

    }
}
