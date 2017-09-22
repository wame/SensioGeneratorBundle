<?php
declare(strict_types=1);

namespace Wame\SensioGeneratorBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wame\SensioGeneratorBundle\Generator\WameDatatableGenerator;
use Wame\SensioGeneratorBundle\Generator\WameVoterGenerator;

class WameDatatableCommand extends ContainerAwareCommand
{
    use WameCommandTrait;

    protected function configure()
    {
        $this
            ->setName('wame:generate:datatable')
            ->setDescription('Generates a Datatable based on a Doctrine entity')
            ->addArgument('entity', InputArgument::REQUIRED, 'The entity class name to initialize (shortcut notation)')
            ->addOption('overwrite', null, InputOption::VALUE_NONE, 'Overwrite file if already exists')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $crudQuestionHelper = $this->getCrudQuestionHelper();

        $metaEntity = $this->getMetaEntityFormInput($input);
        $forceOverwrite = $input->getOption('overwrite');

        $crudQuestionHelper->writeSection($output, 'Datatable generation');
        if ($this->getDatatableGenerator()->generate($metaEntity, $forceOverwrite) === false) {
            $output->writeln('');
            $output->writeln('  To overwrite file, use the --overwrite option');
        }
        $output->writeln('');
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $crudQuestionHelper = $this->getCrudQuestionHelper();

        if (!$input->hasArgument('entity') || !$input->getArgument('entity')) {
            $crudQuestionHelper->askEntityName($input, $output, $this->defaultBundle);
        }
    }

    protected function getDatatableGenerator(): WameDatatableGenerator
    {
        return $this->getContainer()->get(WameDatatableGenerator::class);
    }
}