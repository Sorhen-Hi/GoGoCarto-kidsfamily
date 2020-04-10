<?php

namespace App\Command;

use App\Document\ImportState;
use App\Services\ElementImportService;
use Doctrine\ODM\MongoDB\DocumentManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ImportSourceCommand extends GoGoAbstractCommand
{
    public function __construct(DocumentManager $dm, LoggerInterface $commandsLogger,
                               TokenStorageInterface $security,
                               ElementImportService $importService)
    {
        $this->importService = $importService;
        parent::__construct($dm, $commandsLogger, $security);
    }

    protected function gogoConfigure(): void
    {
        $this
        ->setName('app:elements:importSource')
        ->setDescription('Check for updating external sources')
        ->addArgument('sourceNameOrImportId', InputArgument::REQUIRED, 'The name of the source');
    }

    protected function gogoExecute(DocumentManager $dm, InputInterface $input, OutputInterface $output): void
    {
        try {
            $this->output = $output;
            $sourceNameOrId = $input->getArgument('sourceNameOrImportId');
            $import = $dm->getRepository('App\Document\Import')->find($sourceNameOrId);
            if (!$import) {
                $import = $dm->getRepository('App\Document\Import')->findOneBySourceName($sourceNameOrId);
            }
            if (!$import) {
                $message = "ERREUR pendant l'import : Aucune source avec pour nom ou id ".$input->getArgument('sourceNameOrImportId')." n'existe dans la base de donnée ".$input->getArgument('dbname');
                $this->error($message);

                return;
            }

            $this->log('Updating source '.$import->getSourceName().' for project '.$input->getArgument('dbname').' begins...');
            $result = $this->importService->startImport($import);
            $this->log($result);
        } catch (\Exception $e) {
            $this->dm->persist($import);
            $import->setCurrState(ImportState::Failed);
            $message = $e->getMessage().'</br>'.$e->getFile().' LINE '.$e->getLine();
            $import->setCurrMessage($message);
            $this->error('Source: '.$import->getSourceName().' - '.$message);
        }
    }
}