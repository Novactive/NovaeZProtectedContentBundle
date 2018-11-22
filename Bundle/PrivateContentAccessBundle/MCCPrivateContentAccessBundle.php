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
}
