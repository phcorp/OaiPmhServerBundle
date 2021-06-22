<?php

namespace Naoned\OaiPmhServerBundle\Twig;

use Naoned\OaiPmhServerBundle\DataProvider\DataProviderInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class RecordExtension extends AbstractExtension
{
    /**
     * @var DataProviderInterface
     */
    private $dataProvider;

    public function getFunctions()
    {
        return array(
            new TwigFunction('get_record_sets', [$this, 'getRecordSets']),
            new TwigFunction('dublinize_record', [$this, 'dublinizeRecord']),
            new TwigFunction('get_record_id', [$this, 'getRecordId']),
            new TwigFunction('get_record_updated', [$this, 'getRecordUpdated']),
            new TwigFunction('get_thumb', [$this, 'getThumb']),
        );
    }

    public function setDataProvider(DataProviderInterface $dataProvider)
    {
        $this->dataProvider = $dataProvider;
    }

    public function getRecordSets($record)
    {
        return $this->dataProvider->getSetsForRecord($record);
    }

    public function dublinizeRecord($record)
    {
        return $this->dataProvider->dublinizeRecord($record);
    }

    public function getRecordId($record)
    {
        return $this->dataProvider::getRecordId($record);
    }

    public function getRecordUpdated($record)
    {
        return $this->dataProvider::getRecordUpdated($record);
    }

    // for a service we need a name
    public function getName()
    {
        return 'oaipmh_record';
    }
}
