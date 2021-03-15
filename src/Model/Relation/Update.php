<?php

declare(strict_types=1);

namespace Imi\Model\Relation;

use Imi\Bean\Annotation\AnnotationManager;
use Imi\Bean\BeanFactory;
use Imi\Db\Query\Interfaces\IQuery;
use Imi\Event\Event;
use Imi\Model\Annotation\Relation\AutoSave;
use Imi\Model\Annotation\Relation\AutoUpdate;
use Imi\Model\Annotation\Relation\RelationBase;
use Imi\Model\Model;
use Imi\Model\Relation\Struct\ManyToMany;
use Imi\Model\Relation\Struct\OneToMany;
use Imi\Model\Relation\Struct\OneToOne;
use Imi\Model\Relation\Struct\PolymorphicManyToMany;
use Imi\Model\Relation\Struct\PolymorphicOneToMany;
use Imi\Model\Relation\Struct\PolymorphicOneToOne;

class Update
{
    private function __construct()
    {
    }

    /**
     * 处理更新.
     *
     * @param \Imi\Model\Model $model
     * @param string           $propertyName
     * @param RelationBase     $annotation
     *
     * @return void
     */
    public static function parse(Model $model, string $propertyName, RelationBase $annotation): void
    {
        if (!$model->$propertyName)
        {
            return;
        }
        $className = BeanFactory::getObjectClass($model);
        /** @var AutoUpdate|null $autoUpdate */
        $autoUpdate = AnnotationManager::getPropertyAnnotations($className, $propertyName, AutoUpdate::class)[0] ?? null;
        /** @var AutoSave|null $autoSave */
        $autoSave = AnnotationManager::getPropertyAnnotations($className, $propertyName, AutoSave::class)[0] ?? null;

        if ($autoUpdate)
        {
            if (!$autoUpdate->status)
            {
                return;
            }
        }
        elseif (!$autoSave || !$autoSave->status)
        {
            return;
        }

        if ($annotation instanceof \Imi\Model\Annotation\Relation\OneToOne)
        {
            static::parseByOneToOne($model, $propertyName, $annotation);
        }
        elseif ($annotation instanceof \Imi\Model\Annotation\Relation\OneToMany)
        {
            static::parseByOneToMany($model, $propertyName, $annotation);
        }
        elseif ($annotation instanceof \Imi\Model\Annotation\Relation\ManyToMany)
        {
            static::parseByManyToMany($model, $propertyName, $annotation);
        }
        elseif ($annotation instanceof \Imi\Model\Annotation\Relation\PolymorphicOneToOne)
        {
            static::parseByPolymorphicOneToOne($model, $propertyName, $annotation);
        }
        elseif ($annotation instanceof \Imi\Model\Annotation\Relation\PolymorphicOneToMany)
        {
            static::parseByPolymorphicOneToMany($model, $propertyName, $annotation);
        }
        // @phpstan-ignore-next-line
        elseif ($annotation instanceof \Imi\Model\Annotation\Relation\PolymorphicManyToMany)
        {
            static::parseByPolymorphicManyToMany($model, $propertyName, $annotation);
        }
    }

    /**
     * 处理一对一更新.
     *
     * @param \Imi\Model\Model                        $model
     * @param string                                  $propertyName
     * @param \Imi\Model\Annotation\Relation\OneToOne $annotation
     *
     * @return void
     */
    public static function parseByOneToOne(Model $model, string $propertyName, \Imi\Model\Annotation\Relation\OneToOne $annotation): void
    {
        $className = BeanFactory::getObjectClass($model);

        $struct = new OneToOne($className, $propertyName, $annotation);
        $leftField = $struct->getLeftField();
        $rightField = $struct->getRightField();
        $eventName = 'IMI.MODEL.RELATION.UPDATE.' . $className . '.' . $propertyName;

        Event::trigger($eventName . '.BEFORE', [
            'model'        => $model,
            'propertyName' => $propertyName,
            'annotation'   => $annotation,
            'struct'       => $struct,
        ]);

        $modelField = $model->$propertyName;
        $modelField->$rightField = $model->$leftField;
        $modelField->update();
        Event::trigger($eventName . '.AFTER', [
            'model'        => $model,
            'propertyName' => $propertyName,
            'annotation'   => $annotation,
            'struct'       => $struct,
        ]);
    }

