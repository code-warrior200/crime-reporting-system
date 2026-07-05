<?php

class SmtpMailer
{
    private array $config;
    private string $lastError = '';
    /** @var resource|null */
    private $socket = null;

    public function __construct(array $config)
    {
        $this->config = array_merge([
            'host' => '',
            'port' => 587,
            'encryption' => 'tls',
            'username' => '',
            'password' => '',
            'from_email' => '',
            'from_name' => 'Crime Reporting System',
            'timeout' => 15,
        ], $config);
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function send(string $toEmail, string $toName, string $subject, string $body): bool
    {
        $this->lastError = '';

        if (!$this->hasRequiredConfig()) {
            return false;
        }

        try {
            $this->connect();
            $this->expect([220]);
            $this->command('EHLO localhost', [250]);

            if (strtolower((string) $this->config['encryption']) === 'tls') {
                $this->command('STARTTLS', [220]);
                if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('Unable to enable SMTP TLS encryption.');
                }
                $this->command('EHLO localhost', [250]);
            }

            $this->command('AUTH LOGIN', [334]);
            $this->command(base64_encode((string) $this->config['username']), [334]);
            $this->command(base64_encode((string) $this->config['password']), [235]);

            $fromEmail = $this->sanitizeEmail((string) $this->config['from_email']);
            $toEmail = $this->sanitizeEmail($toEmail);

            $this->command("MAIL FROM:<{$fromEmail}>", [250]);
            $this->command("RCPT TO:<{$toEmail}>", [250, 251]);
            $this->command('DATA', [354]);
            $this->write($this->buildMessage($toEmail, $toName, $subject, $body));
            $this->expect([250]);
            $this->command('QUIT', [221]);
            $this->close();

            return true;
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            $this->close();
            return false;
        }
    }

    private function hasRequiredConfig(): bool
    {
        foreach (['host', 'username', 'password', 'from_email'] as $key) {
            if (trim((string) ($this->config[$key] ?? '')) === '') {
                $this->lastError = "Missing SMTP config value: {$key}.";
                return false;
            }
        }

        return true;
    }

    private function connect(): void
    {
        $host = (string) $this->config['host'];
        $port = (int) $this->config['port'];
        $timeout = (int) $this->config['timeout'];
        $errno = 0;
        $errstr = '';

        $this->socket = fsockopen($host, $port, $errno, $errstr, $timeout);
        if (!$this->socket) {
            throw new RuntimeException("Unable to connect to SMTP server: {$errstr} ({$errno}).");
        }

        stream_set_timeout($this->socket, $timeout);
    }

    private function buildMessage(string $toEmail, string $toName, string $subject, string $body): string
    {
        $fromEmail = $this->sanitizeEmail((string) $this->config['from_email']);
        $fromName = $this->sanitizeHeader((string) $this->config['from_name']);
        $toName = $this->sanitizeHeader($toName);
        $subject = $this->sanitizeHeader($subject);

        $headers = [
            'Date: ' . date(DATE_RFC2822),
            "From: {$fromName} <{$fromEmail}>",
            "To: {$toName} <{$toEmail}>",
            "Subject: {$subject}",
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];

        $message = implode("\r\n", $headers) . "\r\n\r\n" . $this->normalizeBody($body) . "\r\n.";

        return $message . "\r\n";
    }

    private function normalizeBody(string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        $lines = explode("\n", $body);
        $lines = array_map(static function (string $line): string {
            return str_starts_with($line, '.') ? '.' . $line : $line;
        }, $lines);

        return implode("\r\n", $lines);
    }

    private function sanitizeHeader(string $value): string
    {
        return trim(str_replace(["\r", "\n"], '', $value));
    }

    private function sanitizeEmail(string $value): string
    {
        return filter_var(trim($value), FILTER_SANITIZE_EMAIL);
    }

    private function command(string $command, array $expectedCodes): string
    {
        $this->write($command . "\r\n");
        return $this->expect($expectedCodes);
    }

    private function write(string $payload): void
    {
        if (!$this->socket || fwrite($this->socket, $payload) === false) {
            throw new RuntimeException('Unable to write to SMTP server.');
        }
    }

    private function expect(array $expectedCodes): string
    {
        $response = '';

        while ($line = fgets($this->socket, 515)) {
            $response .= $line;
            if (preg_match('/^(\d{3})\s/', $line, $matches)) {
                $code = (int) $matches[1];
                if (!in_array($code, $expectedCodes, true)) {
                    throw new RuntimeException("Unexpected SMTP response: {$response}");
                }
                return $response;
            }
        }

        throw new RuntimeException('No response from SMTP server.');
    }

    private function close(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->socket = null;
    }
}

function loadSmtpConfig(): array
{
    $localConfig = __DIR__ . '/smtp_config.local.php';
    if (is_file($localConfig)) {
        return require $localConfig;
    }

    return [
        'host' => getenv('SMTP_HOST') ?: 'smtp-relay.brevo.com',
        'port' => (int) (getenv('SMTP_PORT') ?: 587),
        'encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls',
        'username' => getenv('SMTP_USERNAME') ?: '',
        'password' => getenv('SMTP_PASSWORD') ?: '',
        'from_email' => getenv('SMTP_FROM_EMAIL') ?: '',
        'from_name' => getenv('SMTP_FROM_NAME') ?: 'Crime Reporting System',
        'timeout' => 15,
    ];
}
