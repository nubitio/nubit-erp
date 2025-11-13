# Core Library

A comprehensive PHP core library providing reusable utilities, services, and components for Symfony applications.

## Structure

```
Core/
├── Annotation/          # Custom PHP attributes
├── Application/         # Application-specific components (Commands, Sessions)
├── Base/               # Base classes (Controllers, etc.)
├── Cache/              # Cache management
├── Color/              # Color manipulation utilities
├── Compiler/           # Compiler passes
├── Doctrine/           # Doctrine extensions (DBAL, Filters)
├── Entity/             # Base entity classes
├── EventListener/      # Event listeners
├── Exception/          # Custom exceptions
├── Filesystem/         # File management
├── Http/               # HTTP related (ApiResponse)
├── Security/           # Authentication & authorization
├── Serialization/      # Serialization services
├── Traits/             # Reusable entity traits
├── Util/               # Helper utilities
└── Validation/         # Validation services
```

## Components

### HTTP

#### ApiResponse
Standard API response wrapper with success/error factory methods.

```php
use App\Core\Http\ApiResponse;

$response = ApiResponse::success('User created', $userData);
$response = ApiResponse::error('Validation failed', $errors);
```

### Cache

#### CacheManager
PSR-6 compatible cache manager with convenience methods.

```php
use App\Core\Cache\CacheManager;

$cache->set('user.123', $user, 3600);
$user = $cache->get('user.123');
$user = $cache->remember('user.123', fn() => $repository->find(123), 3600);
```

### Filesystem

#### FileManager
Flysystem-based file operations.

```php
use App\Core\Filesystem\FileManager;

$fileManager->write('path/to/file.txt', 'content');
$content = $fileManager->read('path/to/file.txt');
$exists = $fileManager->exists('path/to/file.txt');
$fileManager->delete('path/to/file.txt');
```

#### FileUploader
Upload files with automatic slug generation.

```php
use App\Core\Filesystem\FileUploader;

$fileName = $uploader->upload($uploadedFile, 'documents');
```

### Serialization

#### SerializerService
Enhanced Symfony serializer with convenience methods.

```php
use App\Core\Serialization\SerializerService;

$json = $serializer->serialize($object, 'json');
$object = $serializer->deserialize($json, User::class, 'json');
$array = $serializer->normalize($object);

// Safe normalization with circular reference handling
$array = $serializer->normalizeSafe($entityWithRelations);
```

**Important**: To prevent infinite loops and memory issues with entities that have circular references, use `#[MaxDepth]` annotations or the `normalizeSafe()` method.

### Color Utilities

#### ColorUtils
Color manipulation and conversion utilities.

```php
use App\Core\Color\ColorUtils;

$contrast = ColorUtils::contrastColor('#FF5733');
$lighter = ColorUtils::lightenColor('#FF5733', 20);
$darker = ColorUtils::darkenColor('#FF5733', 20);
$rgb = ColorUtils::hexToRgb('#FF5733');
$hex = ColorUtils::rgbToHex(255, 87, 51);
```

### Utilities

#### ArrayHelper
Array manipulation utilities.

```php
use App\Core\Util\ArrayHelper;

$value = ArrayHelper::get($array, 'user.profile.name', 'default');
ArrayHelper::set($array, 'user.profile.name', 'John');
$subset = ArrayHelper::only($array, ['id', 'name']);
$filtered = ArrayHelper::except($array, ['password']);
```

#### StringHelper
String manipulation utilities.

```php
use App\Core\Util\StringHelper;

$slug = StringHelper::slug('Hello World'); // hello-world
$camel = StringHelper::camelCase('hello_world'); // helloWorld
$snake = StringHelper::snakeCase('HelloWorld'); // hello_world
$limited = StringHelper::limit('Long text...', 10);
$random = StringHelper::random(32);
```

#### DateHelper
Date manipulation utilities.

