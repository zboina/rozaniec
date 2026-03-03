<?php

namespace Rozaniec\RozaniecBundle;

use Rozaniec\RozaniecBundle\Model\RozaniecUserInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class RozaniecBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('user_class')
                    ->defaultValue('App\\Entity\\User')
                    ->info('FQCN klasy User (np. App\\Entity\\User)')
                ->end()
                ->scalarNode('base_template')
                    ->defaultValue('@Rozaniec/base_rozaniec.html.twig')
                    ->info('Bazowy szablon Twig, który rozszerzają szablony bundla')
                ->end()
                ->scalarNode('email_from')
                    ->defaultValue('rozaniec@localhost')
                    ->info('Adres nadawcy emaili (np. rozaniec@twojadomena.pl)')
                ->end()
                ->arrayNode('user_full_name_fields')
                    ->defaultValue(['firstName', 'lastName'])
                    ->scalarPrototype()->end()
                    ->info('Nazwy pól User, z których złożyć pełne imię i nazwisko')
                ->end()
            ->end();
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $configs = $builder->getExtensionConfig('rozaniec');
        $userClass = $configs[0]['user_class'] ?? 'App\\Entity\\User';

        $builder->prependExtensionConfig('doctrine', [
            'orm' => [
                'resolve_target_entities' => [
                    RozaniecUserInterface::class => $userClass,
                ],
                'mappings' => [
                    'RozaniecBundle' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => dirname(__DIR__) . '/src/Entity',
                        'prefix' => 'Rozaniec\\RozaniecBundle\\Entity',
                        'alias' => 'Rozaniec',
                    ],
                ],
            ],
        ]);
    }

    public function loadExtension(
        array $config,
        ContainerConfigurator $container,
        ContainerBuilder $builder,
    ): void {
        $container->import('../config/services.yaml');

        $projectDir = $builder->getParameter('kernel.project_dir');

        // Auto-create routes config in host project if missing
        $routesFile = $projectDir . '/config/routes/rozaniec.yaml';
        if (!file_exists($routesFile)) {
            $dir = \dirname($routesFile);
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
            @file_put_contents($routesFile, <<<'YAML'
rozaniec_routes:
    resource: '@RozaniecBundle/config/routes.yaml'
YAML
            );
        }

        $container->parameters()
            ->set('rozaniec.base_template', $config['base_template'])
            ->set('rozaniec.user_class', $config['user_class'])
            ->set('rozaniec.user_full_name_fields', $config['user_full_name_fields'])
            ->set('rozaniec.email_from', $config['email_from']);
    }
}
