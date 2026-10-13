<?php

declare(strict_types=1);

namespace App\Providers;

use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider as BaseTypeScriptTransformerServiceProvider;
use Spatie\TypeScriptTransformer\Formatters\PrettierFormatter;
use Spatie\TypeScriptTransformer\Transformers\AttributedClassTransformer;
use Spatie\TypeScriptTransformer\Transformers\EnumTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Spatie\TypeScriptTransformer\Writers\GlobalNamespaceWriter;

/**
 * Generates resources/js/types/generated.d.ts from #[TypeScript]-annotated Data classes and
 * enums, so server shapes are never hand-written on the client. CI fails if it is stale.
 */
final class TypeScriptTransformerServiceProvider extends BaseTypeScriptTransformerServiceProvider
{
    protected function configure(TypeScriptTransformerConfigFactory $config): void
    {
        $config
            ->transformer(AttributedClassTransformer::class)
            ->transformer(EnumTransformer::class)
            ->transformDirectories(app_path('Domain'))
            ->outputDirectory(resource_path('js/types'))
            ->writer(new GlobalNamespaceWriter('generated.d.ts'))
            ->formatter(PrettierFormatter::class);
    }
}
