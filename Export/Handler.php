<?php

namespace Sigmapix\Sonata\ImportBundle\Export;

use Sonata\Exporter\Source\SourceIteratorInterface;
use Sonata\Exporter\Writer\WriterInterface;

class Handler
{
    /**
     * @var SourceIteratorInterface
     */
    protected $source;

    /**
     * @var WriterInterface
     */
    protected $writer;

    /**
     * @var array
     */
    protected $defaultHeaders;

    /**
     * @param SourceIteratorInterface $source
     * @param WriterInterface         $writer
     */
    public function __construct(SourceIteratorInterface $source, WriterInterface $writer, array $defaultHeaders = [])
    {
        $this->source = $source;
        $this->writer = $writer;
        $this->defaultHeaders = $defaultHeaders;
    }

    public function export()
    {
        $exportData = [];
        foreach ($this->source as $data) {
            $exportData[] = $data;
        }

        $this->writer->open();

        if (empty($exportData) && !empty($this->defaultHeaders)) {
            $this->writer->write($this->defaultHeaders);
        } else {
            foreach ($exportData as $data) {
                $this->writer->write($data);
            }
        }

        $this->writer->close();
    }

    /**
     * @param SourceIteratorInterface $source
     * @param WriterInterface         $writer
     *
     * @return Handler
     */
    public static function create(SourceIteratorInterface $source, WriterInterface $writer, array $defaultHeaders = [])
    {
        return new self($source, $writer, $defaultHeaders);
    }
}
