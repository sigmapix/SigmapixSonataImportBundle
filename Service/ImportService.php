<?php

namespace Sigmapix\Sonata\ImportBundle\Service;

use Port\Csv\CsvReader;
use Port\Doctrine\DoctrineWriter;
use Port\Excel\ExcelReader;
use Port\Steps\Step\ValueConverterStep;
use Port\Steps\StepAggregator;
use Port\ValueConverter\DateTimeValueConverter;
use Doctrine\ORM\EntityManagerInterface;
use Sonata\DoctrineORMAdminBundle\Admin\FieldDescription;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ImportService
{
    /**
     * @var EntityManagerInterface
     */
    protected $em;

    private $session;

    private $container;

    /**
     * @var string
     */
    private $doctrineWriterClass;

    public function __construct(EntityManagerInterface $em, $session, ContainerInterface $container, string $doctrineWriterClass)
    {
        $this->em = $em;
        $this->session = $session;
        $this->container = $container;
        $this->doctrineWriterClass = $doctrineWriterClass;
    }

    /**
     * @param UploadedFile $file
     * @return array
     */
    public function getHeaders(UploadedFile $file)
    {
        $reader = $this->getReader($file);
        $headers = array_flip($reader->getColumnHeaders());
        array_walk($headers, function(&$v, $k) use ($headers) { $v = $k; });
        return $headers;
    }

    public function import(UploadedFile $file, Form $form)
    {
        $mapping = [];
        $dateTimeFields = [];
        foreach ($form as $f) {
            /** @var Form $f */
            /** @var FieldDescription $fieldOptions */
            $fieldOptions = $f->getConfig()->getOption('sonata_field_description');
            if ($fieldOptions && $fieldOptions->getMappingType() === 'datetime') {
                $dateTimeFields[] = $f->getName();
            }
            if ($f instanceof SubmitButton) continue;
            $mapping[$f->getName()] = $f->getNormData();
        }

        $reader = $this->getReader($file);

        // Replace columnsHeader names with entity field name in our $mapping
        $columnHeaders = array_map(function($h) use ($mapping) {
            $k = array_search($h, (array) $mapping, true);
            return ($k === false ? $h : $k);
        }, $reader->getColumnHeaders());
        $reader->setColumnHeaders($columnHeaders);

        /** @var DoctrineWriter $writer */
        $writer = new $this->doctrineWriterClass($this->em, get_class($form->getData()));
        if (method_exists($writer, 'setContainer')) {
            $writer->setContainer($this->container);
        }
        $writer->disableTruncate();

        $converter = new DateTimeValueConverter('d/m/Y');
        $converterStep = new ValueConverterStep();
        foreach ($dateTimeFields as $dateTimeField) {
            $converterStep->add('['.$dateTimeField.']', $converter);
        }

        $workflow = new StepAggregator($reader);
        $result = $workflow
            ->addWriter($writer)
            ->addStep($converterStep)
            ->process();

        return true;
    }

    private function getReader(UploadedFile $file)
    {
        $pathFile = $file->getRealPath();
        $fileExtension = $file->guessExtension();
        $excelExtensions = array('xls', 'xlsx', 'zip');

        if (in_array($fileExtension, $excelExtensions)) {
            $reader = new ExcelReader(new \SplFileObject($pathFile), 0, 0);
        } else {
            $reader = new CsvReader(new \SplFileObject($pathFile), ';');
            $reader->setHeaderRowNumber(0, CsvReader::DUPLICATE_HEADERS_INCREMENT);
        }

        return $reader;
    }

    /**
    * Get session
    * @return  
    */
    public function getSession()
    {
        return $this->session;
    }
    
    /**
    * Set session
    * @return $this
    */
    public function setSession($session)
    {
        $this->session = $session;
        return $this;
    }
}
