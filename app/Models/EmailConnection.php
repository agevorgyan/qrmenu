<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

/**
 * Model representing an email transport / SMTP connection.
 *
 * @property int $id
 * @property string $name
 * @property string $transport
 * @property string|null $host
 * @property int $port
 * @property string|null $encryption
 * @property string|null $username
 * @property string|null $password
 * @property int $timeout
 * @property bool $is_active
 * @property bool $is_default
 * @property Carbon|null $last_tested_at
 * @property string|null $last_test_status
 * @property string|null $last_error
 * @property array|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class EmailConnection extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'transport',
        'host',
        'port',
        'encryption',
        'username',
        'password',
        'timeout',
        'is_active',
        'is_default',
        'last_tested_at',
        'last_test_status',
        'last_error',
        'metadata',
    ];

    /**
     * Hidden from array and JSON serialization.
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Attribute casts.
     */
    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'timeout' => 'integer',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'last_tested_at' => 'datetime',
            'metadata' => 'array',
            'password' => 'encrypted',
        ];
    }

    /**
     * Protect sensitive credentials in dumps and logs.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        $attributes = $this->getAttributes();
        $attributes['password'] = '[REDACTED]';

        return $attributes;
    }

    /**
     * Sender identities assigned to this connection.
     */
    public function senders(): HasMany
    {
        return $this->hasMany(EmailSenderIdentity::class, 'email_connection_id');
    }

    /**
     * Routing rules explicitly bound to this connection.
     */
    public function routingRules(): HasMany
    {
        return $this->hasMany(EmailRoutingRule::class, 'email_connection_id');
    }

    /**
     * Outgoing email logs dispatched via this connection.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(EmailLog::class, 'email_connection_id');
    }

    /**
     * Generate safe masked password for UI display.
     */
    public function maskedPassword(): string
    {
        $raw = $this->password;
        if (empty($raw)) {
            return '';
        }

        $len = strlen($raw);
        if ($len <= 6) {
            return '••••••••';
        }

        return substr($raw, 0, 2).'••••••••'.substr($raw, -2);
    }

    /**
     * Return safe masked summary without raw credentials.
     *
     * @return array<string, mixed>
     */
    public function toMaskedSummary(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'transport' => $this->transport,
            'host' => $this->host,
            'port' => $this->port,
            'encryption' => $this->encryption,
            'username' => $this->username,
            'has_password' => ! empty($this->password),
            'masked_password' => $this->maskedPassword(),
            'is_active' => (bool) $this->is_active,
            'is_default' => (bool) $this->is_default,
            'last_test_status' => $this->last_test_status,
            'last_tested_at' => $this->last_tested_at?->toIso8601String(),
        ];
    }

    /**
     * Build Laravel mailer configuration array for dynamic runtime registration.
     *
     * @return array<string, mixed>
     */
    public function toMailerConfig(): array
    {
        $transport = strtolower($this->transport ?: 'smtp');

        if ($transport === 'log') {
            return [
                'transport' => 'log',
                'channel' => config('mail.mailers.log.channel', 'stack'),
            ];
        }

        if ($transport === 'array') {
            return [
                'transport' => 'array',
            ];
        }

        if ($transport === 'sendmail') {
            return [
                'transport' => 'sendmail',
                'path' => config('mail.mailers.sendmail.path', '/usr/sbin/sendmail -bs -i'),
            ];
        }

        $scheme = match (strtolower((string) $this->encryption)) {
            'ssl', 'smtps' => 'smtps',
            default => null,
        };

        return [
            'transport' => 'smtp',
            'scheme' => $scheme,
            'host' => $this->host,
            'port' => (int) ($this->port ?: 587),
            'encryption' => in_array(strtolower((string) $this->encryption), ['tls', 'ssl', 'starttls'], true) ? strtolower((string) $this->encryption) : null,
            'username' => $this->username,
            'password' => $this->password,
            'timeout' => (int) ($this->timeout ?: 15),
            'local_domain' => parse_url((string) config('app.url', 'http://localhost'), PHP_URL_HOST) ?: 'localhost',
        ];
    }

    /**
     * Perform live socket & authentication test without sending an email.
     *
     * @return array{success: bool, message: string, duration_ms: int}
     */
    public function testConnection(): array
    {
        $startTime = microtime(true);

        try {
            if ($this->transport === 'log' || $this->transport === 'array') {
                $duration = (int) round((microtime(true) - $startTime) * 1000);
                $this->update([
                    'last_tested_at' => now(),
                    'last_test_status' => 'success',
                    'last_error' => null,
                ]);

                return [
                    'success' => true,
                    'message' => "Connection '{$this->name}' ({$this->transport}) is ready.",
                    'duration_ms' => $duration,
                ];
            }

            if ($this->transport === 'sendmail') {
                $path = config('mail.mailers.sendmail.path', '/usr/sbin/sendmail -bs -i');
                $bin = explode(' ', (string) $path)[0];
                $duration = (int) round((microtime(true) - $startTime) * 1000);

                if (! file_exists($bin) && ! is_executable($bin)) {
                    throw new \RuntimeException("Sendmail binary not found at {$bin}");
                }

                $this->update([
                    'last_tested_at' => now(),
                    'last_test_status' => 'success',
                    'last_error' => null,
                ]);

                return [
                    'success' => true,
                    'message' => "Sendmail binary verified at {$bin}.",
                    'duration_ms' => $duration,
                ];
            }

            // Real SMTP handshake and authentication test
            $host = $this->host ?: '127.0.0.1';
            $port = (int) ($this->port ?: 587);
            $tls = in_array(strtolower((string) $this->encryption), ['tls', 'ssl', 'starttls'], true);

            $transport = new EsmtpTransport($host, $port, $tls);

            if ($this->username) {
                $transport->setUsername($this->username);
            }
            if ($this->password) {
                $transport->setPassword($this->password);
            }

            // Initiate socket and ESMTP greeting/auth
            $transport->start();
            $transport->stop();

            $duration = (int) round((microtime(true) - $startTime) * 1000);

            $this->update([
                'last_tested_at' => now(),
                'last_test_status' => 'success',
                'last_error' => null,
            ]);

            return [
                'success' => true,
                'message' => "SMTP handshake and authentication succeeded ({$host}:{$port}).",
                'duration_ms' => $duration,
            ];
        } catch (\Throwable $e) {
            $duration = (int) round((microtime(true) - $startTime) * 1000);
            $rawError = $e->getMessage();

            // Sanitize: remove any passwords or sensitive substrings
            $cleanError = $this->password ? str_replace($this->password, '••••', $rawError) : $rawError;

            $this->update([
                'last_tested_at' => now(),
                'last_test_status' => 'failed',
                'last_error' => $cleanError,
            ]);

            return [
                'success' => false,
                'message' => "Connection test failed: {$cleanError}",
                'duration_ms' => $duration,
            ];
        }
    }
}
