# Efact - Electronic Billing System

Sistema integral de facturación electrónica desarrollado con Symfony 7.4, API Platform, React 18 y DevExtreme.

## 📋 Tabla de Contenidos

- [Arquitectura](#-arquitectura)
- [Stack Tecnológico](#-stack-tecnológico)
- [Estructura del Proyecto](#-estructura-del-proyecto)
- [Instalación](#-instalación)
- [Desarrollo](#-desarrollo)
- [Testing y Calidad](#-testing-y-calidad)
- [Deployment](#-deployment)
- [Guía para Agentes AI](#-guía-para-agentes-ai)

---

## 🏗 Arquitectura

### Filosofía Arquitectónica

Este proyecto sigue un **patrón arquitectónico pragmático** que combina lo mejor de diferentes metodologías:

- **Clean Architecture**: Separación clara de responsabilidades y dependencias unidireccionales
- **Vertical Slice Architecture**: Features organizados por funcionalidad de negocio completa (no por capas técnicas)
- **Domain-Driven Design (DDD)**: Cuando la complejidad lo justifica (ej: módulo Billing)
- **SOLID, DRY, KISS**: Principios aplicados pragmáticamente

**Principio clave**: Solo agregamos complejidad donde es realmente necesario. Maximizamos agilidad manteniendo simplicidad.

### Capas de la Aplicación

```
┌─────────────────────────────────────────────────┐
│           Frontend (React + DevExtreme)         │
│   assets/features/* - Vertical Slices          │
└─────────────────────────────────────────────────┘
                      ↕ HTTP/JSON
┌─────────────────────────────────────────────────┐
│        API Layer (API Platform + Symfony)       │
│   src/ApiResource/* - API Endpoints            │
│   src/State/* - State Providers/Processors      │
└─────────────────────────────────────────────────┘
                      ↕
┌─────────────────────────────────────────────────┐
│            Application Layer                    │
│   src/Controller/* - Custom controllers        │
│   src/Command/* - CLI commands                  │
│   src/Service/* - Application services          │
└─────────────────────────────────────────────────┘
                      ↕
┌─────────────────────────────────────────────────┐
│              Domain Layer                       │
│   src/*/Domain/* - Business logic               │
│   src/Entity/* - Domain models (Doctrine)       │
│   src/Repository/* - Data access                │
└─────────────────────────────────────────────────┘
                      ↕
┌─────────────────────────────────────────────────┐
│         Infrastructure (MariaDB, Redis)         │
└─────────────────────────────────────────────────┘
```

### Módulos Principales

#### Backend Modules

- **Billing** (`src/Billing/`): Facturación electrónica con DDD completo
  - Domain: Use cases, interfaces, value objects
  - Infrastructure: Implementaciones concretas (SUNAT, archivos XML)
  - Model: DTOs de respuesta
  
- **Invoice** (`src/Invoice/`): Gestión de facturas
- **Receipt** (`src/Receipt/`): Recibos y comprobantes
- **Sale** (`src/Sale/`): Proceso de ventas
- **Core** (`src/Core/`): Infraestructura compartida y utilities

#### Frontend Modules (Vertical Slices)

Cada feature contiene todo lo necesario:
```
assets/features/{feature}/
├── components/     # Componentes UI específicos
├── pages/          # Páginas/vistas
├── services/       # Lógica de negocio y API calls
└── types/          # TypeScript types
```

---

## 🛠 Stack Tecnológico

### Backend

- **PHP 8.5**: Lenguaje base con features modernas
- **Symfony 7.4**: Framework principal
- **API Platform 4.1**: REST API automática con OpenAPI
- **Doctrine ORM 2.15**: Persistencia y modelado de datos
- **FrankenPHP 1.10**: Servidor de aplicación de alto rendimiento con soporte Worker mode
- **Redis**: Cache y message broker
- **MariaDB 10.7**: Base de datos relacional

### Frontend

- **React 18**: Biblioteca UI con hooks y concurrent features
- **TypeScript 4.8**: Type safety
- **DevExtreme 25.1**: Componentes empresariales (DataGrid, Forms, etc.)
- **React Router 6**: Enrutamiento SPA
- **Axios**: HTTP client
- **Vite 6**: Build tool y dev server ultra-rápido
- **Tailwind CSS 4**: Utility-first CSS framework

### DevOps & Tools

- **Docker + Docker Compose**: Containerización
- **Yarn 4**: Gestión de dependencias frontend
- **Composer**: Gestión de dependencias PHP
- **PHPStan**: Análisis estático
- **PHP CS Fixer**: Code style
- **ESLint + Prettier**: Linting y formateo JS/TS
- **GrumPHP**: Git hooks para calidad de código
- **OpenTelemetry**: Observabilidad y tracing

---

## 📁 Estructura del Proyecto

```
efact/
├── assets/                    # Frontend (React + TypeScript)
│   ├── features/             # Vertical slices por funcionalidad
│   │   ├── assistant/        # AI Assistant
│   │   ├── cashRegister/     # Caja registradora
│   │   ├── invoice/          # Facturas
│   │   └── .../
│   ├── components/           # Componentes compartidos
│   ├── contexts/             # React contexts (auth, theme)
│   ├── layouts/              # Layout components
│   ├── api/                  # API client layer
│   ├── utils/                # Utilidades compartidas
│   └── app.tsx               # Entry point
│
├── src/                      # Backend (Symfony + PHP)
│   ├── ApiResource/          # API Platform resources
│   ├── State/                # State providers/processors
│   ├── Entity/               # Doctrine entities
│   ├── Repository/           # Data repositories
│   ├── Billing/              # Módulo facturación (DDD)
│   │   ├── Domain/           # Lógica de negocio pura
│   │   ├── Infrastructure/   # Implementaciones
│   │   └── Model/            # DTOs
│   ├── Controller/           # Controllers Symfony tradicionales
│   ├── Service/              # Application services
│   ├── Command/              # CLI commands
│   ├── Core/                 # Shared infrastructure
│   └── Kernel.php
│
├── config/                   # Configuración Symfony
│   ├── packages/             # Bundle configs
│   ├── routes/               # Routing
│   └── services.yaml         # DI container
│
├── migrations/               # Doctrine migrations
├── templates/                # Twig templates (mínimo)
├── tests/                    # PHPUnit tests
├── public/                   # Web root
│   ├── build/                # Vite build output
│   └── index.php             # Entry point
│
├── docker/                   # Docker configs
│   ├── frankenphp/          # FrankenPHP/Caddy config
│   └── php/                 # PHP ini configs
│
├── docker-compose.yml        # Dev environment
├── Dockerfile                # Multi-stage build
├── composer.json             # PHP dependencies
├── package.json              # Node dependencies
├── phpstan.dist.neon         # PHPStan config
├── phpcs.xml.dist            # PHP CS config
├── grumphp.yml               # Git hooks config
└── vite.config.js            # Vite configuration
```

---

## 🚀 Instalación

### Prerequisitos

- Docker Desktop (Windows/Mac) o Docker Engine + Docker Compose (Linux)
- Git

### Setup Inicial

```bash
# 1. Clonar repositorio
git clone https://github.com/CompuExpress/efact.git
cd efact

# 2. Copiar archivo de configuración
cp .env .env.local

# 3. Ajustar variables en .env.local según tu entorno
# DATABASE_URL, APP_SECRET, etc.

# 4. Levantar contenedores
docker-compose up -d

# 5. Instalar dependencias PHP
docker-compose exec web composer install

# 6. Instalar dependencias Node
docker-compose exec vite yarn install

# 7. Ejecutar migraciones
docker-compose exec web php bin/console doctrine:migrations:migrate --no-interaction

# 8. (Opcional) Cargar datos de prueba
docker-compose exec web php bin/console doctrine:fixtures:load --no-interaction
```

### Acceso a la Aplicación

- **Frontend**: http://localhost:8080
- **API**: http://localhost:8080/api
- **API Docs**: http://localhost:8080/api/docs
- **Database**: localhost:3306 (user: efact, pass: Admin.3000)
- **Redis**: localhost:6379

---

## 💻 Desarrollo

### Workflow Diario

```bash
# Terminal 1: Backend (ya corriendo con docker-compose up)
docker-compose logs -f web

# Terminal 2: Frontend dev server con HMR
docker-compose exec vite yarn dev

# Terminal 3: Comandos adicionales
docker-compose exec web php bin/console [comando]
```

### Comandos Útiles

#### Backend

```bash
# Cache
php bin/console cache:clear

# Crear entidad
php bin/console make:entity

# Crear migración
php bin/console make:migration
php bin/console doctrine:migrations:migrate

# Debug rutas
php bin/console debug:router

# Debug container
php bin/console debug:container
```

#### Frontend

```bash
# Dev server
yarn dev

# Build para producción
yarn build

# Build themes
yarn build:themes

# Linting
yarn lint
yarn lint:fix

# Formateo
yarn format
yarn format:check
```

### Agregar Nueva Funcionalidad

#### Backend (API Platform Resource)

1. **Crear Entity** (si no existe):
```php
// src/Entity/Product.php
#[ORM\Entity]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Patch(),
        new Delete()
    ]
)]
class Product {
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    
    #[ORM\Column(length: 255)]
    private string $name;
}
```

2. **State Provider/Processor** (si necesitas lógica custom):
```php
// src/State/Product/ProductProcessor.php
class ProductProcessor implements ProcessorInterface {
    public function process($data, Operation $operation, array $context = []) {
        // Custom logic
        return $data;
    }
}
```

3. **Generar migración**:
```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

#### Frontend (Vertical Slice)

1. **Crear estructura**:
```bash
mkdir -p assets/features/products/{components,pages,services,types}
```

2. **Service** (API calls):
```typescript
// assets/features/products/services/productService.ts
import api from '@/api';

export const getProducts = () => api.get('/api/products');
export const createProduct = (data) => api.post('/api/products', data);
```

3. **Component**:
```typescript
// assets/features/products/pages/ProductList.tsx
import { DataGrid } from 'devextreme-react/data-grid';

export default function ProductList() {
    return <DataGrid dataSource="/api/products" />;
}
```

4. **Route**:
```typescript
// assets/app-routes.tsx
import ProductList from './features/products/pages/ProductList';

const routes = [
    { path: '/products', element: <ProductList /> }
];
```

---

## 🧪 Testing y Calidad

### Ejecutar Tests

```bash
# PHPUnit
docker-compose exec web vendor/bin/phpunit

# PHPStan (análisis estático)
docker-compose exec web vendor/bin/phpstan analyse

# PHP CS Fixer (code style)
docker-compose exec web vendor/bin/php-cs-fixer fix --dry-run

# GrumPHP (ejecuta todo)
docker-compose exec web vendor/bin/grumphp run
```

### Git Hooks

GrumPHP se ejecuta automáticamente en:
- `pre-commit`: PHPStan, PHP CS Fixer, PHPMD
- `commit-msg`: Validación de formato de mensaje

### Standards

- **PHP**: PSR-12, Symfony coding standards
- **TypeScript**: ESLint + Prettier config
- **Commits**: Conventional Commits format

---

## 🚢 Deployment

### Build Producción

```bash
# Build imagen producción
docker build --target prod -t efact:latest .

# Run
docker run -p 80:80 -p 443:443 efact:latest
```

### Docker Compose Producción

```bash
docker-compose -f docker-compose.prod.yml up -d
```

### Variables de Entorno Importantes

```bash
APP_ENV=prod
APP_SECRET=<secret-fuerte>
DATABASE_URL=mysql://user:pass@host:3306/dbname
MESSENGER_TRANSPORT_DSN=redis://redis:6379/messages
```

---

## 🤖 Guía para Agentes AI

### Contexto del Proyecto

**Efact** es un sistema de facturación electrónica empresarial que gestiona el ciclo completo: ventas, emisión de comprobantes electrónicos (integración SUNAT - Perú), inventario, caja, y reportes.

### Principios de Desarrollo

1. **Pragmatismo sobre dogmatismo**: No aplicar patrones complejos donde no se necesitan
2. **Vertical slices sobre capas horizontales**: Organizar por features, no por tipo técnico
3. **API First**: Backend expone API REST, frontend consume
4. **Type Safety**: Usar TypeScript en frontend, PHPStan en backend
5. **Convención sobre configuración**: Seguir convenciones de Symfony y React

### Patrones y Convenciones

#### Backend (Symfony)

**Estructura de Clases**:
```
src/
├── Entity/           # Entidades Doctrine (siempre aquí)
├── Repository/       # Repositorios (siempre aquí)
├── ApiResource/      # Recursos API Platform (simple CRUD)
├── State/            # Providers/Processors (lógica API Platform)
├── {Module}/         # Módulos complejos con DDD
│   ├── Domain/       # Lógica pura, interfaces
│   ├── Infrastructure/ # Implementaciones
│   └── Model/        # DTOs
└── Service/          # Servicios transversales
```

**Naming Conventions**:
- Entities: Singular, PascalCase (ej: `Invoice`, `Product`)
- Repositories: `{Entity}Repository` (ej: `InvoiceRepository`)
- Services: Sufijo `Service` o verbo descriptivo (ej: `BillCreateUseCase`)
- API Resources: Sufijo `Resource` (ej: `InvoiceResource`)
- State Processors: Sufijo `Processor` (ej: `InvoiceProcessor`)

**Cuando usar cada patrón**:

1. **Simple CRUD**: Usar solo `Entity` + `ApiResource` annotation
   ```php
   #[ApiResource(operations: [new Get(), new Post()])]
   class SimpleEntity { }
   ```

2. **CRUD con validación/transformación**: Agregar State Provider/Processor
   ```php
   #[ApiResource(
       provider: MyProvider::class,
       processor: MyProcessor::class
   )]
   ```

3. **Lógica de negocio compleja**: Crear módulo con Domain/Infrastructure
   ```
   src/Billing/Domain/BillCreateUseCase.php
   src/Billing/Infrastructure/SunatBillRepository.php
   ```

#### Frontend (React + TypeScript)

**Estructura de Feature**:
```
assets/features/{feature}/
├── components/       # Componentes específicos del feature
├── pages/           # Páginas/vistas principales
├── services/        # Llamadas API y lógica de negocio
├── types/           # TypeScript interfaces/types
└── hooks/           # Custom hooks (opcional)
```

**Naming Conventions**:
- Components: PascalCase (ej: `ProductList.tsx`)
- Hooks: camelCase con prefijo `use` (ej: `useProducts.ts`)
- Services: camelCase con sufijo `Service` (ej: `productService.ts`)
- Types: PascalCase con sufijo `Type` o interface name (ej: `ProductType`, `IProduct`)

**Patterns**:

1. **API Calls**: Siempre en `services/`, no en components
   ```typescript
   // services/productService.ts
   export const getProducts = () => api.get<Product[]>('/api/products');
   ```

2. **State Management**: 
   - Local: `useState` en component
   - Global: React Context (ej: `AuthContext`, `ThemeContext`)
   - Server state: Calls directos (considerar React Query si crece)

3. **Forms**: Usar DevExtreme Form components
   ```typescript
   import { Form } from 'devextreme-react/form';
   ```

4. **Tables**: Usar DevExtreme DataGrid
   ```typescript
   import { DataGrid } from 'devextreme-react/data-grid';
   ```

### Reglas Importantes para AI Agents

#### ✅ DO (Hacer)

1. **Mantener separación de concerns**:
   - Backend: Lógica de negocio en Domain/Services
   - Frontend: Lógica de presentación en components, lógica de negocio en services

2. **Usar tipos existentes**:
   - Backend: Verificar si Entity existe antes de crear
   - Frontend: Usar interfaces TypeScript existentes

3. **Seguir convenciones de nombres** según las tablas arriba

4. **Reutilizar componentes**:
   - Backend: Usar traits de `Core/` para funcionalidad común
   - Frontend: Usar components de `assets/components/` antes de crear nuevos

5. **Validación**:
   - Backend: Usar Symfony Validator en Entities
   - Frontend: Validación en formularios DevExtreme

6. **Manejo de errores**:
   - Backend: Lanzar exceptions apropiadas (Symfony HTTP exceptions)
   - Frontend: Mostrar notificaciones DevExtreme

7. **Testing**:
   - Agregar tests PHPUnit para lógica de negocio backend
   - Considerar tests funcionales para API endpoints

#### ❌ DON'T (No Hacer)

1. **No mezclar capas**:
   - No poner lógica de negocio en Controllers
   - No hacer queries directas en Components

2. **No duplicar código**:
   - Verificar si existe service/utility antes de crear

3. **No ignorar types**:
   - No usar `any` en TypeScript
   - No desactivar PHPStan con `@phpstan-ignore`

4. **No hardcodear valores**:
   - Usar `.env` para configuración
   - Usar constants/enums para valores fijos

5. **No crear archivos en ubicaciones incorrectas**:
   - Entities siempre en `src/Entity/`
   - Components compartidos en `assets/components/`

6. **No modificar vendor/**:
   - Toda customización mediante herencia/composition

### Tareas Comunes

#### Agregar nuevo endpoint API

```bash
# 1. Crear/modificar Entity
src/Entity/NewEntity.php

# 2. Agregar annotations ApiResource
#[ApiResource(operations: [...])]

# 3. Si necesitas lógica: crear State Processor
src/State/NewEntity/NewEntityProcessor.php

# 4. Migración
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

#### Agregar nueva página frontend

```bash
# 1. Crear estructura
mkdir -p assets/features/newFeature/{components,pages,services}

# 2. Service para API
assets/features/newFeature/services/newFeatureService.ts

# 3. Página
assets/features/newFeature/pages/NewFeaturePage.tsx

# 4. Route
assets/app-routes.tsx
```

#### Debugging

```bash
# Backend logs
docker-compose logs -f web

# Ver SQL queries
APP_ENV=dev -> Symfony profiler en /_profiler

# Frontend
Browser DevTools + React DevTools
```

### Recursos de Documentación

- [Symfony Docs](https://symfony.com/doc/current/index.html)
- [API Platform](https://api-platform.com/docs/core/)
- [Doctrine ORM](https://www.doctrine-project.org/projects/doctrine-orm/en/latest/)
- [React Docs](https://react.dev/)
- [DevExtreme React](https://js.devexcel.com/react/)
- [TypeScript Handbook](https://www.typescriptlang.org/docs/)

### Contacto y Soporte

- **Maintainer**: Johan Guerreros (johangm90@gmail.com)
- **Repository**: https://github.com/CompuExpress/efact

---

## POS Offline-First (PWA)

### Cómo funciona

- La capa offline está en `assets/offline/` con IndexedDB (Dexie) y tablas:
  - `products`
  - `customers`
  - `sales`
  - `outbox`
  - `sync_logs`
- El checkout POS siempre crea una venta local (`sales`) y una tarea en cola (`outbox`) con `Idempotency-Key`.
- Si no hay conexión:
  - se confirma: `Venta guardada localmente`
  - la venta queda en estado `pending` y “Pendiente de sincronización”.
- Cuando vuelve internet:
  - se procesa cola FIFO automáticamente
  - cada venta pasa por estados `pending -> syncing -> synced` o `error`
  - en errores recuperables hay reintentos automáticos con backoff exponencial (1s, 3s, 10s, 30s, 2m, 5m)
  - en errores no recuperables (400/422) queda en `error` y se reintenta manualmente.

### Conectividad y UI

- Estado visible en header:
  - `🟢 Conectado`
  - `🔴 Sin conexión (modo offline)`
  - `🟡 Sincronizando (N pendientes)`
- Indicador de pendientes con acción `Ver detalle`.
- Estado por venta (`pending`, `syncing`, `synced`, `error`) con botón `Reintentar` en errores.
- Catálogo POS:
  - online: API + persistencia en IndexedDB
  - offline: lectura desde IndexedDB con banner `Mostrando catálogo offline (última actualización: ...)`.

### PWA / Service Worker

- Configurado con `vite-plugin-pwa` + Workbox en `vite.config.js`.
- Estrategias:
  - App shell y assets: `CacheFirst`
  - `GET /api/products`: `StaleWhileRevalidate`
  - `GET /api/customers`: `StaleWhileRevalidate`
  - `POST` de ventas no se cachea (la resiliencia la maneja Outbox en la app).

### Cómo probar

1. Abrir POS, cargar productos y realizar al menos una venta online.
2. En Chrome DevTools: `Network -> Offline`.
3. Registrar una venta desde POS:
   - debe mostrar `Venta guardada localmente`
   - debe incrementar `Ventas pendientes`.
4. Volver a `Online`:
   - verificar transición de estados hasta `synced`.
5. Forzar error:
   - simular 500 o caída de red para ver `retrying` automático.
   - simular 422 para ver estado `error` y usar `Reintentar`.

---

## 📝 Notas Adicionales

### Performance

- **FrankenPHP Worker Mode**: Habilitado en producción para máximo performance
- **OpCache**: Configurado en PHP para caching de bytecode
- **Redis**: Usado para cache y message queue (Symfony Messenger)
- **Vite**: Build optimizado con tree-shaking y code splitting

### Seguridad

- **CORS**: Configurado via NelmioCorsBundle
- **JWT**: Autenticación stateless
- **HTTPS**: Manejado por FrankenPHP/Caddy (auto-certificates en staging/prod)
- **Validation**: Input validation en backend (Symfony Validator)

### Observabilidad

- **OpenTelemetry**: Tracing distribuido configurado
- **Monolog**: Logging estructurado

---

**Última actualización**: 2025-12-10
**Versión**: 1.0.0
