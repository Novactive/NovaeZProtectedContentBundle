<?php
/**
 * @copyright Novactive
 * Date: 3/16/18
 */

namespace MCC\Bundle\PrivateContentAccessBundle;

use MCC\Bundle\PrivateContentAccessBundle\DependencyInjection\MCCPrivateContentAccessBundleExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class MCCPrivateContentAccessBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function getContainerExtension()
    {
        if (!isset($this->extension)) {
            $this->extension = new MCCPrivateContentAccessBundleExtension(__DIR__.'/Resources/config');
        }

        return $this->extension;
    }

    /**
     * Builds the bundle.
     *
     * It is only ever called once when the cache is empty.
     *
     * This method can be overridden to register compilation passes,
     * other extensions, ...
     *
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new ContentHelperRegistryPass());
        $container->addCompilerPass(new LocationHelperRegistryPass());
    }
}
