<?php

namespace ZF\Apigility\Doctrine\Bulk\Server\Resource;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Instantiator\InstantiatorInterface;
use \ZF\Apigility\Doctrine\Server\Resource\DoctrineResource;
use ZF\ApiProblem\ApiProblem;
use ZF\Hal\Plugin\Hal;

class DoctrineBulkResource extends DoctrineResource
{
    const EVENT_POST_FLUSH = 'doctrine.apigility.post-flush';
    const EVENT_PRE_COMMIT = 'doctrine.apigility.pre-commit';
    const EVENT_POST_COMMIT = 'doctrine.apigility.post-commit';

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
        //convert data to array
        $data = (array) $data;
        //if request is a collection of entities
        if (array_values($data) === $data) {
            // this is a POST request which creates a collection
            $return = new ArrayCollection();
            //start transaction
            $this->getObjectManager()->getConnection()->beginTransaction();
            //loop through collection
            foreach ($data as $row) {
                //convert each entity data to array
                $row = (array)$row;
                // execute create
                $entity = parent::create($row);
                //if ApiProblem is returned instead of data entity rollback and return the ApiProblem object
                if ($entity instanceof ApiProblem) {
                    $this->getObjectManager()->getConnection()->rollBack();

                    return $entity;
                }
                //trigger EVENT_POST_FLUSH event for created entity
                $results = $this->triggerDoctrineEvent(self::EVENT_POST_FLUSH, $entity, $data);
                //if ApiProblem is returned instead of data entity as a result of triggering the event show the error
                if ($results->last() instanceof ApiProblem) {
                    $this->getObjectManager()->getConnection()->rollback();
                    return $results->last();
                }
                //entity has been created correctly and attached events has been correctly executed
                //so entity is added to the return ArrayCollection
                $return->add($entity);
            }
            //after every entity has been saved we are executing EVENT_PRE_COMMIT for each entity
            foreach ($return as $createdEntity) {
                $results = $this->triggerDoctrineEvent(self::EVENT_PRE_COMMIT, $createdEntity, $data);
                if ($results->last() instanceof ApiProblem) {
                    $this->getObjectManager()->getConnection()->rollback();
                    return $results->last();
                }
            }
            //committing the database transaction
            $this->getObjectManager()->getConnection()->commit();
            //after every entity has been correctly stored to database we are executing EVENT_POST_COMMIT for each entity
            foreach ($return as $createEntity) {
                $results = $this->triggerDoctrineEvent(self::EVENT_POST_COMMIT, $createEntity, $data);
                if ($results->last() instanceof ApiProblem) {
                    return $results->last();
                }
            }

            $halPlugin = new Hal();
            $halCollection = $halPlugin->createCollection($return, $this->getEvent()->getRouteMatch()->getMatchedRouteName());

            return $halCollection;
        } else {
            //if request is only one entity
            $this->getObjectManager()->getConnection()->beginTransaction();
            // this is a post request which creates an entity
            $entity = parent::create($data);
            //if ApiProblem is returned instead of data entity rollback and return the ApiProblem object
            if ($entity instanceof ApiProblem) {
                $this->getObjectManager()->getConnection()->rollBack();

                return $entity;
            }
            //trigger EVENT_POST_FLUSH event for created entity
            $results = $this->triggerDoctrineEvent(self::EVENT_POST_FLUSH, $entity, $data);
            if ($results->last() instanceof ApiProblem) {
                $this->getObjectManager()->getConnection()->rollback();

                return $results->last();
            }
            //committing the database transaction
            $this->getObjectManager()->getConnection()->commit();
            //after entity has been correctly stored to database we are executing EVENT_POST_COMMIT for that entity
            $results = $this->triggerDoctrineEvent(self::EVENT_POST_COMMIT, $entity, $data);
            if ($results->last() instanceof ApiProblem) {
                return $results->last();
            }

            return $entity;
        }
    }
}