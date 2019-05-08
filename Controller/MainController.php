<?php

namespace Naoned\OaiPmhServerBundle\Controller;

use Doctrine\Common\Cache\CacheProvider;
use Naoned\OaiPmhServerBundle\DataProvider\DataProviderInterface;
use Naoned\OaiPmhServerBundle\Exception\BadVerbException;
use Naoned\OaiPmhServerBundle\Exception\IdDoesNotExistException;
use Naoned\OaiPmhServerBundle\Exception\NoRecordsMatchException;
use Naoned\OaiPmhServerBundle\Exception\NoSetHierarchyException;
use Naoned\OaiPmhServerBundle\Exception\OaiPmhServerException;
use Naoned\OaiPmhServerBundle\OaiPmh\OaiPmhRuler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MainController extends AbstractController
{
    private $availableVerbs = array(
        'GetRecord',
        'Identify',
        'ListIdentifiers',
        'ListMetadataFormats',
        'ListRecords',
        'ListSets',
    );

    /**
     * @var CacheProvider
     */
    private $cache;

    private $queryParams = array();

    /**
     * @var OaiPmhRuler
     */
    private $ruler;

    public function __construct(CacheProvider $cache, OaiPmhRuler $ruler)
    {
        $this->cache = $cache;
        $this->ruler = $ruler;
    }

    public function indexAction(Request $request)
    {
        try {
            $this->ruler->checkParamsUnicity($request->getQueryString());

            $this->allArgs = $this->getAllArguments($request);
            if (!array_key_exists('verb', $this->allArgs)) {
                throw new BadVerbException('The verb argument is missing');
            }
            $verb = $this->allArgs['verb'];
            if (!in_array($verb, $this->availableVerbs)) {
                throw new BadVerbException('Value of the verb argument is not a legal OAI-PMH verb.');
            }
            $methodName = $verb.'Verb';

            return $this->$methodName($request);
        } catch (\Exception $e) {
            if ($e instanceof OaiPmhServerException) {
                $reflect = new \ReflectionClass($e);
                // remove «Exception» at end of class namespace
                $code = substr($reflect->getShortName(), 0, -9);
                // lowercase first char
                $code[0] = strtolower(substr($code, 0, 1));
            } elseif ($e instanceof NotFoundHttpException) {
                $code = 'notFoundError';
            } else {
                $code = 'unknownError';
            }

            return $this->error($code, $e->getMessage());
        }
    }

    private function getAllArguments(Request $request)
    {
        return array_merge(
            $request->query->all(),
            $request->request->all()
        );
    }

    private function error($code, $message = '')
    {
        if (!$message) {
            $message = 'Unknown error';
        }

        return $this->render(
            '@NaonedOaiPmhServer/error.xml.twig',
            $viewParams = array(
                'code' => $code,
                'message' => $message,
                'queryParams' => $this->queryParams,
            )
        );
    }

    private function identifyVerb(Request $request)
    {
        $dataProvider = $this->getDataProvider();
        $this->queryParams = $this->ruler->retrieveAndCheckArguments(
            $this->getAllArguments($request)
        );

        return $this->render(
            '@NaonedOaiPmhServer/identify.xml.twig',
            array(
                'dataProvider' => $dataProvider,
                'queryParams' => $this->queryParams,
            )
        );
    }

    private function getDataProvider()
    {
        $service = $this->container->getParameter('naoned.oaipmh_server.data_provider_service_name');
        $dataProvider = $this->get($service);
        if (!$dataProvider instanceof DataProviderInterface) {
            throw new \Exception(sprintf("Class of service %s must implement %s", $service, 'DataProviderInterface'));
        }

        return $dataProvider;
    }

    private function getRecordVerb(Request $request)
    {
        $dataProvider = $this->getDataProvider();
        $this->queryParams = $this->ruler->retrieveAndCheckArguments(
            $this->getAllArguments($request),
            array(
                'metadataPrefix',
                'identifier',
            )
        );
        $this->ruler->checkMetadataPrefix($this->queryParams);
        $record = $this->retrieveRecord($this->queryParams['identifier']);

        return $this->render(
            '@NaonedOaiPmhServer/getRecord.xml.twig',
            array(
                'record' => $record,
                'queryParams' => $this->queryParams,
                'metadataPrefix' => $this->queryParams['metadataPrefix'],
            )
        );
    }

    private function retrieveRecord($id)
    {
        // Extract relevant identifier part
        $parts = explode(':', $id);
        $id = end($parts);

        $dataProvider = $this->getDataProvider();
        $record = $dataProvider->getRecord($id);
        if (!$record) {
            throw new idDoesNotExistException();
        }

        return $record;
    }

    private function listRecordsVerb(Request $request)
    {
        return $this->render(
            '@NaonedOaiPmhServer/listRecords.xml.twig',
            $this->listCommon($request)
        );
    }

    private function listCommon(Request $request)
    {
        $this->queryParams = $this->ruler->retrieveAndCheckArguments(
            $this->getAllArguments($request),
            array('metadataPrefix'),
            array('from', 'until', 'set'),
            array('resumptionToken')
        );
        if (!array_key_exists('resumptionToken', $this->queryParams)) {
            $this->ruler->checkMetadataPrefix($this->queryParams);
        }

        $dataProvider = $this->getDataProvider();
        $searchParams = $this->ruler->getSearchParams(
            $this->queryParams,
            $this->cache
        );
        if (isset($searchParams['set']) && !$dataProvider->checkSupportSets()) {
            throw new NoSetHierarchyException();
        }
        $from = isset($searchParams['from']) ? $this->ruler->checkGranularity($searchParams['from']) : null;
        $until = isset($searchParams['until']) ? $this->ruler->checkGranularity($searchParams['until']) : null;
        $records = $dataProvider->getRecords(
            isset($searchParams['set']) ? $searchParams['set'] : null,
            $from,
            $until
        );
        if (!\is_iterable($records)) {
            throw new \Exception('Implementation error: Records must be an array or an arrayObject');
        }
        if (!count($records)) {
            throw new noRecordsMatchException();
        }
        $resumption = $this->ruler->getResumption(
            $records,
            $searchParams,
            $this->cache
        );

        return array(
            'resumption' => $resumption,
            'metadataPrefix' => $searchParams['metadataPrefix'],
            'queryParams' => $this->queryParams,
        );
    }

    private function listIdentifiersVerb(Request $request)
    {
        return $this->render(
            '@NaonedOaiPmhServer/listIdentifiers.xml.twig',
            $this->listCommon($request)
        );
    }

    private function listMetadataFormatsVerb(Request $request)
    {
        $this->queryParams = $this->ruler->retrieveAndCheckArguments(
            $this->getAllArguments($request),
            array(),
            array('identifier')
        );
        // This is just for checking the record exists
        if (array_key_exists('identifier', $this->queryParams)) {
            $record = $this->retrieveRecord($this->queryParams['identifier']);
        }

        return $this->render(
            '@NaonedOaiPmhServer/listMetadataFormats.xml.twig',
            array(
                'availableMetadata' => $this->ruler->getAvailableMetadata(),
                'queryParams' => $this->queryParams,
            )
        );
    }

    private function listSetsVerb(Request $request)
    {
        $this->queryParams = $this->ruler->retrieveAndCheckArguments(
            $this->getAllArguments($request),
            array(),
            array(),
            array('resumptionToken')
        );
        $dataProvider = $this->getDataProvider();
        if (!$dataProvider->checkSupportSets()) {
            throw new NoSetHierarchyException();
        }
        $sets = $dataProvider->getSets();
        if ($sets !== null && (!(is_array($sets) || ($sets instanceof \ArrayObject)))) {
            throw new \Exception('Implementation error: Sets must be an array or an arrayObject');
        }
        $searchParams = $this->ruler->getSearchParams(
            $this->queryParams,
            $this->cache
        );
        $resumption = $this->ruler->getResumption(
            $sets,
            $searchParams,
            $this->cache
        );

        return $this->render(
            '@NaonedOaiPmhServer/listSets.xml.twig',
            array(
                'query' => $this->queryParams,
                'resumption' => $resumption,
                'searchParams' => $searchParams,
                'queryParams' => $this->queryParams,
            )
        );
    }
}
