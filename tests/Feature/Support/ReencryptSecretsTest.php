<?php

declare(strict_types=1);

use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Integrations\Support\EncryptedColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

it('re-encrypts stored secrets with the new key after a rotation', function (): void {
    $oldKey = 'base64:'.base64_encode(random_bytes(32));
    $newKey = 'base64:'.base64_encode(random_bytes(32));
    $use = function (string $key, array $previous = []): void {
        config(['app.key' => $key, 'app.previous_keys' => $previous]);
        app()->forgetInstance('encrypter');
        Model::encryptUsing(resolve('encrypter'));
    };

    $use($oldKey);
    $connection = IntegrationConnection::query()->create(['type' => 'email', 'name' => 'default', 'credentials' => ['password' => 'smtp-secret']]);
    $before = DB::table('integration_connections')->value('credentials');

    $use($newKey, [$oldKey]);
    $this->artisan('mawred:reencrypt')->assertSuccessful();
    $after = DB::table('integration_connections')->value('credentials');

    $use($newKey);
    expect($after)->not->toBe($before)
        ->and($connection->fresh()?->credentials)->toBe(['password' => 'smtp-secret']);
    Model::encryptUsing(null);
});

it('lists every encrypted cast in the registry', function (): void {
    foreach (EncryptedColumns::all() as $model => $columns) {
        $encrypted = array_keys(array_filter((new $model)->getCasts(), fn (string $cast): bool => str_starts_with($cast, 'encrypted')));
        expect($columns)->toEqualCanonicalizing($encrypted);
    }

    $models = collect(File::allFiles(app_path('Domain')))->filter(fn ($file) => str_contains($file->getPath(), '/Models'))
        ->map(fn ($file) => 'App\\'.str_replace(['/', '.php'], ['\\', ''], Str::after($file->getPathname(), app_path().'/')))
        ->filter(fn (string $class): bool => class_exists($class) && is_subclass_of($class, Model::class) && ! (new ReflectionClass($class))->isAbstract())
        ->filter(fn (string $class): bool => collect((new $class)->getCasts())->contains(fn (string $cast): bool => str_starts_with($cast, 'encrypted')));

    expect($models->sort()->values()->all())->toEqualCanonicalizing(array_keys(EncryptedColumns::all()));
});
