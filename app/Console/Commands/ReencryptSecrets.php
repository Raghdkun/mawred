<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Integrations\Support\EncryptedColumns;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\ConnectionInterface;

/**
 * Re-encrypts every encrypted column with the current APP_KEY after a rotation (ADR-0015).
 * Old values decrypt through APP_PREVIOUS_KEYS. Writes go straight to the column, so no model
 * events or audit entries fire, and running it twice is harmless.
 */
#[Signature('mawred:reencrypt {--dry-run : Count what would change without writing}')]
#[Description('Re-encrypt stored secrets with the current APP_KEY (run after rotating it)')]
final class ReencryptSecrets extends Command
{
    public function handle(Encrypter $encrypter, ConnectionInterface $db): int
    {
        $failures = 0;

        foreach (EncryptedColumns::all() as $model => $columns) {
            $table = (new $model)->getTable();
            $count = 0;

            $db->table($table)->select(['id', ...$columns])->orderBy('id')->chunkById(500, function ($rows) use ($db, $table, $columns, $encrypter, &$count, &$failures): void {
                foreach ($rows as $row) {
                    $id = is_int($row->id) || is_string($row->id) ? $row->id : 0;
                    $changes = [];

                    foreach ($columns as $column) {
                        $value = $row->{$column} ?? null;

                        if (! is_string($value) || $value === '') {
                            continue;
                        }

                        try {
                            $changes[$column] = $encrypter->encryptString($encrypter->decryptString($value));
                        } catch (DecryptException) {
                            $failures++;
                            $this->components->error("{$table}#{$id}.{$column} could not be decrypted with the current or previous keys.");
                        }
                    }

                    if ($changes !== []) {
                        $count++;

                        if (! $this->option('dry-run')) {
                            $db->table($table)->where('id', $id)->update($changes);
                        }
                    }
                }
            });

            $this->components->twoColumnDetail($table, (string) $count);
        }

        if ($failures > 0) {
            $this->components->error("{$failures} values could not be decrypted. Keep the old key in APP_PREVIOUS_KEYS until they are fixed.");

            return self::FAILURE;
        }

        $this->components->info($this->option('dry-run') ? 'Dry run: nothing written.' : 'Every secret now uses the current key. You can drop the old key from APP_PREVIOUS_KEYS.');

        return self::SUCCESS;
    }
}
