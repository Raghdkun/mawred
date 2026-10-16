<?php

declare(strict_types=1);

use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Integrations\Support\EncryptedColumns;
use App\Domain\People\Enums\HistoryEvent;
use App\Domain\People\Models\Employee;
use App\Domain\People\Models\EmploymentHistoryEntry;
use App\Support\Money\EncryptedMoneyCast;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
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

/**
 * The columns a model encrypts with APP_KEY: `encrypted*` casts, and the `<name>_minor` column
 * behind an EncryptedMoneyCast.
 *
 * @return list<string>
 */
function reencryptColumns(Model $model): array
{
    $columns = [];

    foreach ($model->getCasts() as $attribute => $cast) {
        if (str_starts_with($cast, 'encrypted')) {
            $columns[] = $attribute;
        } elseif ($cast === EncryptedMoneyCast::class) {
            $columns[] = "{$attribute}_minor";
        }
    }

    return $columns;
}

it('lists every encrypted cast in the registry', function (): void {
    foreach (EncryptedColumns::all() as $model => $columns) {
        expect($columns)->toEqualCanonicalizing(reencryptColumns(new $model));
    }

    $models = collect(File::allFiles(app_path('Domain')))->filter(fn ($file) => str_contains($file->getPath(), '/Models'))
        ->map(fn ($file) => 'App\\'.str_replace(['/', '.php'], ['\\', ''], Str::after($file->getPathname(), app_path().'/')))
        ->filter(fn (string $class): bool => class_exists($class) && is_subclass_of($class, Model::class) && ! (new ReflectionClass($class))->isAbstract())
        ->filter(fn (string $class): bool => reencryptColumns(new $class) !== []);

    expect($models->sort()->values()->all())->toEqualCanonicalizing(array_keys(EncryptedColumns::all()));
});

it('re-encrypts salaries with the new key after a rotation', function (): void {
    $oldKey = 'base64:'.base64_encode(random_bytes(32));
    $newKey = 'base64:'.base64_encode(random_bytes(32));
    $use = function (string $key, array $previous = []): void {
        config(['app.key' => $key, 'app.previous_keys' => $previous]);
        app()->forgetInstance('encrypter');
        Crypt::clearResolvedInstance('encrypter');
    };

    $use($oldKey);
    $entry = EmploymentHistoryEntry::query()->create([
        'employee_id' => Employee::factory()->create()->id, 'event' => HistoryEvent::Hired, 'effective_on' => '2026-01-01', 'salary' => new Money(850000, 'SAR'),
    ]);
    $before = DB::table('employment_history')->value('salary_minor');

    $use($newKey, [$oldKey]);
    $this->artisan('mawred:reencrypt')->assertSuccessful();

    $use($newKey);
    expect(DB::table('employment_history')->value('salary_minor'))->not->toBe($before)
        ->and($entry->fresh()?->salary)->toEqual(new Money(850000, 'SAR'));
});
