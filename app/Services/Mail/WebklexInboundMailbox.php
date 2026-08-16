<?php

namespace App\Services\Mail;

use App\Contracts\InboundMailbox;
use App\Data\InboundEmailMessage;
use Carbon\CarbonImmutable;
use App\Models\MailboxAccount;
use RuntimeException;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;

class WebklexInboundMailbox implements InboundMailbox
{
    private ?Client $client = null;
    private ?MailboxAccount $account = null;

    /** @var array<string, Message> */
    private array $messages = [];

    public function forAccount(MailboxAccount $account): self
    {
        $this->close();
        $this->account = $account;

        return $this;
    }

    public function testConnection(): void
    {
        $this->connect();
        $this->close();
    }

    public function recent(int $limit): iterable
    {
        $folder = $this->connect()->getFolder((string) $this->account?->folder);
        if (! $folder) {
            throw new RuntimeException('Cartella IMAP non trovata.');
        }

        $since = now()->subDays((int) config('commerciale-ai.imap.sync_since_days', 14));
        $messages = $folder->query()->unseen()->since($since)->leaveUnread()->setFetchOrderAsc()->limit($limit)->get();
        foreach ($messages as $message) {
            $identifier = (string) $message->getUid();
            $this->messages[$identifier] = $message;
            $from = $message->getFrom()->first();
            $textBody = trim($message->getTextBody());
            $body = $textBody !== '' ? $textBody : $this->htmlToText($message->getHTMLBody());
            $autoSubmitted = mb_strtolower((string) $message->getHeader()->get('auto_submitted'));
            $precedence = mb_strtolower((string) $message->getHeader()->get('precedence'));

            yield new InboundEmailMessage(
                identifier: $identifier,
                messageId: $this->firstHeader($message, 'message_id'),
                inReplyTo: $this->firstHeader($message, 'in_reply_to'),
                references: array_values(array_filter(array_map([$this, 'normalizeMessageId'], $message->getReferences()->all()))),
                fromAddress: mb_strtolower(trim((string) ($from?->mail ?? ''))),
                fromName: filled($from?->personal ?? null) ? trim($from->personal) : null,
                subject: mb_substr(trim((string) $message->getSubject()), 0, 255),
                body: mb_substr($body, 0, 100000),
                receivedAt: CarbonImmutable::instance($message->getDate()->toDate()),
                automated: ($autoSubmitted !== '' && $autoSubmitted !== 'no') || in_array($precedence, ['bulk', 'junk', 'list'], true),
            );
        }
    }

    public function markSeen(string $identifier): void
    {
        $message = $this->messages[$identifier] ?? null;
        if (! $message || ! $message->setFlag('Seen')) {
            throw new RuntimeException('Impossibile contrassegnare il messaggio IMAP come letto.');
        }
    }

    public function close(): void
    {
        if ($this->client) {
            $this->client->disconnect();
            $this->client = null;
            $this->messages = [];
        }
    }

    private function connect(): Client
    {
        if ($this->client) {
            return $this->client;
        }
        if (! $this->account) throw new RuntimeException('Nessuna casella IMAP selezionata.');

        $manager = new ClientManager([]);
        $this->client = $manager->make([
            'host' => $this->account->host,
            'port' => (int) $this->account->port,
            'encryption' => $this->account->encryption ?: false,
            'validate_cert' => (bool) $this->account->validate_cert,
            'username' => $this->account->username,
            'password' => $this->account->password,
            'protocol' => 'imap',
            'authentication' => $this->account->authentication,
            'timeout' => (int) config('commerciale-ai.imap.timeout', 30),
        ]);
        $this->client->connect();

        return $this->client;
    }

    private function firstHeader(Message $message, string $name): ?string
    {
        return $this->normalizeMessageId($message->getHeader()->get($name)->first());
    }

    private function normalizeMessageId(mixed $value): ?string
    {
        $value = trim((string) $value, " \t\n\r\0\x0B<>");

        return $value !== '' ? mb_substr($value, 0, 255) : null;
    }

    private function htmlToText(string $html): string
    {
        $html = preg_replace('/<(br|\/p|\/div|\/li)>/i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
    }
}

