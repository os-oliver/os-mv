<?php

namespace OsOliver\OpenSearchMatViewBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use OsOliver\OpenSearchMatViewBundle\DependencyInjection\OpenSearchMatViewBundleExtension;
/**
 * Class OpenSearchMatViewBundle.
 */
class OpenSearchMatViewBundle extends Bundle
{
    /**
     * @param ContainerBuilder $containerBuilder
     */
    public function build(ContainerBuilder $containerBuilder)
    {
        parent::build($containerBuilder);
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->extension) {
            return new OpenSearchMatViewBundleExtension();
        }

        return $this->extension;
    }
}
