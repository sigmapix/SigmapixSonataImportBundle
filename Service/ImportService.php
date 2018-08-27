<?php

namespace Sigmapix\Sonata\ImportBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Port\Csv\CsvReader;
use Port\Doctrine\DoctrineWriter;
use Port\Excel\ExcelReader;
use Port\Steps\StepAggregator;
use Sonata\AdminBundle\Admin\AdminInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class ImportService
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var string
     */
    private $doctrineWriterClass;

    /**
     * ImportService constructor.
     *
     * @param EntityManagerInterface $em
     * @param $session
     * @param ContainerInterface $container
     * @param string             $doctrineWriterClass
     */
    public function __construct(EntityManagerInterface $em, $session, ContainerInterface $container, string $doctrineWriterClass)
    {
        $this->em = $em;
        $this->session = $session;
        $this->container = $container;
        $this->doctrineWriterClass = $doctrineWriterClass;
    }

    /**
     * @param UploadedFile $file
     *
     * @return array
     */
    public function getHeaders(UploadedFile $file)
    {
        $reader = $this->getReader($file);
        $columnHeaders = array_filter($reader->getColumnHeaders(), function ($h) {return null !== $h; });
        $columnHeaders = array_map(
            function ($h) {
                if (!empty($h) && \is_string($h)) {
                    $h = utf8_encode($h);
                }

                return trim($h);
            }, $columnHeaders);
        $headers = array_flip($columnHeaders);
        array_walk($headers, function (&$v, $k) use ($headers) { $v = $k; });

        return $headers;
    }

    /**
     * @param UploadedFile   $file
     * @param Form           $form
     * @param AdminInterface $admin
     *
     * @return mixed
     */
    public function import(UploadedFile $file, Form $form, AdminInterface $admin)
    {
        $mapping = [];
        foreach ($form as $f) {
            if ($f instanceof SubmitButton) {
                continue;
            }
            $mapping[$f->getName()] = $f->getNormData();
        }

        $reader = $this->getReader($file);

        // Convert
        $columnHeaders = array_map(
            function ($h) {
                if (!empty($h) && \is_string($h)) {
                    $h = utf8_encode($h);
                }

                return trim($h);
            }, $reader->getColumnHeaders());
        // Replace columnsHeader names with entity field name in our $mapping
        $columnHeaders = array_map(function ($h) use ($mapping) {
            $k = array_search($h, (array) $mapping, true);

            return false === $k ? $h : $k;
        }, $columnHeaders);
        $reader->setColumnHeaders($columnHeaders);

        /** @var DoctrineWriter $writer */
        $writer = new $this->doctrineWriterClass($this->em, \get_class($form->getData()));
        if (method_exists($writer, 'setContainer')) {
            $writer->setContainer($this->container);
        }
        $writer->disableTruncate();

        $workflow = new StepAggregator($reader);
        $workflow->addWriter($writer);
        $admin->configureImportSteps($workflow);

        $result = $workflow->process();

        return $result;
    }

    /**
     * Get session.
     *
     * @return
     */
    public function getSession()
    {
        return $this->session;
    }

    /**
     * Set session.
     *
     * @return $this
     */
    public function setSession($session)
    {
        $this->session = $session;

        return $this;
    }

    /**
     * @param UploadedFile $file
     *
     * @return CsvReader|ExcelReader
     */
    private function getReader(UploadedFile $file)
    {
        $pathFile = $file->getRealPath();
        $fileExtension = $file->guessExtension();
        $excelExtensions = ['xls', 'xlsx', 'zip'];

        if (\in_array($fileExtension, $excelExtensions)) {
            $reader = new ExcelReader(new \SplFileObject($pathFile), 0, 0, true);
        } else {
            $reader = new CsvReader(new \SplFileObject($pathFile), ';');
            $reader->setHeaderRowNumber(0, CsvReader::DUPLICATE_HEADERS_INCREMENT);
        }

        return $reader;
    }
}
