<?php

namespace App\Object;

use Charcoal\Attachment\Interfaces\AttachmentAwareInterface;
use Pimple\Container;
use Charcoal\Attachment\Traits\AttachmentAwareTrait;
use Charcoal\Loader\CollectionLoaderAwareTrait;
use Charcoal\Object\Content;
use Charcoal\Object\RoutableInterface;
use Charcoal\Object\RoutableTrait;

/**
 * Class ModelSample
 */
class ModelSample extends Content implements
    RoutableInterface,
    AttachmentAwareInterface
{
    use RoutableTrait;
    use AttachmentAwareTrait;
    use CollectionLoaderAwareTrait;

    /** @var mixed */
    protected $name;

    /**
     * @param  Container $container Pimple DI Container.
     * @return void
     */
    public function setDependencies(Container $container)
    {
        parent::setDependencies($container);
        $this->setCollectionLoader($container['model/collection/loader']);
    }


    /* --- Getters + Setters --- */

    /**
     * @return mixed
     */
    public function name()
    {
        return $this->name;
    }

    /**
     * @param mixed $name Name.
     * @return self
     */
    public function setName($name)
    {
        $this->name = $this->translator()->translation($name);
        return $this;
    }


    /* --- Event Hooks --- */

    /**
     * Pre-Save
     * @return boolean
     */
    public function preSave()
    {
        $this->setSlug($this->generateSlug());
        return parent::preSave();
    }

    /**
     * Pre-Update
     * @param array $properties Properties.
     * @return boolean
     */
    protected function preUpdate(array $properties = null)
    {
        $this->setSlug($this->generateSlug());
        return parent::preUpdate($properties);
    }

    /**
     * Pre-Delete
     * @return boolean
     */
    protected function preDelete()
    {
        $this->deleteObjectRoutes();
        return parent::preDelete();
    }

    /**
     * Post-Save
     * @return boolean
     */
    public function postSave()
    {
        $this->generateObjectRoute($this->getSlug());
        return parent::postSave();
    }

    /**
     * Post-Update
     * @param array $properties Properties.
     * @return boolean
     */
    public function postUpdate(array $properties = null)
    {
        $this->generateObjectRoute($this->getSlug());
        return parent::postUpdate($properties);
    }
}
