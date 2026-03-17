<?php

declare(strict_types=1);

namespace App\ApiPlatform;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Decorates the API Platform Hydra documentation normalizer so that
 * ApiProperty description strings that look like translation keys
 * (e.g. "category.name.label") are resolved to their translated text
 * and written into hydra:title — the field the frontend reads for
 * human-readable property labels.
 *
 * Register in services.yaml:
 *
 *   App\ApiPlatform\TranslatedDocumentationNormalizer:
 *       decorates: api_platform.hydra.normalizer.documentation
 *       arguments:
 *           $inner: '@.inner'
 *           $translator: '@translator'
 *           $requestStack: '@request_stack'
 *           $apiLocale: '%env(APP_API_LOCALE)%'
 */
final class TranslatedDocumentationNormalizer implements NormalizerInterface
{
    public function __construct(
        private readonly NormalizerInterface $inner,
        private readonly TranslatorInterface $translator,
        private readonly RequestStack $requestStack,
        private readonly string $apiLocale = 'auto',
    ) {
    }

    /** @return array<mixed> */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        /** @var array<mixed> $doc */
        $doc = $this->inner->normalize($object, $format, $context);

        $locale = $this->resolveLocale();

        // Handle both prefixed ("hydra:") and unprefixed ("") key styles.
        foreach (['hydra:', ''] as $prefix) {
            $classesKey = $prefix . 'supportedClass';
            if (!isset($doc[$classesKey]) || !\is_array($doc[$classesKey])) {
                continue;
            }

            foreach ($doc[$classesKey] as &$class) {
                if (!\is_array($class)) {
                    continue;
                }

                $propertiesKey = $prefix . 'supportedProperty';
                if (!isset($class[$propertiesKey]) || !\is_array($class[$propertiesKey])) {
                    continue;
                }

                foreach ($class[$propertiesKey] as &$supportedProperty) {
                    if (!\is_array($supportedProperty)) {
                        continue;
                    }

                    $descriptionKey = $prefix . 'description';
                    $titleKey = $prefix . 'title';

                    if (
                        isset($supportedProperty[$descriptionKey])
                        && \is_string($supportedProperty[$descriptionKey])
                        && $this->looksLikeTranslationKey($supportedProperty[$descriptionKey])
                    ) {
                        $translated = $this->translator->trans(
                            $supportedProperty[$descriptionKey],
                            [],
                            'api',
                            $locale,
                        );

                        // Only overwrite hydra:title if the key was actually translated
                        // (trans() returns the key itself when no translation is found).
                        if ($translated !== $supportedProperty[$descriptionKey]) {
                            $supportedProperty[$titleKey] = $translated;
                        }
                    }
                }
                unset($supportedProperty);
            }
            unset($class);
        }

        return $doc;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $this->inner->supportsNormalization($data, $format, $context);
    }

    /** @return array<class-string, bool> */
    public function getSupportedTypes(?string $format): array
    {
        return $this->inner->getSupportedTypes($format);
    }

    /**
     * A string looks like a translation key when it contains at least one dot
     * and has no whitespace — e.g. "category.name.label".
     *
     * Human-readable strings like "The collection of Foo resources" will never
     * match this heuristic, so they are left untouched.
     */
    private function looksLikeTranslationKey(string $value): bool
    {
        return str_contains($value, '.') && !str_contains($value, ' ');
    }

    /**
     * Resolve the locale to use for translation.
     *
     * Priority:
     *   1. APP_API_LOCALE env var (when set to a specific locale, e.g. "es", "en")
     *   2. Accept-Language header from the current request
     *   3. Symfony's default translator locale (fallback for CLI / cache warm-up)
     */
    private function resolveLocale(): string
    {
        if ($this->apiLocale !== 'auto') {
            return $this->apiLocale;
        }
        $request = $this->requestStack->getCurrentRequest();
        if ($request !== null) {
            return $request->getLocale();
        }

        return $this->translator->getLocale();
    }
}
