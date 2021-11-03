<?php

declare(strict_types=1);

namespace App\Query\CraftCMS;

use App\Service\CraftCMS;
use Strata\Data\Cache\CacheLifetime;
use Strata\Data\Exception\GraphQLQueryException;
use Strata\Data\Mapper\MapArray;
use Strata\Data\Query\GraphQLQuery;
use Strata\Data\Transform\Data\CallableData;
use Symfony\Component\Routing\RouterInterface;

/**
 * Get global navigation
 */
class YouMayAlsoLikeRelatedEntries extends GraphQLQuery
{
    public RouterInterface $router;

    /**
     * Set up query
     *
     * @param RouterInterface $router
     * @param int             $siteId        Site ID to generate global navigation for
     * @param string          $uri
     * @param int             $cacheLifetime Cache lifetime to store HTTP response for, defaults to 24 hours
     *
     * @throws GraphQLQueryException
     */
    public function __construct(
        RouterInterface $router,
        int $siteId,
        string $uri,
        int $cacheLifetime = CacheLifetime::HOUR
    ) {
        $this->router = $router;
        $this
            ->setGraphQLFromFile(__DIR__ . '/graphql/youMayAlsoLikeRelatedEntries.graphql')
            ->addFragmentFromFile(__DIR__. '/graphql/fragments/thumbnailImage.graphql')
            ->setRootPropertyPath('[entry]')

            // Set page URI to retrieve navigation for
            ->addVariable('uri', $uri)

            // Set site ID to retrieve navigation for
            ->addVariable('siteId', $siteId)

            // Cache page response
            ->cache($cacheLifetime)
        ;
    }

    public function getRequiredDataProviderClass(): string
    {
        return CraftCMS::class;
    }

    public function transformImage(?array $entry)
    {
        if (array_key_exists('contentEntry', $entry)) {
            $entry = $entry['contentEntry'][0];
        }

        if (array_key_exists('thumbnailImage', $entry) && count($entry['thumbnailImage']) > 0) {
            if (array_key_exists('thumbnailAltText', $entry)) {
                return array_merge($entry['thumbnailImage'][0], ['alt' => $entry['thumbnailAltText']]);
            }

            return $entry['thumbnailImage'][0];
        }

        return null;
    }

    public function transformUrl(?array $entry): ?string
    {
        if (array_key_exists('contentEntry', $entry)) {
            $entry = $entry['contentEntry'][0];
        } else {
            return $entry['url'];
        }

        switch ($entry['category']) {
            case 'blogPosts':
                return $this->router->generate('app_blog_show', ['year' => $entry['year'], 'slug' => $entry['slug']]);
            case 'newsArticles':
                return $this->router->generate('app_news_show', ['year' => $entry['year'], 'slug' => $entry['slug']]);
            case 'pressReleases':
                return $this->router->generate(
                    'app_pressreleases_show',
                    ['year' => $entry['year'], 'slug' => $entry['slug']]
                );
            case 'ecosystems':
                return $this->router->generate('app_ecosystem_show', ['slug' => $entry['slug']]);
            case 'events':
                switch ($entry['typeHandle']) {
                    case 'default':
                        return $this->router->generate('app_events_show', [
                            'year' => $entry['year'],
                            'slug' => $entry['slug']
                        ]);
                    case 'external':
                        return $entry['urlLink'];
                    case 'entryContentIsACraftPage':
                        return $this->router->generate('app_default_index', ['route' => $entry['page']['uri']]);
                }
                return null;
            default:
                return $this->router->generate('app_default_index', ['route' => $entry['uri']]);
        }
    }

    public function getMapping()
    {
        return [
            '[title]' => '[youMayAlsoLikeTitle]',
            '[text]'  => '[youMayAlsoLikeSectionIntroduction]',
            '[links]' => new MapArray(
                '[youMayAlsoLikeRelatedEntries]',
                [
                    '[title]'    => ['[title]', '[contentEntry][0][title]'],
                    '[url]'      => new CallableData([$this, 'transformUrl']),
                    '[category]' => ['[category]', '[contentEntry][0][category]'],
                    '[text]'     => ['[text]', '[contentEntry][0][text]'],
                    '[img]'      => new CallableData([$this, 'transformImage'])
                ]
            )
        ];
    }
}