```php
use App\Core\Util\DateHelper;

$now = DateHelper::now();
$today = DateHelper::today();
$tomorrow = DateHelper::tomorrow();
$diff = DateHelper::diffInDays($date1, $date2);
$newDate = DateHelper::addDays($date, 7);
```

#### NumberHelper
Number formatting and manipulation.

```php
use App\Core\Util\NumberHelper;

$formatted = NumberHelper::format(1234.56, 2); // 1,234.56
$currency = NumberHelper::formatCurrency(99.99, 'USD'); // USD 99.99
$percentage = NumberHelper::percentage(25, 100); // 25.0
$clamped = NumberHelper::clamp(150, 0, 100); // 100
```

### Exceptions

#### ServiceException
Base exception for service-layer errors.

```php
use App\Core\Exception\ServiceException;

throw ServiceException::create('Operation failed');
```

#### ValidationException
Exception for validation errors with error details.

```php
use App\Core\Exception\ValidationException;

throw ValidationException::withErrors([
    'email' => ['Email is required', 'Email is invalid'],
    'password' => ['Password must be at least 8 characters']
]);
```

#### NotFoundException
Exception for resource not found errors.

```php
use App\Core\Exception\NotFoundException;

throw NotFoundException::forResource('User', $userId);
```

### Entity Traits

#### TimestampableTrait
Automatic created_at and updated_at timestamps.

```php
use App\Core\Traits\TimestampableTrait;

class User
{
    use TimestampableTrait;
}
```

#### SoftDeletableTrait
Soft delete functionality.

```php
use App\Core\Traits\SoftDeletableTrait;

class Post
{
    use SoftDeletableTrait;
}

$post->softDelete();
$post->restore();
$isDeleted = $post->isDeleted();
```

### Base Classes

#### BaseController
Enhanced controller with convenience response methods.

```php
use App\Core\Base\BaseController;

class UserController extends BaseController
{
    public function create(): JsonResponse
    {
        return $this->success('User created', $user);
        // or
        return $this->created('User created', $user);
        // or
        return $this->error('Validation failed', $errors);
        // or
        return $this->notFound('User not found');
    }
}
```

#### AuditableEntity
Base entity with audit fields (created_by, updated_by, timestamps, active).

```php
use App\Core\Entity\AuditableEntity;

class Product extends AuditableEntity
{
    // Inherits: createdBy, updatedBy, createdAt, updatedAt, active
}

$product->activate();
$product->deactivate();
```

### Security

#### JWTManager
JWT token management with validation.

```php
use App\Core\Security\Auth\JWTManager;

$token = $jwtManager->encode(['user_id' => 123], 3600);
$payload = $jwtManager->decode($token);
$isValid = $jwtManager->verify($token);
$isExpired = $jwtManager->isExpired($token);
```

### Validation

#### Validator
Symfony validator wrapper with exception throwing.

```php
use App\Core\Validation\Validator;

$validator->validate($user); // Throws ValidationException on failure
$isValid = $validator->isValid($user);
$errors = $validator->getViolations($user);
```

### Event Listeners

#### ExceptionListener
Global exception handler for ServiceException hierarchy.

#### ApiResponseListener
Adds pagination headers to API responses.

## Usage in Future Projects

To use this core library in new projects:

1. Copy the entire `src/Core` directory to your project
2. Ensure namespace remains `App\Core` or update accordingly
3. Register services in `config/services.yaml` if needed
4. The library is framework-agnostic where possible, but integrates seamlessly with Symfony

## Dependencies

- PHP 8.1+
- Symfony Components (Console, HttpFoundation, HttpKernel, Serializer, Validator)
- Doctrine ORM (for entity traits)
- Flysystem (for file management)
- Firebase JWT (for JWT management)

## Best Practices

- All utility classes are `final` and use static methods
- Services use readonly properties and constructor property promotion
- Strict types are declared in all files
- Exception hierarchy provides specific error handling
- Traits provide reusable entity behaviors
- PSR standards are followed throughout

## License

Proprietary - For internal use only.
