<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Tests\Fixtures\Collection;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\LaravelUtils\Tests\Fixtures\Enums\TestLanguage;

final class TestLanguageCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(TestLanguage::class);
    }

    public function hasLanguage(TestLanguage $language): bool
    {
        return $this->contains($language);
    }

    public function toCodes(): array
    {
        return array_map(fn (TestLanguage $lang) => $lang->value, $this->toArray());
    }

    public function toNativeLabels(): array
    {
        return array_map(fn (TestLanguage $lang) => $lang->nativeLabel(), $this->toArray());
    }

    public function getPrimary(): ?TestLanguage
    {
        $items = $this->toArray();

        return ! empty($items) ? $items[0] : null;
    }

    public function getSupportedLanguages(): self
    {
        return $this->filter(fn (TestLanguage $lang) => TestLanguage::isSupportedLanguage($lang));
    }

    public static function fromCodes(array $codes): self
    {
        $collection = new self;
        foreach ($codes as $code) {
            $lang = TestLanguage::tryFrom($code);
            if ($lang !== null) {
                $collection->add($lang);
            }
        }

        return $collection;
    }
}
