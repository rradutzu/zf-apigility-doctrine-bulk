<?php

namespace ZF\Apigility\Doctrine\Bulk\Server\Resource;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Instantiator\InstantiatorInterface;
use \ZF\Apigility\Doctrine\Server\Resource\DoctrineResource;
use ZF\ApiProblem\ApiProblem;

class DoctrineBulkResource extends DoctrineResource
{
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
                $result = parent::create($row);

                if ($result instanceof ApiProblem) {
                    $this->getObjectManager()->getConnection()->rollBack();

                    return $result;
                }
                $return->add($result);
            }
            $this->getObjectManager()->getConnection()->commit();

            return $return;
        } else {
            // this is a post request which creates an entity
            return parent::create($data);
        }
    }
}