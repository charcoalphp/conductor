<?php

namespace App\Object;

use Charcoal\Attachment\Interfaces\AttachmentAwareInterface;
use Pimple\Container;
use Charcoal\Attachment\Traits\AttachmentAwareTrait;
use Charcoal\Loader\CollectionLoaderAwareTrait;
use Charcoal\Object\Content;

/**
 * Class ModelSample
 */
class ModelSample extends Content implements
    AttachmentAwareInterface
{
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
}
