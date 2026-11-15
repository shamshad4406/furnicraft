<?php

namespace App\Mail\Transport;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

/**
 * Sends mail through Zoho ZeptoMail's HTTP API.
 *
 * A Symfony transport rather than anything application-specific, so the rest
 * of the app keeps using Mail::to(...)->send(...) and knows nothing about
 * Zoho. Swapping providers is a config change.
 *
 * Why the API and not SMTP: shared hosting often blocks outbound SMTP ports,
 * and an HTTP call gives a response body we can log when a message is
 * rejected, instead of a timeout.
 */
class ZeptoMailTransport extends AbstractTransport
{
    public function __construct(
        protected HttpFactory $http,
        protected string $apiKey,
        protected string $endpoint,
        protected int $timeout = 15,
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return 'zeptomail';
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $response = $this->request()->post($this->endpoint, $this->payload($email));

        if ($response->failed()) {
            throw new TransportException(
                'ZeptoMail rejected the message ('.$response->status().'): '.$this->reason($response->json(), $response->body())
            );
        }

        // ZeptoMail returns a request id; keeping it on the message means a
        // bounce can be traced back to the order that sent it.
        $message->setMessageId((string) ($response->json('request_id') ?? $message->getMessageId()));
    }

    protected function request(): PendingRequest
    {
        return $this->http
            ->withHeaders([
                // The key issued by Zoho already carries its own prefix; add
                // it only when it is missing, so either form in .env works.
                'Authorization' => str_starts_with($this->apiKey, 'Zoho-enczapikey')
                    ? $this->apiKey
                    : 'Zoho-enczapikey '.$this->apiKey,
                'Accept' => 'application/json',
            ])
            ->timeout($this->timeout)
            ->asJson();
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(Email $email): array
    {
        $from = $email->getFrom()[0] ?? null;

        if (! $from) {
            throw new TransportException('ZeptoMail needs a from address.');
        }

        $payload = [
            'from' => array_filter([
                'address' => $from->getAddress(),
                'name' => $from->getName() ?: null,
            ]),
            'to' => $this->recipients($email->getTo()),
            'subject' => $email->getSubject() ?? '',
        ];

        foreach (['cc' => $email->getCc(), 'bcc' => $email->getBcc()] as $field => $addresses) {
            if ($addresses !== []) {
                $payload[$field] = $this->recipients($addresses);
            }
        }

        if ($email->getReplyTo() !== []) {
            $payload['reply_to'] = array_map(fn (Address $address) => array_filter([
                'address' => $address->getAddress(),
                'name' => $address->getName() ?: null,
            ]), $email->getReplyTo());
        }

        if ($html = $email->getHtmlBody()) {
            $payload['htmlbody'] = $this->asString($html);
        }

        if ($text = $email->getTextBody()) {
            $payload['textbody'] = $this->asString($text);
        }

        // Neither body set would be silently accepted and delivered blank.
        if (! isset($payload['htmlbody']) && ! isset($payload['textbody'])) {
            throw new TransportException('ZeptoMail needs an HTML or a text body.');
        }

        if ($attachments = $this->attachments($email)) {
            $payload['attachments'] = $attachments;
        }

        return $payload;
    }

    /**
     * @param  Address[]  $addresses
     * @return array<int, array{email_address: array<string, string>}>
     */
    protected function recipients(array $addresses): array
    {
        if ($addresses === []) {
            throw new TransportException('ZeptoMail needs at least one recipient.');
        }

        return array_map(fn (Address $address) => [
            'email_address' => array_filter([
                'address' => $address->getAddress(),
                'name' => $address->getName() ?: null,
            ]),
        ], $addresses);
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function attachments(Email $email): array
    {
        $attachments = [];

        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $disposition = $headers->getHeaderBody('Content-Disposition');

            $file = [
                'content' => base64_encode($attachment->getBody()),
                'mime_type' => $headers->get('Content-Type')?->getBody() ?? 'application/octet-stream',
                'name' => $attachment->getFilename() ?? 'attachment',
            ];

            // An inline image is referenced from the HTML by its cid, so it
            // travels in its own field rather than as a download.
            if ($disposition === 'inline') {
                $file['cid'] = $attachment->getContentId();
            }

            $attachments[] = $file;
        }

        return $attachments;
    }

    /**
     * Symfony bodies can arrive as a resource; the API wants a string.
     */
    protected function asString(mixed $body): string
    {
        return is_resource($body) ? (string) stream_get_contents($body) : (string) $body;
    }

    /**
     * ZeptoMail nests the useful part of an error; fall back to the raw body
     * so nothing is lost when the shape is not what we expect.
     *
     * @param  array<string, mixed>|null  $json
     */
    protected function reason(?array $json, string $body): string
    {
        if (! is_array($json)) {
            return \Illuminate\Support\Str::limit($body, 400);
        }

        $details = $json['error']['details'][0]['message']
            ?? $json['error']['message']
            ?? $json['message']
            ?? null;

        return $details ? (string) $details : \Illuminate\Support\Str::limit($body, 400);
    }
}
