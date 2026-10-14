<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Actions;

use App\Domain\Integrations\Data\EmailSettingsData;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Integrations\Support\RuntimeMailer;
use App\Support\Actors\Actor;
use Illuminate\Database\ConnectionInterface;

/**
 * Stores the outgoing email connection in the vault (ADR-0015, D9: any SMTP provider). The
 * audit log records that the password changed, never its value.
 */
final readonly class SaveEmailSettings
{
    public function __construct(private ConnectionInterface $db, private RuntimeMailer $mailer) {}

    public function handle(Actor $actor, EmailSettingsData $data): IntegrationConnection
    {
        $connection = $this->db->transaction(function () use ($actor, $data): IntegrationConnection {
            $connection = RuntimeMailer::connection() ?? new IntegrationConnection(['type' => 'email', 'name' => 'default']);
            $passwordChanged = $data->password !== null && $data->password !== '';

            $connection->fill([
                'config' => [
                    'host' => mb_strtolower($data->host),
                    'port' => $data->port,
                    'security' => $data->security,
                    'username' => $data->username,
                    'from_address' => mb_strtolower($data->fromAddress),
                    'from_name' => $data->fromName,
                ],
                'status' => $connection->status === 'not_configured' ? 'unverified' : $connection->status,
            ]);

            if ($passwordChanged) {
                $connection->credentials = ['password' => (string) $data->password];
            }

            $connection->save();

            if ($passwordChanged) {
                activity('integrations')->causedBy($actor->user)->performedOn($connection)->event('credentials.changed')
                    ->withProperties(['keys' => ['password']])->log('Email password changed');
            }

            return $connection;
        });

        $this->mailer->bump();

        return $connection;
    }
}
