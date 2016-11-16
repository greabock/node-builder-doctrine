<?php

namespace Greabock\NodeBuilder\Doctrine;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Greabock\NodeBuilder\Doctrine\Annotations\Node;
use Greabock\NodeBuilder\Field;
use Greabock\NodeBuilder\Relation;
use Doctrine\ORM\Mapping\ClassMetadata;
use Greabock\NodeBuilder\Support\Contracts\NodeKnowledgeInterface;

/**
 * Class Knowledge
 * ============================================================================
 * Этот "класс-знание" позволяет получать сведения о сущностях,
 * на которые билдер будет накладывать array-карту.
 * Конкретно эта реализация работает с Doctrine.
 *
 * @package Greabock\NodeBuilder\Doctrine
 */
class Knowledge implements NodeKnowledgeInterface
{
    const DEFAULT_SETTER_PREFIX = 'set';

    protected static $studlyCache = [];

    protected $strategy = Knowledge::STRATEGY_METHOD;

    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var AnnotationReader
     */
    protected $reader;

    /**
     * Knowledge constructor.
     * ============================================================================
     * Для работы этого класса понадобится EntityManager -
     * через него мы будем получать объект метаданых класса,
     * чтобы извлечь информацию его устройстве.
     * Так же нам пригодится доктриновский "читальщик" аннотаций -
     * с его помощью мы будем читать кастомные аннотации на классах сущностей.
     *
     *
     * @param EntityManager    $em
     * @param AnnotationReader $reader
     */
    public function __construct(EntityManager $em, AnnotationReader $reader)
    {
        $this->em = $em;
        $this->reader = $reader;
    }

    /**
     * Knowledge::compareFields
     * ============================================================================
     * Это единственный публичный метод, ради которого все и затевается.
     * Здесь мы сопоставляем полям данных, карты их свойств и отношений.
     *
     * @param string $type  Имя класса сущности, на которую будут мапиться даныые.
     *                      В данном случае, это должен быть валидный FQCN.
     * @param array  $data  Массив данных, которые необходимо размапить.
     *
     * @return array|Relation[]|Field[]
     */
    public function compareFields(string $type, array $data):array
    {
        $meta = $this->em->getClassMetadata($type);

        return $this->mapRelations($data, $meta,
            $this->mapFields($data, $meta)
        );
    }

    protected function mapFields($data, ClassMetadata $meta, $map = [])
    {
        $fields = $meta->getFieldNames();

        foreach ($fields as $field) {

            if (!array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field];

            $node = $this->reader->getPropertyAnnotation(
                    $meta->getReflectionProperty($field), Node::class
                ) ?? $this->getDefaultNode();

            $node->fieldName = $field;
            $this->defineSetter($node);

            $map[] = new Field($value, $node);
        }

        return $map;
    }

    protected function mapRelations($data, ClassMetadata $meta, $map = [])
    {
        $relations = $meta->getAssociationNames();

        foreach ($relations as $relation) {

            if (!array_key_exists($relation, $data)) {
                continue;
            }

            $value = $data[$relation];

            /** @var Node $node */
            $node = $this->reader->getPropertyAnnotation(
                    $meta->getReflectionProperty($relation), Node::class
                ) ?? $this->getDefaultNode();

            $node->fieldName = $relation;
            $this->defineSetter($node);

            $mapping = $meta->getAssociationMapping($relation);

            $type = $mapping['targetEntity'];

            $cast = $this->isToMany($mapping['type']) ? Relation::CAST_TO_MANY : Relation::CAST_TO_ONE;

            $map[] = new Relation($value, $node, $type, $cast);
        }

        return $map;
    }

    protected function isToMany($type)
    {
        return $type & (ClassMetadataInfo::ONE_TO_MANY | ClassMetadataInfo::MANY_TO_MANY);
    }

    private function defineSetter(Node $node)
    {
        switch ($this->strategy) {
            case static::STRATEGY_DIRECT:
                $this->defineDirectSetter($node);
                break;
            case static::STRATEGY_METHOD:
                $this->defineMethodSetter($node);
                break;
            case static::STRATEGY_REFLECTION:
                $this->defineReflectionSetter($node);
                break;
            default:
                throw new \RuntimeException('Unknown strategy');
        }
    }

    public function defineDirectSetter(Node $node)
    {
        $name = $node->getFieldName();
        $node->setter = function ($entity, $value) use ($name) {
            $entity->{$name} = $value;
        };
    }

    public function defineMethodSetter(Node $node)
    {
        $setter = $node->setter ?? $this->getSetter($node->getFieldName());
        $node->setter = function ($entity, $value) use ($setter) {
            $entity->{$setter}($value);
        };
    }

    public function defineReflectionSetter(Node $node)
    {
        $name = $node->getFieldName();
        $node->setter = function ($entity, $value) use ($name) {
            $reflection = new \ReflectionProperty($entity, $name);
            $reflection->setAccessible(true);
            $reflection->setValue($entity, $value);
            $reflection->setAccessible(false);
        };
    }

    protected function getSetter($field)
    {

        return static::DEFAULT_SETTER_PREFIX . $this->studly($field);
    }

    protected function getDefaultNode()
    {
        return new Node([]);
    }

    /**
     *
     * @param  string $value
     *
     * @return string
     */
    protected function studly($value)
    {
        $key = $value;

        if (isset(static::$studlyCache[$key])) {
            return static::$studlyCache[$key];
        }

        $value = ucwords(str_replace(['-', '_'], ' ', $value));

        return static::$studlyCache[$key] = str_replace(' ', '', $value);
    }

}