<?php

declare(strict_types=1);

namespace App\Tests\Core\Security\Auth;

use App\Core\Audit\AuditLogger;
use App\Core\Context\TenantContext;
use App\Identity\Auth\ClientDetectorService;
use App\Identity\Auth\CookieFactoryService;
use App\Identity\Auth\JWTAuthenticationToken;
use App\Identity\Auth\JWTAuthenticator;
use App\Identity\Auth\JWTManager;
use App\Identity\Auth\MercureSubscriberTokenService;
use App\Identity\Auth\TokenGeneratorService;
use App\Identity\Auth\UserProvider;
use App\Identity\Entity\Role;
use App\Identity\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Unit tests for JWTAuthenticator.
 *
 * Design notes:
 *  - JWTManager, ClientDetectorService, CookieFactoryService, TokenGeneratorService
 *    are all `final readonly` and cannot be mocked with PHPUnit's built-in doubling.
 *    We use real instances where feasible:
 *      - JWTManager: real instance with a known test secret.
 *      - ClientDetectorService / CookieFactoryService: real (zero-config).
 *      - TokenGeneratorService: real instance with a mocked EntityManager so
 *        persist/flush are no-ops.
 *  - TenantContext is `final` (but not readonly): we use a real instance and
 *    call its setter methods to configure state.
 *  - UserProvider and AuditLogger are readonly but not final → mockable.
 */
#[AllowMockObjectsWithoutExpectations]
final class JWTAuthenticatorTest extends TestCase
{
    private const string JWT_SECRET = 'test-secret-key-for-unit-tests-at-least-32-chars!!';

    private UserProvider&MockObject $userProvider;
    private JWTManager $jwtManager;
    private LoggerInterface&MockObject $logger;
    private TokenGeneratorService $tokenGenerator;
    private ClientDetectorService $clientDetector;
    private CookieFactoryService $cookieFactory;
    private MercureSubscriberTokenService $mercureTokenService;
    private TranslatorInterface&MockObject $translator;
    private AuditLogger&MockObject $auditLogger;
    private TenantContext $tenantContext;
    private JWTAuthenticator $authenticator;

