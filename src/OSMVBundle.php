<?php

namespace OsOliver\OSMVBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
/**
 * Class OSMVBundle.
 */
class OSMVBundle extends Bundle
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
            return new OSMVBundleExtension();
        }

        return $this->extension;
    }
}
