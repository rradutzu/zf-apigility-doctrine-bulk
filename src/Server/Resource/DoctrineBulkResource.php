<?php

namespace ZF\Apigility\Doctrine\Bulk\Server\Resource;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Instantiator\InstantiatorInterface;
use ZF\Apigility\Doctrine\Server\Event\DoctrineResourceEvent;
use \ZF\Apigility\Doctrine\Server\Resource\DoctrineResource;
use ZF\ApiProblem\ApiProblem;
use ZF\Hal\Plugin\Hal;

class DoctrineBulkResource extends DoctrineResource
{
    const EVENT_POST_FLUSH = 'doctrine.apigility.post-flush';

    /** @var null|InstantiatorInterface  */
    private $entityFactory;

    /**
     * @param InstantiatorInterface|null $entityFactory
     */
    public function __construct(InstantiatorInterface $entityFactory = null)
    {
        $this->entityFactory = $entityFactory;
        parent::__construct($entityFactory);
    }

    /**
     * Replace a collection or members of a collection
     *
     * @param mixed $data
     * @return ApiProblem|mixed
     */
    public function create($data)
    {
        $data = (array) $data;
        if (array_values($data) === $data) {
            // this is a POST request which creates a collection
            $return = new ArrayCollection();

            $this->getObjectManager()->getConnection()->beginTransaction();
            foreach ($data as $row) {
                $row = (array)$row;
                // execute create
                $entity = parent::create($row);

                if ($entity instanceof ApiProblem) {
                    $this->getObjectManager()->getConnection()->rollBack();

                    return $entity;
                }

                $results = $this->triggerDoctrineEvent(self::EVENT_POST_FLUSH, $entity, $data);
                if ($results->last() instanceof ApiProblem) {
                    return $results->last();
                }

                $return->add($entity);
            }
            $this->getObjectManager()->getConnection()->commit();

            $halPlugin = new Hal();
            $halCollection = $halPlugin->createCollection($return, $this->getEvent()->getRouteMatch()->getMatchedRouteName());

            return $halCollection;
        } else {
            $this->getObjectManager()->getConnection()->beginTransaction();

            // this is a post request which creates an entity
            $entity = parent::create($data);

            if ($entity instanceof ApiProblem) {
                $this->getObjectManager()->getConnection()->rollBack();

                return $entity;
            }

            $results = $this->triggerDoctrineEvent(self::EVENT_POST_FLUSH, $entity, $data);
            if ($results->last() instanceof ApiProblem) {
                $this->getObjectManager()->getConnection()->rollback();

                return $results->last();
            }

            $this->getObjectManager()->getConnection()->commit();

            return $entity;
        }
    }
}