    protected function setUp(): void
    {
        $this->userProvider  = $this->createMock(UserProvider::class);
        $this->jwtManager    = new JWTManager(self::JWT_SECRET, new NullLogger());
        $this->logger        = $this->createMock(LoggerInterface::class);
        $this->clientDetector = new ClientDetectorService();
        $this->cookieFactory = new CookieFactoryService(false);
        $this->mercureTokenService = new MercureSubscriberTokenService(self::JWT_SECRET, 3600);
        $this->translator    = $this->createMock(TranslatorInterface::class);
        $this->auditLogger   = $this->createMock(AuditLogger::class);
        $this->tenantContext = new TenantContext();

        // TokenGeneratorService is final readonly — create real instance with a mocked EM
        $em = $this->createStub(EntityManagerInterface::class);
        $this->tokenGenerator = new TokenGeneratorService(
            $this->jwtManager,
            $em,
            $this->tenantContext,
            3600,
            86400
        );

        $this->authenticator = new JWTAuthenticator(
            $this->userProvider,
            $this->jwtManager,
            $this->logger,
            $this->tokenGenerator,
            $this->clientDetector,
            $this->cookieFactory,
            $this->mercureTokenService,
            $this->translator,
            $this->auditLogger,
            $this->tenantContext,
        );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeUser(): User
    {
        $role = new Role();

        $user = new User();
        $user->setUsername('testuser');
        $user->setPassword('hashedpassword');
        $user->setRole($role);

        return $user;
    }

    private function makeJWTToken(User $user, string $rawToken = 'raw.jwt.token'): JWTAuthenticationToken
    {
        return new JWTAuthenticationToken($user, 'main', $user->getRoles(), $rawToken);
    }

    /**
     * Generate a real JWT token using the test JWTManager.
     *
     * @param array<string, mixed> $claims
     */
    private function makeRealJwt(array $claims, ?int $expiresIn = 3600): string
    {
        if ($expiresIn !== null) {
            $claims['exp'] = time() + $expiresIn;
        }

        return $this->jwtManager->encode($claims);
    }

    private function makeRequest(
        string $method = 'GET',
        string $route = 'some_route',
        ?string $bearerToken = null,
        ?string $cookieToken = null,
        string $content = '',
        string $clientType = ''
    ): Request {
        $server = [];
        if ($bearerToken !== null) {
            $server['HTTP_AUTHORIZATION'] = "Bearer {$bearerToken}";
        }
        if ($clientType !== '') {
            $server['HTTP_X_CLIENT_TYPE'] = $clientType;
        }

        $cookies = [];
        if ($cookieToken !== null) {
            $cookies[JWTAuthenticator::AUTH_COOKIE] = $cookieToken;
        }

        $request = Request::create('/', $method, [], $cookies, [], $server, $content);
        $request->attributes->set('_route', $route);

        return $request;
    }

    // =========================================================================
    // supports()
    // =========================================================================

    /** @test (1) Bearer header present, not login route → true */
    public function testSupportsBearerHeaderNotLoginRoute(): void
    {
        $request = $this->makeRequest('GET', 'api_resource', 'mytoken');
        self::assertTrue($this->authenticator->supports($request));
    }

    /** @test (2) Auth cookie present, not login route → true */
    public function testSupportsAuthCookieNotLoginRoute(): void
    {
        $request = $this->makeRequest('GET', 'api_resource', null, 'cookietoken');
        self::assertTrue($this->authenticator->supports($request));
    }

    /** @test (3) POST to login route → true */
    public function testSupportsPostToLoginRoute(): void
    {
        $request = $this->makeRequest('POST', JWTAuthenticator::LOGIN_ROUTE);
        self::assertTrue($this->authenticator->supports($request));
    }

    /** @test (4) Neither header nor cookie, not login route → false */
    public function testSupportsReturnsFalseWithoutHeaderOrCookie(): void
    {
        $request = $this->makeRequest('GET', 'api_resource');
        self::assertFalse($this->authenticator->supports($request));
    }

    /** @test (5) Bearer header on login route → false */
    public function testSupportsReturnsFalseWhenBearerHeaderOnLoginRoute(): void
    {
        $request = $this->makeRequest('GET', JWTAuthenticator::LOGIN_ROUTE, 'mytoken');
        self::assertFalse($this->authenticator->supports($request));
    }

    // =========================================================================
    // authenticate() routing
    // =========================================================================

    /** @test (6) JWT token in Authorization header → SelfValidatingPassport */
    public function testAuthenticateWithJwtTokenInHeader(): void
    {
        $jwt     = $this->makeRealJwt(['username' => 'testuser']);
        $request = $this->makeRequest('GET', 'api_resource', $jwt);

        $passport = $this->authenticator->authenticate($request);

        self::assertInstanceOf(SelfValidatingPassport::class, $passport);
    }

    /** @test (7) JWT token in cookie (no header) → SelfValidatingPassport */
    public function testAuthenticateWithJwtTokenInCookie(): void
    {
        $jwt     = $this->makeRealJwt(['username' => 'testuser']);
        $request = $this->makeRequest('GET', 'api_resource', null, $jwt);

        $passport = $this->authenticator->authenticate($request);

        self::assertInstanceOf(SelfValidatingPassport::class, $passport);
    }

    /** @test (8) No token, credentials present → Passport with PasswordCredentials */
    public function testAuthenticateWithCredentialsWhenNoToken(): void
    {
        $body    = json_encode(['username' => 'testuser', 'password' => 'secret']);
        $request = $this->makeRequest('POST', JWTAuthenticator::LOGIN_ROUTE, null, null, (string) $body);

        $passport = $this->authenticator->authenticate($request);

        self::assertInstanceOf(Passport::class, $passport);
        self::assertTrue($passport->getAttribute('is_login'));
        self::assertTrue($passport->hasBadge(PasswordCredentials::class));
    }

    // =========================================================================
    // authenticateWithJWT() (via authenticate())
    // =========================================================================

    /** @test (9) Valid JWT, username present, tenants match → SelfValidatingPassport */
    public function testAuthenticateWithJwtValidTokenReturnsSelfValidatingPassport(): void
    {
        $this->tenantContext->setTenant(1, 'acme', null, null);
        $jwt = $this->makeRealJwt([
            'username'   => 'alice',
            'tenantName' => 'acme',
            'userId'     => 1,
        ]);

        $request  = $this->makeRequest('GET', 'api_resource', $jwt);
        $passport = $this->authenticator->authenticate($request);

        self::assertInstanceOf(SelfValidatingPassport::class, $passport);
        self::assertFalse($passport->getAttribute('is_login'));
        self::assertSame($jwt, $passport->getAttribute('token'));
    }

    /** @test (10) Expired JWT → AuthenticationException with warning log */
    public function testAuthenticateWithExpiredJwtThrowsAuthenticationException(): void
    {
        // Build an expired JWT directly via Firebase (bypasses our JWTManager encode)
        $expiredJwt = \Firebase\JWT\JWT::encode(
            ['username' => 'alice', 'exp' => time() - 100],
            self::JWT_SECRET,
            'HS256'
        );

        $this->logger->expects(self::once())->method('warning');

        $this->expectException(AuthenticationException::class);
        $this->authenticator->authenticate($this->makeRequest('GET', 'api', $expiredJwt));
    }

    /** @test (11) Generic invalid JWT → AuthenticationException with error log */
    public function testAuthenticateWithInvalidJwtThrowsAuthenticationException(): void
    {
        $this->logger->expects(self::once())->method('error');

        $this->expectException(AuthenticationException::class);
        $this->authenticator->authenticate($this->makeRequest('GET', 'api', 'not.valid.jwt.at.all'));
    }

    /** @test (12) Valid JWT but no username claim → AuthenticationException */
    public function testAuthenticateJwtMissingUsernameClaimThrows(): void
    {
        $jwt = $this->makeRealJwt(['tenantName' => 'acme']); // no 'username' key

        $this->expectException(AuthenticationException::class);
        $this->authenticator->authenticate($this->makeRequest('GET', 'api', $jwt));
    }

    /** @test (13) JWT tenant mismatch → AuthenticationException with warning */
    public function testAuthenticateJwtTenantMismatchThrows(): void
    {
        $this->tenantContext->setTenant(1, 'tenant-b', null, null);
        $jwt = $this->makeRealJwt([
            'username'   => 'alice',
            'tenantName' => 'tenant-a',
        ]);

        $this->logger->expects(self::once())->method('warning');

        $this->expectException(AuthenticationException::class);
        $this->authenticator->authenticate($this->makeRequest('GET', 'api', $jwt));
    }

    // =========================================================================
    // authenticateWithCredentials() (via authenticate())
    // =========================================================================

    /** @test (14) Empty username → AuthenticationException */
    public function testAuthenticateWithEmptyUsernameThrows(): void
    {
        $body    = json_encode(['username' => '', 'password' => 'secret']);
        $request = $this->makeRequest('POST', JWTAuthenticator::LOGIN_ROUTE, null, null, (string) $body);

        $this->expectException(AuthenticationException::class);
        $this->authenticator->authenticate($request);
    }

    /** @test (15) Empty password → AuthenticationException */
    public function testAuthenticateWithEmptyPasswordThrows(): void
    {
        $body    = json_encode(['username' => 'alice', 'password' => '']);
        $request = $this->makeRequest('POST', JWTAuthenticator::LOGIN_ROUTE, null, null, (string) $body);

        $this->expectException(AuthenticationException::class);
        $this->authenticator->authenticate($request);
    }

    /** @test (16) Valid credentials → Passport with PasswordCredentials */
    public function testAuthenticateWithValidCredentialsReturnsPassportWithPasswordCredentials(): void
    {
        $body    = json_encode(['username' => 'alice', 'password' => 'secret']);
        $request = $this->makeRequest('POST', JWTAuthenticator::LOGIN_ROUTE, null, null, (string) $body);

        $passport = $this->authenticator->authenticate($request);

        self::assertInstanceOf(Passport::class, $passport);
        self::assertTrue($passport->hasBadge(PasswordCredentials::class));
        self::assertTrue($passport->getAttribute('is_login'));
    }

    // =========================================================================
    // onAuthenticationSuccess()
    // =========================================================================

    /** @test (17) Not login route → null */
    public function testOnAuthenticationSuccessNonLoginRouteReturnsNull(): void
    {
        $user    = $this->makeUser();
        $token   = $this->makeJWTToken($user);
        $request = $this->makeRequest('GET', 'api_resource');

        $result = $this->authenticator->onAuthenticationSuccess($request, $token, 'main');
        self::assertNull($result);
    }

    /** @test (18) Not a JWTAuthenticationToken → null */
    public function testOnAuthenticationSuccessNonJWTTokenReturnsNull(): void
    {
        $token   = $this->createStub(TokenInterface::class);
        $request = $this->makeRequest('POST', JWTAuthenticator::LOGIN_ROUTE);

        $result = $this->authenticator->onAuthenticationSuccess($request, $token, 'main');
        self::assertNull($result);
    }

    /** @test (19) Mobile client login → JsonResponse with token in body */
    public function testOnAuthenticationSuccessMobileClientReturnsJsonWithTokenInBody(): void
    {
        $user  = $this->makeUser();
        $token = $this->makeJWTToken($user, 'access.token.value');
        $token->setAttribute('refreshToken', 'refresh.token.value');
        $token->setAttribute('accessTokenExpiresAt', time() + 3600);

        // X-Client-Type: android → isMobileClient() = true
        $request  = $this->makeRequest('POST', JWTAuthenticator::LOGIN_ROUTE, null, null, '', 'android');
        $response = $this->authenticator->onAuthenticationSuccess($request, $token, 'main');

        self::assertNotNull($response);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('token', $data);
        self::assertSame('access.token.value', $data['token']);
        self::assertArrayHasKey('refreshToken', $data);
    }

    /** @test (20) Web client login, all cookies present → JsonResponse with cookies */
    public function testOnAuthenticationSuccessWebClientWithAllCookiesSetsCookies(): void
    {
        $user  = $this->makeUser();
        $token = $this->makeJWTToken($user, 'access.token.value');
        $token->setAttribute('refreshToken', 'refresh.token.value');
        $token->setAttribute('accessTokenExpiresAt', time() + 3600);
        $token->setAttribute('refreshTokenExpiresAt', time() + 7200);

        // No X-Client-Type header → web client
        $request  = $this->makeRequest('POST', JWTAuthenticator::LOGIN_ROUTE);
        $response = $this->authenticator->onAuthenticationSuccess($request, $token, 'main');

        self::assertNotNull($response);
        $cookies     = $response->headers->getCookies();
        $cookieNames = array_map(fn ($c) => $c->getName(), $cookies);
        self::assertContains(JWTAuthenticator::AUTH_COOKIE, $cookieNames);
        self::assertContains(JWTAuthenticator::REFRESH_COOKIE, $cookieNames);
        self::assertContains(JWTAuthenticator::MERCURE_COOKIE, $cookieNames);
    }

    /** @test (21) Web client login, missing refreshToken → response without cookies */
    public function testOnAuthenticationSuccessWebClientMissingRefreshTokenNoCookies(): void
    {
        $user  = $this->makeUser();
        $token = $this->makeJWTToken($user, 'access.token.value');
        // refreshToken attribute NOT set → getTokenAttributeSafely() returns null

        $request  = $this->makeRequest('POST', JWTAuthenticator::LOGIN_ROUTE);
        $response = $this->authenticator->onAuthenticationSuccess($request, $token, 'main');

        self::assertNotNull($response);
        self::assertEmpty($response->headers->getCookies());
    }

    // =========================================================================
    // onAuthenticationFailure()
    // =========================================================================

    /** @test (22) Non-login route → logs warning, returns 401 */
    public function testOnAuthenticationFailureNonLoginRouteReturns401(): void
    {
        $this->translator->method('trans')->willReturn('Auth error');
        $exception = new AuthenticationException('Auth error');
        $request   = $this->makeRequest('GET', 'api_resource');

        $response = $this->authenticator->onAuthenticationFailure($request, $exception);

        self::assertNotNull($response);
        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    /** @test (23) Login route → logs warning + audit log, returns 401 */
    public function testOnAuthenticationFailureLoginRouteLogsAuditAndReturns401(): void
    {
        $this->translator->method('trans')->willReturn('Invalid credentials');
        $exception = new AuthenticationException('Invalid credentials');

        $body    = json_encode(['username' => 'alice']);
        $request = $this->makeRequest('POST', JWTAuthenticator::LOGIN_ROUTE, null, null, (string) $body);

        $this->auditLogger->expects(self::once())
            ->method('log')
            ->with('auth.login_failed', self::arrayHasKey('username'));

        $response = $this->authenticator->onAuthenticationFailure($request, $exception);

        self::assertNotNull($response);
        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    // =========================================================================
    // createToken()
    // =========================================================================

    /** @test (24) User is not App\Entity\User → AuthenticationException */
    public function testCreateTokenWithNonUserInstanceThrows(): void
    {
        $passport = $this->createStub(Passport::class);
        $nonUser  = $this->createStub(\Symfony\Component\Security\Core\User\UserInterface::class);
        $passport->method('getUser')->willReturn($nonUser);
        $passport->method('getAttribute')->willReturn(null);

        $this->expectException(AuthenticationException::class);
        $this->authenticator->createToken($passport, 'main');
    }

    /** @test (25) Existing JWT (is_login=false) → JWTAuthenticationToken with existing token */
    public function testCreateTokenExistingJwtReturnsJWTAuthenticationToken(): void
    {
        $user     = $this->makeUser();
        $passport = $this->createStub(Passport::class);
        $passport->method('getUser')->willReturn($user);
        $passport->method('getAttribute')->willReturnMap([
            ['is_login', false],
            ['token', 'existing.jwt.token'],
        ]);

        $token = $this->authenticator->createToken($passport, 'main');

        self::assertInstanceOf(JWTAuthenticationToken::class, $token);
        /** @var JWTAuthenticationToken $token */
        self::assertSame('existing.jwt.token', $token->getCredentials());
    }

    /** @test (26) Login flow (is_login=true), token pair generated → JWTAuthenticationToken */
    public function testCreateTokenLoginFlowGeneratesNewTokenPair(): void
    {
        $user     = $this->makeUser();
        $passport = $this->createStub(Passport::class);
        $passport->method('getUser')->willReturn($user);
        $passport->method('getAttribute')->willReturnMap([
            ['is_login', true],
        ]);

        // The real TokenGeneratorService will call em->persist() and flush()
        // which are no-ops on the mock EM — this is fine for our test.
        $token = $this->authenticator->createToken($passport, 'main');

        self::assertInstanceOf(JWTAuthenticationToken::class, $token);
        /** @var JWTAuthenticationToken $token */
        self::assertNotEmpty($token->getCredentials()); // a real JWT string
        self::assertNotNull($token->getAttribute('refreshToken'));
        self::assertNotNull($token->getAttribute('accessTokenExpiresAt'));
    }

    /** @test (27) Login flow, token generation fails → AuthenticationException */
    public function testCreateTokenLoginFlowFailureThrowsAuthenticationException(): void
    {
        // Build an authenticator with a broken EM that throws on flush
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('flush')->willThrowException(new RuntimeException('DB failure'));

        $brokenTokenGenerator = new TokenGeneratorService(
            $this->jwtManager,
            $em,
            $this->tenantContext,
            3600,
            86400
        );

        $brokenAuthenticator = new JWTAuthenticator(
            $this->userProvider,
            $this->jwtManager,
            $this->logger,
            $brokenTokenGenerator,
            $this->clientDetector,
            $this->cookieFactory,
            $this->mercureTokenService,
            $this->translator,
            $this->auditLogger,
            $this->tenantContext,
        );

        $user     = $this->makeUser();
        $passport = $this->createStub(Passport::class);
        $passport->method('getUser')->willReturn($user);
        $passport->method('getAttribute')->willReturnMap([
            ['is_login', true],
        ]);

        $this->logger->expects(self::once())->method('error');

        $this->expectException(AuthenticationException::class);
        $brokenAuthenticator->createToken($passport, 'main');
    }

    // =========================================================================
    // start()
    // =========================================================================

    /** @test (28) With auth exception → 401 with exception message */
    public function testStartWithAuthExceptionReturns401WithExceptionMessage(): void
    {
        $exception = new AuthenticationException('Session expired');
        $request   = $this->makeRequest('GET', 'api_resource');

        $response = $this->authenticator->start($request, $exception);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertSame('Session expired', $data['message']);
    }

    /** @test (29) Without auth exception → 401 with default message */
    public function testStartWithoutAuthExceptionReturnsDefaultMessage(): void
    {
        $request = $this->makeRequest('GET', 'api_resource');

        $response = $this->authenticator->start($request);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertSame('Authentication Required', $data['message']);
    }
}
