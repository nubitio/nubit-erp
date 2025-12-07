## [1.9.0](https://github.com/nubitio/efact/compare/v1.8.0...v1.9.0) (2025-12-07)

### 🚀 Features

* add FrankenPHP Symfony runtime and update Docker configuration ([7451d7e](https://github.com/nubitio/efact/commit/7451d7e51069763afc39df3f59d712d8ce6a3c60))
* add HealthController for database connectivity health check ([65ec236](https://github.com/nubitio/efact/commit/65ec236fbc52dbce27bf265ab9bd493348e657dc))
* add logging for email sending errors in SendEmailProcessor ([d84711c](https://github.com/nubitio/efact/commit/d84711cf2362bd205d856a1ad2fd1bfeb1f7cab0))
* add Symfony Messenger components and update configuration for message handling ([8eb1a97](https://github.com/nubitio/efact/commit/8eb1a976fa5fb0553788fdb195a34638a4762497))
* automate database migrations during entrypoint execution ([70fc64b](https://github.com/nubitio/efact/commit/70fc64b26b82a831bba9666a618751b087f0588a))
* enhance CI/CD workflows with improved validation, security scanning, and performance metrics ([#268](https://github.com/nubitio/efact/issues/268)) ([7474723](https://github.com/nubitio/efact/commit/7474723d266193371977da5026d18e477f8a4f0e))
* enhance type definitions for contentRender and defaultValue, and update cash movement fields to accept navigate function ([07d3803](https://github.com/nubitio/efact/commit/07d3803103e30f52ffcd28ae4cc1cc994a54c55d))

### 🐛 Bug Fixes

* remove continue-on-error from multiple CI/CD steps for better error handling ([c4986a2](https://github.com/nubitio/efact/commit/c4986a23c3a6fa206d1de93cbf5dbecba41a53f8))
* remove unused APP_URL from deployment environment configuration ([4ea3a99](https://github.com/nubitio/efact/commit/4ea3a9945fce6961b30a9076fcc2525aa8ddf400))
* update deploy.yml to include actions read access ([ed6ece8](https://github.com/nubitio/efact/commit/ed6ece8362ced03a245fcb25f8718606f3416791))
* update deploy.yml to include write access for security-events ([a9fde4b](https://github.com/nubitio/efact/commit/a9fde4bbd8d5fbc5ca91270c91d2a40d78ccbe44))
* update Docker images in docker-compose.prod.yml to use 'main' tag ([b32bbd3](https://github.com/nubitio/efact/commit/b32bbd3424f57a93ed08e2b7dd41d4e160a901d7))
* update permissions in deploy.yml to include actions read access ([8322bfa](https://github.com/nubitio/efact/commit/8322bfa5f94c75c2f531a7a8ecf49ec1ab5fb078))

### ♻️ Refactoring

* remove production deployment step from workflow configuration ([6eb20b1](https://github.com/nubitio/efact/commit/6eb20b138c65286f89e615e0a1a1b5097ac50143))
* remove TypeScript type check step from CI pipeline ([c1fa400](https://github.com/nubitio/efact/commit/c1fa400ae124e5ba00c11d6a35c0601b45cdc0a6))
* replace 'let' with 'const' for variable declarations in multiple files ([9438914](https://github.com/nubitio/efact/commit/94389141cd801c9dc433eae1d3d5147191ae5b57))
* simplify versioning workflow and add semantic release configuration ([7a8fc01](https://github.com/nubitio/efact/commit/7a8fc011cc7b9e36736971cc362774ac3a5832f5))

### 📚 Documentation

* update CHANGELOG.md ([c4a7ba8](https://github.com/nubitio/efact/commit/c4a7ba8a12bb84c73633ccd89acb7fee0cf81262))

# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.8.0] - 2025-12-07

### ♻️ Refactoring

- Remove security scanning steps from CI and deployment workflows ([3d2579a](3d2579a045b02dd00febd509808b681aa3efbf04))

### 🎨 Styling

- Standardize SCSS and TypeScript formatting; adjust import statements and spacing ([bf1e804](bf1e804b78ac6a06fa630a798ce8e9fda923267a))

### 🐛 Bug Fixes

- Remove fallback values for Portainer stack name and endpoint ID in deployment configuration ([4e78dce](4e78dce18e531baf98365b012a9ad9df3a8d41bd))
- Update Docker Compose configuration for production environment ([88fef4f](88fef4fb09a65aa4ad64d71d60d064b9f9e5c17c))
- Remove image digest from deployment configuration for Portainer ([d6d968f](d6d968f8c8a74ad7567fe3cfe5980aa7c6b9dca1))
- Exclude DataFixtures from automatic service discovery in services configuration ([e94e533](e94e5333900b2218f380da4176ac82c7ec9b55d9))

### 📚 Documentation

- Update CHANGELOG.md ([3e862db](3e862db2e8b19b1d767bf2d460b6102f53bea551))

### 🚀 Features

- Add GitHub Container Registry login step to deploy workflow ([b94b0ec](b94b0ec68dd18144c48426f99e271db9676c7901))
- Simplify deployment to Portainer by using portainer-stack-deploy action ([d08b174](d08b1741d6d7b7fa8c541240292fa048ba6ec498))
- Add verification for Portainer configuration secrets before deployment ([bffc1c1](bffc1c1ca4067f0959077e80eb210074f2c2ed5b))
- Enhance deployment workflow with improved environment detection and tagging ([8130d7c](8130d7c06f98bf0ff2b1fde72835590f9cc7d388))
- Add common entrypoint script for container initialization ([086ae1f](086ae1fa095f90a3203382ed0bef09c153180841))
- Update CI/CD workflows for release branch support and enhanced deployment options ([6ab849a](6ab849aaf9a64cc1f927a4de8a4c2c3dcb60e769))

## [1.7.0] - 2025-12-06

### ♻️ Refactoring

- Update namespaces and move classes to new locations for better organization ([246cef9](246cef9bffb498d807d36a058758f213e78dc417))
- Reorganize namespaces and update class references for improved clarity ([9aff561](9aff561aad065f8acd0c198c6243ff08c7b046ee))
- Update InvoiceRepository and related classes to improve organization and functionality ([e1cb030](e1cb030b7109c7d8961d77c088bc1053a5fc9993))
- Clean up whitespace and improve code readability across multiple files ([f5957a8](f5957a86685ddd6fefa06c46e8f601f27ea98be8))
- Update API prefix to /api and adjust related components for consistency ([e910af4](e910af48c5b50bffc91a6d3d6cb9203906eaae3e))
- Update Invoice entity and related use cases for improved property access and naming consistency ([0a7fca5](0a7fca52e5e01b5cbfb559cb17153775be433f80))
- Rename fields in InvoiceFields and InvoicePage for consistency and clarity; add logging in SendInvoiceProcessor ([6badc17](6badc17c7448a4305de09c6e0529e11f762be518))
- Update API responses in SaleReportResource and SaleResource for improved clarity and consistency ([4aef735](4aef735b68b62886af5ff07ff06d9e19d7191307))
- Update various entities and services for improved consistency and nullable properties; add new Menu and MenuRol entities ([3b132cc](3b132cce683e34b8123a33825d462f7b487db6c8))

### 🔧 Miscellaneous

- Update database configuration in .env and docker-compose.yml for efact ([06a12ea](06a12eabf83c7c5813b915f3edd4427103ee811c))
- Update PHP and Symfony versions in Dockerfile and composer.json ([c8f674a](c8f674ac7f832678a532a8024abb602c1d2dd38a))
- Remove doctrine/annotations dependency and update related configurations ([90601ba](90601bafdd7b7f796884f5b20a0addf8078b2bd9))
- Add pontedilana/php-weasyprint dependency to composer.json and update composer.lock ([abd90d0](abd90d0e4f59c106362d0c82caa75ca347d7df77))
- Update PHP version to 8.5 in code_quality.yml and test.yml ([81afdcf](81afdcf5a6d2f9787f3a8a360d734f2db14163c4))

### 🚀 Features

- Implement invoice sending command with batch processing and error handling ([cc92a9a](cc92a9a66d24096e585d5c36b9a3234158643a30))
- Add Doctrine Fixtures Bundle and create initial AppFixtures class ([5a167c3](5a167c3c66e458b7b69a85cd901ae18aa8bcfafe))
- Implement initial setup process with validation and API integration ([eb3a0e4](eb3a0e4fbc67eaaa94b815c5bcd1fd6afd04c766))
- Add Ubigeo entity and repository; update company context and settings for ubigeo integration; enhance invoice processing with ubigeo data ([26cb234](26cb234a97828e80ab6cb3e69a5b228e312cdd8c))
- Add FillTimeDimensionCommand and related entities for time dimension management; create Summary and TimeDimension entities with repositories ([d1661e4](d1661e4d7fb1142f3e039167852014ac40369e54))
- Refactor FillTimeDimensionCommand and ImportUbigeoCommand to use dedicated services for data seeding; add batch size option for improved performance ([64b2e00](64b2e004759b4612a080bf16f82933c1688b5e05))
- Enhance Dashboard functionality with consolidated overview data; improve styling and structure of dashboard components ([3d0d7f5](3d0d7f5de23a4fa78d74764327c72fb4a350da30))
- Refactor Dashboard components to use StatCard for metrics display; enhance styling and add error handling ([9109977](9109977a27eaeeacb8cc57c0aa6eb4e42fb2ba8d))
- Implement password visibility toggle in LoginForm; enhance styling for password toggle button ([6d8b868](6d8b868d97e69ac3a95149862c7c66a489140b31))
- Enhance Dashboard with refund and net sales metrics; add cash register status section; update payment method trend visualization ([e62fd76](e62fd760febbbbc925025e70f9c802e05c582877))
- Implement v1 API versioning; create ApiController and ApiRepository for handling requests; update security and constants for new API structure ([3a29309](3a2930902c73c9cef9090b404690e215574f65ff))
- Add MenuSeedService for seeding menu items; implement SeedMenuCommand for command line execution ([ea9f4a1](ea9f4a173ba6ac71f30a027a3007139f2b01415c))
- Update date filtering in DashboardRepository for improved metrics accuracy ([a639c3a](a639c3a495fae5467e8cc5973be44de5c8b5e4ae))
- Add CI and CD pipelines for automated testing and deployment; configure Docker build and push steps ([f29ba1a](f29ba1ac1f64ad98fa1248056507a60be7fe073d))

## [1.4.17] - 2025-03-05

### Wip

- Refactor sales ([4c74e1d](4c74e1d546f0d651290a96462ee151dbde31e9aa))

## [1.4.13] - 2025-02-19

### Wip

- New cash register module ([dd7ff60](dd7ff60a989f2458f7101c2ca0f7d711100771d9))
- New cash register module also fix dropdown search ([aac4cae](aac4caefd47da91f26222491b57682ed190c34dc))

## [1.4.11] - 2025-02-08

### Wip

- Refactor ([c7a0240](c7a0240a01aa765c865554205b5fd37316122b64))
- Refactor ([2743c7d](2743c7de496d59bc013de292f460e7494be099f1))
- Refactor ([c1fc34d](c1fc34d1d280749c4425eb198e87d71c3d72427e))

## [1.4.10] - 2025-02-01

### Wip

- Pos page ([f219327](f219327974883ccbb296d1064a6c7c22aa4e6806))
- Pos module ([c239df9](c239df936108055272f0c8c4ba7fa0ae31184a5b))

## [1.4.8] - 2024-12-18

### Wip

- Refactor ([1bff5b2](1bff5b2520ea3f89b18267689921a3da6c0a7a93))
- Refactor ([fc9ef01](fc9ef01553f053d1e42c0cf165f1e256fa6de13b))
- Refactor ([499624c](499624c6e970d8c8d2e131a161b30423812392ec))
- Refactor (field builder fluent api, validators) ([6a12268](6a122680a8e9d6c4e283a861e6d8805e58ee0379))
- Pos page ([9473498](94734980e0f1124c90afd7a84ab3230b89f0854e))

## [1.4.6] - 2023-10-10

### Wip

- Fix hmd patient edit in sale ([d04ddfa](d04ddfaefb26d6194cf2e69929b99a161841a01f))
- Consultation sale ([7d6b522](7d6b522371c040edc1dd7148001e694914c86ef9))
- Sale association ([22acf60](22acf60c0a18e5047c3e9f693c4d065a44a0992a))

## [1.4.3] - 2023-09-03

### Wip

- Fix kardex module ([2deae8d](2deae8d4cc227f078facc104392433f50249051e))

### 🚀 Features

- Kardex ([d4ec209](d4ec2097968ba68e06d2ae267972b0e3d672afde))

## [1.4.1] - 2023-08-24

### 🐛 Bug Fixes

- Inbound, kardex and filter sale by branch ([5d2e965](5d2e965cf68986b64b38a19d533cf76fbee49e5c))

## [1.3.0] - 2023-05-10

### Try

- Fix pipeline ([a32e8f8](a32e8f8d537a4c5854ffa508b2e947df6e38a636))

### 🐛 Bug Fixes

- Missing session variable in frontend ([ca35618](ca356181e93b96566027776148ada8ea1b55e082))

<!-- generated by git-cliff -->
