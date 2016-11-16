<?php

namespace Greabock\NodeBuilder\Doctrine;

use Doctrine\ORM\EntityManager;
use Greabock\NodeBuilder\Support\Contracts\NodeResolverInterface;

class Resolver implements NodeResolverInterface
{
    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var callable|null
     */
    protected $factory;

    /**
     * Resolver constructor.
     *
     * @param EntityManager $em
     * @param callable|null $factory
     */
    public function __construct(EntityManager $em, callable $factory = null)
    {
        $this->em = $em;
        $this->factory = $factory;
    }

    /**
     * Возвращает Entity.
     *
     * @param       $type
     * @param array $data
     *
     * @return null|object
     */
    public function resolve($type, array $data)
    {
        // Получаем список имен полей первичного ключа
        $pkFieldNames = $this->em->getClassMetadata($type)->getIdentifierFieldNames();

        $criteria = [];

        // Собираем критерий поиска
        foreach ($pkFieldNames as $fieldName) {
            if (array_key_exists($fieldName, $data)) {
                $criteria[$fieldName] = $data[$fieldName];
            }
        }

        // Если количество полей в критерии поиска
        // совпадает с количеством полей первичного ключа -
        // вытаскиваем сущность из бд и возвращаем ее.
        if (count($criteria) == count($pkFieldNames)) {

            return $this->em->find($type, $criteria);
        }

        // Если определена ксатомная фабрика,
        // то возвращаем результат работы фабрики.
        if ($this->factory) {

            return ($this->factory)($type);
        }

        // Пытаемся просто создать модель (сущность).
        return new $type;
    }
}