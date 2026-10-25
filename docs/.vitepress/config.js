import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Solo Application',
  description: 'PSR-15 application kernel: container, middleware pipeline and routing with solophp/router',
  base: '/Application/',

  head: [
    ['link', { rel: 'icon', type: 'image/svg+xml', href: '/Application/logo.svg' }],
    ['meta', { name: 'theme-color', content: '#ec4899' }],
    ['meta', { property: 'og:type', content: 'website' }],
    ['meta', { property: 'og:title', content: 'Solo Application' }],
    ['meta', { property: 'og:description', content: 'PSR-15 application kernel: container, middleware pipeline and routing with solophp/router' }],
  ],

  themeConfig: {
    logo: '/logo.svg',

    nav: [
      { text: 'Guide', link: '/guide/installation' },
      { text: 'Features', link: '/features/routing' },
      { text: 'API', link: '/api/application' },
      { text: 'v4.0.1', link: '/guide/upgrading' },
      {
        text: 'Links',
        items: [
          { text: 'GitHub', link: 'https://github.com/solophp/application' },
          { text: 'Packagist', link: 'https://packagist.org/packages/solophp/application' },
          { text: 'Solo Router', link: 'https://solophp.github.io/Router/' },
          { text: 'SoloPHP', link: 'https://github.com/solophp' }
        ]
      }
    ],

    sidebar: [
      {
        text: 'Getting Started',
        items: [
          { text: 'Installation', link: '/guide/installation' },
          { text: 'Quick Start', link: '/guide/quick-start' },
          { text: 'Lifecycle', link: '/guide/lifecycle' },
          { text: 'Configuration', link: '/guide/configuration' },
          { text: 'Upgrading from 3.x', link: '/guide/upgrading' }
        ]
      },
      {
        text: 'Features',
        items: [
          { text: 'Routing', link: '/features/routing' },
          { text: 'Route Cache', link: '/features/route-cache' },
          { text: 'Routing Errors', link: '/features/routing-errors' },
          { text: 'Middleware', link: '/features/middleware' },
          { text: 'Service Providers', link: '/features/service-providers' },
          { text: 'Route Handlers', link: '/features/handlers' },
          { text: 'Workers and Emitting', link: '/features/workers' },
          { text: 'Custom Container', link: '/features/container' },
          { text: 'Testing', link: '/features/testing' }
        ]
      },
      {
        text: 'API Reference',
        items: [
          { text: 'Application', link: '/api/application' },
          { text: 'Config', link: '/api/config' },
          { text: 'MiddlewarePipeline', link: '/api/middleware-pipeline' },
          { text: 'RoutingMiddleware', link: '/api/routing-middleware' },
          { text: 'RoutingErrorMiddleware', link: '/api/routing-error-middleware' },
          { text: 'RouteDispatcher', link: '/api/route-dispatcher' },
          { text: 'RequestAttribute', link: '/api/request-attribute' },
          { text: 'Exceptions', link: '/api/exceptions' }
        ]
      }
    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com/solophp/application' }
    ],

    footer: {
      message: 'Released under the MIT License.',
      copyright: `Copyright © 2024-${new Date().getFullYear()} SoloPHP`
    },

    search: {
      provider: 'local'
    },

    editLink: {
      pattern: 'https://github.com/solophp/application/edit/main/docs/:path',
      text: 'Edit this page on GitHub'
    }
  }
})
