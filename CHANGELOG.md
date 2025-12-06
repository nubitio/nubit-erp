## [1.7.0](https://github.com/nubitio/efact/compare/v1.6.0...v1.7.0) (2025-12-06)

### 🚀 Features

* add CI and CD pipelines for automated testing and deployment; configure Docker build and push steps ([f29ba1a](https://github.com/nubitio/efact/commit/f29ba1ac1f64ad98fa1248056507a60be7fe073d))
* add Doctrine Fixtures Bundle and create initial AppFixtures class ([5a167c3](https://github.com/nubitio/efact/commit/5a167c3c66e458b7b69a85cd901ae18aa8bcfafe))
* add FillTimeDimensionCommand and related entities for time dimension management; create Summary and TimeDimension entities with repositories ([d1661e4](https://github.com/nubitio/efact/commit/d1661e4d7fb1142f3e039167852014ac40369e54))
* add MenuSeedService for seeding menu items; implement SeedMenuCommand for command line execution ([ea9f4a1](https://github.com/nubitio/efact/commit/ea9f4a173ba6ac71f30a027a3007139f2b01415c))
* add Ubigeo entity and repository; update company context and settings for ubigeo integration; enhance invoice processing with ubigeo data ([26cb234](https://github.com/nubitio/efact/commit/26cb234a97828e80ab6cb3e69a5b228e312cdd8c))
* enhance Dashboard functionality with consolidated overview data; improve styling and structure of dashboard components ([3d0d7f5](https://github.com/nubitio/efact/commit/3d0d7f5de23a4fa78d74764327c72fb4a350da30))
* enhance Dashboard with refund and net sales metrics; add cash register status section; update payment method trend visualization ([e62fd76](https://github.com/nubitio/efact/commit/e62fd760febbbbc925025e70f9c802e05c582877))
* implement initial setup process with validation and API integration ([eb3a0e4](https://github.com/nubitio/efact/commit/eb3a0e4fbc67eaaa94b815c5bcd1fd6afd04c766))
* implement invoice sending command with batch processing and error handling ([cc92a9a](https://github.com/nubitio/efact/commit/cc92a9a66d24096e585d5c36b9a3234158643a30))
* implement password visibility toggle in LoginForm; enhance styling for password toggle button ([6d8b868](https://github.com/nubitio/efact/commit/6d8b868d97e69ac3a95149862c7c66a489140b31))
* implement v1 API versioning; create ApiController and ApiRepository for handling requests; update security and constants for new API structure ([3a29309](https://github.com/nubitio/efact/commit/3a2930902c73c9cef9090b404690e215574f65ff))
* refactor Dashboard components to use StatCard for metrics display; enhance styling and add error handling ([9109977](https://github.com/nubitio/efact/commit/9109977a27eaeeacb8cc57c0aa6eb4e42fb2ba8d))
* refactor FillTimeDimensionCommand and ImportUbigeoCommand to use dedicated services for data seeding; add batch size option for improved performance ([64b2e00](https://github.com/nubitio/efact/commit/64b2e004759b4612a080bf16f82933c1688b5e05))
* update date filtering in DashboardRepository for improved metrics accuracy ([a639c3a](https://github.com/nubitio/efact/commit/a639c3a495fae5467e8cc5973be44de5c8b5e4ae))

### ♻️ Refactoring

* clean up whitespace and improve code readability across multiple files ([f5957a8](https://github.com/nubitio/efact/commit/f5957a86685ddd6fefa06c46e8f601f27ea98be8))
* rename fields in InvoiceFields and InvoicePage for consistency and clarity; add logging in SendInvoiceProcessor ([6badc17](https://github.com/nubitio/efact/commit/6badc17c7448a4305de09c6e0529e11f762be518))
* reorganize namespaces and update class references for improved clarity ([9aff561](https://github.com/nubitio/efact/commit/9aff561aad065f8acd0c198c6243ff08c7b046ee))
* update API prefix to /api and adjust related components for consistency ([e910af4](https://github.com/nubitio/efact/commit/e910af48c5b50bffc91a6d3d6cb9203906eaae3e))
* update API responses in SaleReportResource and SaleResource for improved clarity and consistency ([4aef735](https://github.com/nubitio/efact/commit/4aef735b68b62886af5ff07ff06d9e19d7191307))
* update Invoice entity and related use cases for improved property access and naming consistency ([0a7fca5](https://github.com/nubitio/efact/commit/0a7fca52e5e01b5cbfb559cb17153775be433f80))
* update InvoiceRepository and related classes to improve organization and functionality ([e1cb030](https://github.com/nubitio/efact/commit/e1cb030b7109c7d8961d77c088bc1053a5fc9993))
* update namespaces and move classes to new locations for better organization ([246cef9](https://github.com/nubitio/efact/commit/246cef9bffb498d807d36a058758f213e78dc417))
* update various entities and services for improved consistency and nullable properties; add new Menu and MenuRol entities ([3b132cc](https://github.com/nubitio/efact/commit/3b132cce683e34b8123a33825d462f7b487db6c8))
