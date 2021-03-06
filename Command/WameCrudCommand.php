<?php
declare(strict_types=1);

namespace Wame\SensioGeneratorBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wame\SensioGeneratorBundle\Command\Helper\CrudQuestionHelper;
use Wame\SensioGeneratorBundle\Generator\DoctrineCrudGenerator;
use Wame\SensioGeneratorBundle\Generator\WameDatatableGenerator;
use Wame\SensioGeneratorBundle\Generator\WameFormGenerator;
use Wame\SensioGeneratorBundle\Generator\WameVoterGenerator;
use Wame\SensioGeneratorBundle\Inflector\Inflector;
use Wame\SensioGeneratorBundle\MetaData\MetaEntityFactory;

/**
 * Wame version of GenerateDoctrineCrudCommand
 *
 * @author Kevin Driessen <kevin@wame.nl>
 */
class WameCrudCommand extends ContainerAwareCommand
{
    use WameCommandTrait;

    protected function configure()
    {
        $this
            ->setName('wame:generate:crud')
            ->setDescription('Generates a CRUD based on a Doctrine entity')
            ->addArgument('entity', InputArgument::REQUIRED, 'The entity class name to initialize (shortcut notation)')
            ->addOption('route-prefix', null, InputOption::VALUE_REQUIRED, 'The route prefix')
            ->addOption('with-write', null, InputOption::VALUE_NONE, 'Whether or not to generate create, new and delete actions')
            ->addOption('with-datatable', null, InputOption::VALUE_NONE, 'Whether or not to generate a datatable')
            ->addOption('with-voter', null, InputOption::VALUE_NONE, 'Whether or not to generate a voter')
            ->addOption('overwrite', null, InputOption::VALUE_NONE, 'Overwrite any existing controller or form class when generating the CRUD contents')
            ->setHelp(<<<EOT
The <info>%command.name%</info> command generates a CRUD based on a Doctrine entity.

The default command only generates the list and show actions.

<info>php %command.full_name% Post --route-prefix=post_admin</info>

Using the --with-write option allows to generate the new, edit and delete actions.

<info>php %command.full_name% AcmeBlogBundle:Post --route-prefix=post_admin --with-write</info>

Every generated file is based on a template. There are default templates but they can be overridden by placing custom templates in one of the following locations, by order of priority:

<info>BUNDLE_PATH/Resources/WameSensioGeneratorBundle/skeleton/crud
APP_PATH/Resources/WameSensioGeneratorBundle/skeleton/crud</info>

And

<info>__bundle_path__/Resources/WameSensioGeneratorBundle/skeleton/form
__project_root__/app/Resources/WameSensioGeneratorBundle/skeleton/form</info>

You can check https://github.com/sensio/SensioGeneratorBundle/tree/master/Resources/skeleton
in order to know the file structure of the skeleton
EOT
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $crudQuestionHelper = $this->getCrudQuestionHelper();

        $this->validateEntityInput($input);
        $entity = WameValidators::validateEntityName($input->getArgument('entity'));
        list($bundle, $entity) = $this->parseShortcutNotation($entity);

        $prefix = $input->getOption('route-prefix') ?: Inflector::tableize($entity);
        $withWrite = $input->getOption('with-write');
        $forceOverwrite = $input->getOption('overwrite');
        $withDatatable = $input->hasOption('with-datatable') ? $input->getOption('with-datatable') : false;
        $withVoter = $input->hasOption('with-voter') ? $input->getOption('with-voter') : false;

        $entityClass = $this->getContainer()->get('doctrine')->getAliasNamespace($bundle).'\\'.$entity;
        $metadata = $this->getEntityMetadata($entityClass);

        $bundle = $this->getContainer()->get('kernel')->getBundle($bundle);

        $crudQuestionHelper->writeSection($output, 'CRUD generation');

        try {
            $this->getGenerator()->generate($bundle, $entity, $metadata[0], $prefix, $withWrite, $forceOverwrite, $withDatatable, $withVoter);
        } catch (\RuntimeException $exception) {
            //The generator may throw an exception because the controller already exists, but we still want to generate the other classes
            if ($forceOverwrite === false) {
                $output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));
            } else { //however, if forceOverwrite is set, an error shouldn't be thrown because of an existing controller, so in this case we throw the error
                throw new $exception;
            }
        }

        $metaEntity = MetaEntityFactory::createFromClassMetadata($metadata[0], $bundle);

        if ($withWrite) {
            $this->getFormGenerator()->generateByMetaEntity($metaEntity, $forceOverwrite);
        }
        if ($withDatatable) {
            $this->getDatatableGenerator()->generate($metaEntity, $forceOverwrite);
        }
        if ($withVoter) {
            $this->getVoterGenerator()->generateByMetaEntity($metaEntity, $forceOverwrite);
        }

        $crudQuestionHelper->writeGeneratorSummary($output, []);
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $crudQuestionHelper = $this->getCrudQuestionHelper();

        if (!$input->hasArgument('entity') || !$input->getArgument('entity')) {
            $crudQuestionHelper->askEntityName($input, $output, $this->defaultBundle);
        }

        $crudQuestionHelper->writeSection($output, 'Welcome to the WAME CRUD generator');

        $entity = $input->getArgument('entity');
        list($bundle, $entity) = $this->parseShortcutNotation($entity);

        $crudQuestionHelper->askRoutePrefix($input, $output, $entity);
        $crudQuestionHelper->askWithWrite($input, $output);
        if ($this->enableDatatables) {
            $crudQuestionHelper->askWithDatatable($input, $output);
        }
        if ($this->enableVoters) {
            $crudQuestionHelper->askWithVoter($input, $output);
        }
    }

    protected function getDatatableGenerator(): WameDatatableGenerator
    {
        return $this->getContainer()->get(WameDatatableGenerator::class);
    }

    protected function getFormGenerator(): WameFormGenerator
    {
        return $this->getContainer()->get(WameFormGenerator::class);
    }

    protected function getVoterGenerator(): WameVoterGenerator
    {
        return $this->getContainer()->get(WameVoterGenerator::class);
    }

    protected function getGenerator(): DoctrineCrudGenerator
    {
        return new DoctrineCrudGenerator(
            $this->getContainer()->getParameter('kernel.root_dir')
        );
    }
}
