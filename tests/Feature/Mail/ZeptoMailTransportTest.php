<?php

namespace Tests\Feature\Mail;

use App\Mail\Transport\ZeptoMailTransport;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * The ZeptoMail transport builds Zoho's request by hand, so the shape of that
 * request is the thing worth testing: a field in the wrong place is a silent
 * non-delivery, which nobody notices until a customer asks where their order
 * confirmation went.
 */
class ZeptoMailTransportTest extends TestCase
{
    protected const ENDPOINT = 'https://api.zeptomail.in/v1.1/email';

    protected function transport(string $key = 'Zoho-enczapikey test-key'): ZeptoMailTransport
    {
        return new ZeptoMailTransport(app(HttpFactory::class), $key, self::ENDPOINT);
    }

    protected function send(Email $email): void
    {
        $this->transport()->send($email, new Envelope(
            $email->getFrom()[0],
            array_merge($email->getTo(), $email->getCc(), $email->getBcc())
        ));
    }

    protected function basicEmail(): Email
    {
        return (new Email)
            ->from(new Address('support@innobles.com', 'Innobles FurniCraft'))
            ->to(new Address('ravi@example.com', 'Ravi Kumar'))
            ->subject('Your order is confirmed')
            ->html('<p>Thank you for your order.</p>')
            ->text('Thank you for your order.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function sentPayload(): array
    {
        $request = Http::recorded()[0][0] ?? null;

        $this->assertNotNull($request, 'No request was sent to ZeptoMail.');

        return $request->data();
    }

    /* ------------------------------------------------------- the request */

    public function test_it_posts_to_the_configured_endpoint(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['request_id' => 'req-1'], 201)]);

        $this->send($this->basicEmail());

        Http::assertSent(fn ($request) => $request->url() === self::ENDPOINT && $request->method() === 'POST');
    }

    public function test_it_sends_the_api_key_as_the_authorization_header(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['request_id' => 'req-1'], 201)]);

        $this->send($this->basicEmail());

        Http::assertSent(fn ($request) => $request->header('Authorization')[0] === 'Zoho-enczapikey test-key');
    }

    /**
     * Zoho issues the token already prefixed, but someone pasting only the
     * token part should not end up with a 401 and no idea why.
     */
    public function test_a_key_without_the_prefix_still_gets_one(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['request_id' => 'req-1'], 201)]);

        $transport = new ZeptoMailTransport(app(HttpFactory::class), 'bare-token', self::ENDPOINT);
        $email = $this->basicEmail();
        $transport->send($email, new Envelope($email->getFrom()[0], $email->getTo()));

        Http::assertSent(fn ($request) => $request->header('Authorization')[0] === 'Zoho-enczapikey bare-token');
    }

    /* ------------------------------------------------------- the payload */

    public function test_the_payload_carries_the_sender_recipient_and_subject(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['request_id' => 'req-1'], 201)]);

        $this->send($this->basicEmail());

        $payload = $this->sentPayload();

        $this->assertSame('support@innobles.com', $payload['from']['address']);
        $this->assertSame('Innobles FurniCraft', $payload['from']['name']);
        $this->assertSame('ravi@example.com', $payload['to'][0]['email_address']['address']);
        $this->assertSame('Ravi Kumar', $payload['to'][0]['email_address']['name']);
        $this->assertSame('Your order is confirmed', $payload['subject']);
    }

    public function test_both_bodies_are_sent_under_zohos_field_names(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['request_id' => 'req-1'], 201)]);

        $this->send($this->basicEmail());

        $payload = $this->sentPayload();

        $this->assertStringContainsString('Thank you for your order.', $payload['htmlbody']);
        $this->assertStringContainsString('Thank you for your order.', $payload['textbody']);
    }

    public function test_cc_bcc_and_reply_to_are_included_when_set(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['request_id' => 'req-1'], 201)]);

        $email = $this->basicEmail()
            ->cc('manager@innobles.com')
            ->bcc('records@innobles.com')
            ->replyTo(new Address('support@innobles.com', 'Support'));

        $this->send($email);

        $payload = $this->sentPayload();

        $this->assertSame('manager@innobles.com', $payload['cc'][0]['email_address']['address']);
        $this->assertSame('records@innobles.com', $payload['bcc'][0]['email_address']['address']);
        $this->assertSame('support@innobles.com', $payload['reply_to'][0]['address']);
    }

    public function test_optional_fields_are_left_out_rather_than_sent_empty(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['request_id' => 'req-1'], 201)]);

        $this->send($this->basicEmail());

        $payload = $this->sentPayload();

        $this->assertArrayNotHasKey('cc', $payload);
        $this->assertArrayNotHasKey('bcc', $payload);
        $this->assertArrayNotHasKey('reply_to', $payload);
        $this->assertArrayNotHasKey('attachments', $payload);
    }

    public function test_an_attachment_is_base64_encoded(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['request_id' => 'req-1'], 201)]);

        $email = $this->basicEmail()
            ->attach('Invoice contents', 'invoice.txt', 'text/plain');

        $this->send($email);

        $attachment = $this->sentPayload()['attachments'][0];

        $this->assertSame('invoice.txt', $attachment['name']);
        $this->assertSame('Invoice contents', base64_decode($attachment['content']));
        $this->assertStringContainsString('text/plain', $attachment['mime_type']);
    }

    /* --------------------------------------------------------- failures */

    public function test_a_rejected_message_raises_the_reason_zoho_gave(): void
    {
        Http::fake([self::ENDPOINT => Http::response([
            'error' => ['details' => [['message' => 'Sender address is not verified']]],
        ], 400)]);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Sender address is not verified');

        $this->send($this->basicEmail());
    }

    public function test_an_unreadable_error_still_reports_the_status(): void
    {
        Http::fake([self::ENDPOINT => Http::response('Service unavailable', 503)]);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('503');

        $this->send($this->basicEmail());
    }

    /**
     * Symfony allows a message that is nothing but an attachment; ZeptoMail
     * does not. Better a clear error here than a 400 from Zoho.
     */
    public function test_a_message_with_only_an_attachment_is_refused_before_it_is_sent(): void
    {
        Http::fake();

        $email = (new Email)
            ->from(new Address('support@innobles.com'))
            ->to(new Address('ravi@example.com'))
            ->subject('Invoice')
            ->attach('Invoice contents', 'invoice.txt', 'text/plain');

        try {
            $this->send($email);
            $this->fail('A message with no body should not have been sent.');
        } catch (TransportException $e) {
            $this->assertStringContainsString('HTML or a text body', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    /* ------------------------------------------------------ registration */

    public function test_the_driver_is_registered_under_the_name_zeptomail(): void
    {
        config([
            'mail.mailers.zeptomail' => [
                'transport' => 'zeptomail',
                'key' => 'Zoho-enczapikey test-key',
                'endpoint' => self::ENDPOINT,
            ],
        ]);

        $transport = Mail::mailer('zeptomail')->getSymfonyTransport();

        $this->assertInstanceOf(ZeptoMailTransport::class, $transport);
        $this->assertSame('zeptomail', (string) $transport);
    }

    public function test_a_missing_api_key_says_so_plainly(): void
    {
        config([
            'mail.mailers.zeptomail' => ['transport' => 'zeptomail', 'key' => null],
        ]);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('ZOHO_MAIL_API_KEY');

        Mail::mailer('zeptomail')->getSymfonyTransport();
    }
}