    /**
     * 处理一对多更新.
     *
     * @param \Imi\Model\Model                         $model
     * @param string                                   $propertyName
     * @param \Imi\Model\Annotation\Relation\OneToMany $annotation
     *
     * @return void
     */
    public static function parseByOneToMany(Model $model, string $propertyName, \Imi\Model\Annotation\Relation\OneToMany $annotation): void
    {
        $className = BeanFactory::getObjectClass($model);

        $struct = new OneToMany($className, $propertyName, $annotation);
        $leftField = $struct->getLeftField();
        $rightField = $struct->getRightField();
        $rightModel = $struct->getRightModel();

        /** @var AutoUpdate|null $autoUpdate */
        $autoUpdate = AnnotationManager::getPropertyAnnotations($className, $propertyName, AutoUpdate::class)[0] ?? null;
        /** @var AutoSave|null $autoSave */
        $autoSave = AnnotationManager::getPropertyAnnotations($className, $propertyName, AutoSave::class)[0] ?? null;
        // 是否删除无关数据
        if ($autoUpdate)
        {
            $orphanRemoval = $autoUpdate->orphanRemoval;
        }
        elseif ($autoSave)
        {
            $orphanRemoval = $autoSave->orphanRemoval;
        }
        else
        {
            $orphanRemoval = false;
        }

        $eventName = 'IMI.MODEL.RELATION.UPDATE.' . $className . '.' . $propertyName;

        Event::trigger($eventName . '.BEFORE', [
            'model'        => $model,
            'propertyName' => $propertyName,
            'annotation'   => $annotation,
            'struct'       => $struct,
        ]);
        $modelLeftValue = $model->$leftField;
        if ($orphanRemoval)
        {
            // 删除无关联数据
            $pks = $rightModel::__getMeta()->getId();
            if (isset($pks[1]))
            {
                throw new \RuntimeException(sprintf('%s can not OneToMany, because has more than 1 primary keys', $rightModel));
            }
            $pk = $pks[0];

            $oldIds = $rightModel::query()->where($rightField, '=', $modelLeftValue)->field($pk)->select()->getColumn();

            $updateIds = [];
            foreach ($model->$propertyName as $row)
            {
                if (null !== $row->$pk)
                {
                    $updateIds[] = $row->$pk;
                }
                $row->$rightField = $modelLeftValue;
                $row->save();
            }

            $deleteIds = array_diff($oldIds, $updateIds);

            if ($deleteIds)
            {
                // 批量删除
                $rightModel::deleteBatch(function (IQuery $query) use ($pk, $deleteIds) {
                    $query->whereIn($pk, $deleteIds);
                });
            }
        }
        else
        {
            // 直接更新
            foreach ($model->$propertyName as $row)
            {
                $row->$rightField = $modelLeftValue;
                $row->save();
            }
        }
        Event::trigger($eventName . '.AFTER', [
            'model'        => $model,
            'propertyName' => $propertyName,
            'annotation'   => $annotation,
            'struct'       => $struct,
        ]);
    }

