<?php

/*
 * This file is part of the crassula/google-cloud-error-reporting-bundle package.
 *
 * (c) Crassula <hello@crassula.io>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Crassula\Bundle\GoogleCloudErrorReportingBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('crassula_google_cloud_error_reporting');

        $rootNode
            ->children()
                ->booleanNode('enabled')
                    ->info('Enables error reporting.')
                    ->defaultFalse()
                ->end()
                ->scalarNode('project_id')
                    ->info('Google Cloud Platform project ID.')
                    ->isRequired()
                    ->validate()
                        ->ifTrue(function ($v) {
                            return !(is_null($v) || is_string($v));
                        })
                        ->thenInvalid('Project ID must be a string')
                    ->end()
                ->end()
                ->scalarNode('service')
                    ->info('Name of app/service to group errors by.')
                    ->isRequired()
                ->end()
                ->booleanNode('use_listeners')
                    ->info('Enable automatic error reporting by using event listeners.')
                    ->defaultTrue()
                ->end()
                ->arrayNode('client_options')
                    ->info('Error reporting client connection options.')
                    ->isRequired()
                    ->ignoreExtraKeys()
                    ->children()
                        ->scalarNode('serviceAddress')
                            ->info('The address of the API remote host.')
                            ->example(['example.googleapis.com', 'example.googleapis.com:443'])
                        ->end()
                        ->booleanNode('disableRetries')
                            ->info('Determines whether or not retries defined by the client configuration should be disabled.')
                            ->defaultFalse()
                        ->end()
                        ->variableNode('clientConfig')
                            ->info('Client method configuration, including retry settings. This option can be either a path to a JSON file, or a PHP array containing the decoded JSON data. By default this settings points to the default client config file, which is provided in the resources folder.')
                            ->validate()
                                ->ifTrue(function ($v) {
                                    return !(is_null($v) || is_string($v) || is_array($v));
                                })
                                ->thenInvalid('"clientConfig" must be a path to a JSON file, or an array containing the decoded JSON data')
                            ->end()
                        ->end()
                        ->variableNode('credentials')
                            ->info('The credentials to be used by the client to authorize API calls. This option accepts either a path to a credentials file, or a decoded credentials file as array.')
                            ->isRequired()
                            ->validate()
                                ->ifTrue(function ($v) {
                                    return !(is_null($v) || is_string($v) || is_array($v));
                                })
                                ->thenInvalid('"credentials" must be a path to a credentials file, or a decoded credentials file as array')
                            ->end()
                        ->end()
                        ->variableNode('credentialsConfig')
                            ->info('Options used to configure credentials, including auth token caching, for the client. For a full list of supporting configuration options, see \Google\ApiCore\CredentialsWrapper::build.')
                        ->end()
                        ->enumNode('transport')
                            ->values(['rest', 'grpc', 'grpc-fallback'])
                            ->defaultValue('grpc')
                        ->end()
                        ->arrayNode('transportConfig')
                            ->info('Configuration options that will be used to construct the transport. Options for each supported transport type should be passed in a key for that transport.')
                            ->children()
                                ->arrayNode('rest')->end()
                                ->arrayNode('grpc')->end()
                                ->arrayNode('grpc-fallback')->end()
                            ->end()
                        ->end()
                        ->scalarNode('versionFile')
                            ->info('The path to a file which contains the current version of the client.')
                        ->end()
                        ->scalarNode('descriptorsConfigPath')
                            ->info('The path to a descriptor configuration file.')
                        ->end()
                        ->scalarNode('serviceName')
                            ->info('The name of the service.')
                        ->end()
                        ->scalarNode('libName')
                            ->info('The name of the client application.')
                        ->end()
                        ->scalarNode('libVersion')
                            ->info('The version of the client application.')
                        ->end()
                        ->scalarNode('gapicVersion')
                            ->info('The code generator version of the GAPIC library.')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