    /**
     * 处理多对多更新.
     *
     * @param \Imi\Model\Model                          $model
     * @param string                                    $propertyName
     * @param \Imi\Model\Annotation\Relation\ManyToMany $annotation
     *
     * @return void
     */
    public static function parseByManyToMany(Model $model, string $propertyName, \Imi\Model\Annotation\Relation\ManyToMany $annotation): void
    {
        $className = BeanFactory::getObjectClass($model);

        $struct = new ManyToMany($className, $propertyName, $annotation);
        $middleModel = $struct->getMiddleModel();
        $middleLeftField = $struct->getMiddleLeftField();
        $middleRightField = $struct->getMiddleRightField();
        $leftField = $struct->getLeftField();

        /** @var AutoUpdate|null $autoUpdate */
        $autoUpdate = AnnotationManager::getPropertyAnnotations($className, $propertyName, AutoUpdate::class)[0] ?? null;
        /** @var AutoSave|null $autoSave */
        $autoSave = AnnotationManager::getPropertyAnnotations($className, $propertyName, AutoSave::class)[0] ?? null;
        // 是否删除无关数据
        if ($autoUpdate)
        {
            $orphanRemoval = $autoUpdate->orphanRemoval;
        }
        elseif ($autoSave)
        {
            $orphanRemoval = $autoSave->orphanRemoval;
        }
        else
        {
            $orphanRemoval = false;
        }

        $eventName = 'IMI.MODEL.RELATION.UPDATE.' . $className . '.' . $propertyName;

        Event::trigger($eventName . '.BEFORE', [
            'model'        => $model,
            'propertyName' => $propertyName,
            'annotation'   => $annotation,
            'struct'       => $struct,
        ]);
        $modelLeftValue = $model->$leftField;
        if ($orphanRemoval)
        {
            // 删除无关联数据
            $oldRightIds = $middleModel::query()->where($middleLeftField, '=', $modelLeftValue)->field($middleRightField)->select()->getColumn();

            $updateIds = [];
            foreach ($model->$propertyName as $row)
            {
                if (null !== $row->$middleRightField)
                {
                    $updateIds[] = $row->$middleRightField;
                }
                $row->$middleLeftField = $modelLeftValue;
                $row->save();
            }

            $deleteIds = array_diff($oldRightIds, $updateIds);

            if ($deleteIds)
            {
                // 批量删除
                $middleModel::deleteBatch(function (IQuery $query) use ($middleLeftField, $middleRightField, $deleteIds, $modelLeftValue) {
                    $query->where($middleLeftField, '=', $modelLeftValue)->whereIn($middleRightField, $deleteIds);
                });
            }
        }
        else
        {
            // 直接更新
            foreach ($model->$propertyName as $row)
            {
                $row->$middleLeftField = $modelLeftValue;
                $row->save();
            }
        }
        Event::trigger($eventName . '.AFTER', [
            'model'        => $model,
            'propertyName' => $propertyName,
            'annotation'   => $annotation,
            'struct'       => $struct,
        ]);
    }

    /**
     * 模型类（可指定字段）是否包含更新关联关系.
     *
     * @param string      $className
     * @param string|null $propertyName
     *
     * @return bool
     */
    public static function hasUpdateRelation(string $className, ?string $propertyName = null): bool
    {
        $relations = AnnotationManager::getPropertiesAnnotations($className, RelationBase::class);

        if (empty($relations))
        {
            return false;
        }

        if (null === $propertyName)
        {
            foreach ($relations as $name => $annotations)
            {
                /** @var AutoUpdate|null $autoUpdate */
                $autoUpdate = AnnotationManager::getPropertyAnnotations($className, $name, AutoUpdate::class)[0] ?? null;
                /** @var AutoSave|null $autoSave */
                $autoSave = AnnotationManager::getPropertyAnnotations($className, $name, AutoSave::class)[0] ?? null;

                if ($autoUpdate)
                {
                    if (!$autoUpdate->status)
                    {
                        continue;
                    }
                }
                elseif (!$autoSave || !$autoSave->status)
                {
                    continue;
                }
            }
        }
        else
        {
            /** @var AutoUpdate|null $autoUpdate */
            $autoUpdate = AnnotationManager::getPropertyAnnotations($className, $propertyName, AutoUpdate::class)[0] ?? null;
            /** @var AutoSave|null $autoSave */
            $autoSave = AnnotationManager::getPropertyAnnotations($className, $propertyName, AutoSave::class)[0] ?? null;

            if ($autoUpdate)
            {
                if (!$autoUpdate->status)
                {
                    return false;
                }
            }
            elseif (!$autoSave || !$autoSave->status)
            {
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * 处理多态一对一更新.
     *
     * @param \Imi\Model\Model                                   $model
     * @param string                                             $propertyName
     * @param \Imi\Model\Annotation\Relation\PolymorphicOneToOne $annotation
     *
     * @return void
     */
    public static function parseByPolymorphicOneToOne(Model $model, string $propertyName, \Imi\Model\Annotation\Relation\PolymorphicOneToOne $annotation): void
    {
        $className = BeanFactory::getObjectClass($model);

        $struct = new PolymorphicOneToOne($className, $propertyName, $annotation);
        $leftField = $struct->getLeftField();
        $rightField = $struct->getRightField();
        $eventName = 'IMI.MODEL.RELATION.UPDATE.' . $className . '.' . $propertyName;

        Event::trigger($eventName . '.BEFORE', [
            'model'        => $model,
            'propertyName' => $propertyName,
            'annotation'   => $annotation,
            'struct'       => $struct,
        ]);

        $modelField = $model->$propertyName;
        $modelField->$rightField = $model->$leftField;
        $modelField->{$annotation->type} = $annotation->typeValue;
        $modelField->update();
        Event::trigger($eventName . '.AFTER', [
            'model'        => $model,
            'propertyName' => $propertyName,
            'annotation'   => $annotation,
            'struct'       => $struct,
        ]);
    }

    /**
     * 处理多态一对多更新.
     *
     * @param \Imi\Model\Model                                    $model
     * @param string                                              $propertyName
     * @param \Imi\Model\Annotation\Relation\PolymorphicOneToMany $annotation
     *
     * @return void
     */
    public static function parseByPolymorphicOneToMany(Model $model, string $propertyName, \Imi\Model\Annotation\Relation\PolymorphicOneToMany $annotation): void
    {
        $className = BeanFactory::getObjectClass($model);

        $struct = new PolymorphicOneToMany($className, $propertyName, $annotation);
        $leftField = $struct->getLeftField();
        $rightField = $struct->getRightField();
        $rightModel = $struct->getRightModel();

        /** @var AutoUpdate|null $autoUpdate */
        $autoUpdate = AnnotationManager::getPropertyAnnotations($className, $propertyName, AutoUpdate::class)[0] ?? null;
        /** @var AutoSave|null $autoSave */
        $autoSave = AnnotationManager::getPropertyAnnotations($className, $propertyName, AutoSave::class)[0] ?? null;
        // 是否删除无关数据
        if ($autoUpdate)
        {
            $orphanRemoval = $autoUpdate->orphanRemoval;
        }
        elseif ($autoSave)
        {
            $orphanRemoval = $autoSave->orphanRemoval;
        }
        else
        {
            $orphanRemoval = false;
        }

        $eventName = 'IMI.MODEL.RELATION.UPDATE.' . $className . '.' . $propertyName;

        Event::trigger($eventName . '.BEFORE', [
            'model'        => $model,
            'propertyName' => $propertyName,
            'annotation'   => $annotation,
            'struct'       => $struct,
        ]);
        $modelLeftValue = $model->$leftField;
        if ($orphanRemoval)
        {
            // 删除无关联数据
            $pks = $rightModel::__getMeta()->getId();
            if (isset($pks[1]))
            {
                throw new \RuntimeException(sprintf('%s can not OneToMany, because has more than 1 primary keys', $rightModel));
            }
            $pk = $pks[0];

            $oldIds = $rightModel::query()->where($annotation->type, '=', $annotation->typeValue)->where($rightField, '=', $modelLeftValue)->field($pk)->select()->getColumn();

            $updateIds = [];
            foreach ($model->$propertyName as $row)
            {
                if (null !== $row->$pk)
                {
                    $updateIds[] = $row->$pk;
                }
                $row->$rightField = $modelLeftValue;
                $row->{$annotation->type} = $annotation->typeValue;
                $row->save();
            }

            $deleteIds = array_diff($oldIds, $updateIds);

            if ($deleteIds)
            {
                // 批量删除
                $rightModel::deleteBatch(function (IQuery $query) use ($pk, $deleteIds) {
                    $query->whereIn($pk, $deleteIds);
                });
            }
        }
        else
        {
            // 直接更新
            foreach ($model->$propertyName as $row)
            {
                $row->$rightField = $modelLeftValue;
                $row->save();
            }
        }
        Event::trigger($eventName . '.AFTER', [
            'model'        => $model,
            'propertyName' => $propertyName,
            'annotation'   => $annotation,
            'struct'       => $struct,
        ]);
    }

    /**
     * 处理多态多对多更新.
     *
     * @param \Imi\Model\Model                                     $model
     * @param string                                               $propertyName
     * @param \Imi\Model\Annotation\Relation\PolymorphicManyToMany $annotation
     *
     * @return void
     */
    public static function parseByPolymorphicManyToMany(Model $model, string $propertyName, \Imi\Model\Annotation\Relation\PolymorphicManyToMany $annotation): void
    {
        $className = BeanFactory::getObjectClass($model);

        $struct = new PolymorphicManyToMany($className, $propertyName, $annotation);
        $middleModel = $struct->getMiddleModel();
        $middleLeftField = $struct->getMiddleLeftField();
        $middleRightField = $struct->getMiddleRightField();
        $leftField = $struct->getLeftField();

        /** @var AutoUpdate|null $autoUpdate */
        $autoUpdate = AnnotationManager::getPropertyAnnotations($className, $propertyName, AutoUpdate::class)[0] ?? null;
        /** @var AutoSave|null $autoSave */
        $autoSave = AnnotationManager::getPropertyAnnotations($className, $propertyName, AutoSave::class)[0] ?? null;
        // 是否删除无关数据
        if ($autoUpdate)
        {
            $orphanRemoval = $autoUpdate->orphanRemoval;
        }
        elseif ($autoSave)
        {
            $orphanRemoval = $autoSave->orphanRemoval;
        }
        else
        {
            $orphanRemoval = false;
        }

        $eventName = 'IMI.MODEL.RELATION.UPDATE.' . $className . '.' . $propertyName;

        Event::trigger($eventName . '.BEFORE', [
            'model'        => $model,
            'propertyName' => $propertyName,
            'annotation'   => $annotation,
            'struct'       => $struct,
        ]);
        $modelLeftValue = $model->$leftField;
        if ($orphanRemoval)
        {
            // 删除无关联数据
            $oldRightIds = $middleModel::query()->where($annotation->type, '=', $annotation->typeValue)->where($middleLeftField, '=', $modelLeftValue)->field($middleRightField)->select()->getColumn();

            $updateIds = [];
            foreach ($model->$propertyName as $row)
            {
                if (null !== $row->$middleRightField)
                {
                    $updateIds[] = $row->$middleRightField;
                }
                $row->$middleLeftField = $modelLeftValue;
                $row->{$annotation->type} = $annotation->typeValue;
                $row->save();
            }

            $deleteIds = array_diff($oldRightIds, $updateIds);

            if ($deleteIds)
            {
                // 批量删除
                $middleModel::deleteBatch(function (IQuery $query) use ($middleLeftField, $middleRightField, $deleteIds, $annotation, $modelLeftValue) {
                    $query->where($annotation->type, '=', $annotation->typeValue)->where($middleLeftField, '=', $modelLeftValue)->whereIn($middleRightField, $deleteIds);
                });
            }
        }
        else
        {
            // 直接更新
            foreach ($model->$propertyName as $row)
            {
                $row->$middleLeftField = $modelLeftValue;
                $row->{$annotation->type} = $annotation->typeValue;
                $row->save();
            }
        }
        Event::trigger($eventName . '.AFTER', [
            'model'        => $model,
            'propertyName' => $propertyName,
            'annotation'   => $annotation,
            'struct'       => $struct,
        ]);
    }
}
