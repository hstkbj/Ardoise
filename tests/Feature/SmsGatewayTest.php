<?php

namespace Tests\Feature;

use App\Services\Sms\SmsGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Fournisseurs SMS : format des requêtes et interprétation des réponses. */
class SmsGatewayTest extends TestCase
{
    protected function gateway(string $driver, array $config): SmsGateway
    {
        config(['ardoise.sms.driver' => $driver, "ardoise.sms.{$driver}" => $config, 'ardoise.sms.default_country_code' => '229']);
        $this->app->forgetInstance(SmsGateway::class);

        return $this->app->make(SmsGateway::class);
    }

    public function test_orange_gets_a_token_then_sends_to_an_international_number(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.orange.com/oauth/v3/token' => Http::response(['access_token' => 'tok-1', 'expires_in' => 3600]),
            'api.orange.com/smsmessaging/*' => Http::response(['outboundSMSMessageRequest' => []], 201),
        ]);

        $sent = $this->gateway('orange', ['client_id' => 'id', 'client_secret' => 'secret', 'sender_number' => '+22500000000'])
            ->send('97 00 00 01', 'Bonjour');

        $this->assertTrue($sent);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/smsmessaging/v1/outbound/tel%3A%2B22500000000/requests')
            && $request->hasHeader('Authorization', 'Bearer tok-1')
            && $request['outboundSMSMessageRequest']['address'] === 'tel:+22997000001'
            && $request['outboundSMSMessageRequest']['outboundSMSTextMessage']['message'] === 'Bonjour');
    }

    public function test_africas_talking_reports_a_rejected_recipient_as_a_failure(): void
    {
        Http::fake(['api.africastalking.com/*' => Http::response(['SMSMessageData' => ['Recipients' => [['status' => 'InvalidPhoneNumber']]]], 201)]);

        $this->assertFalse($this->gateway('africastalking', ['username' => 'ardoise', 'api_key' => 'key'])->send('+22997000001', 'Test'));
    }

    public function test_twilio_uses_basic_auth_and_form_fields(): void
    {
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201)]);

        $this->assertTrue($this->gateway('twilio', ['sid' => 'AC1', 'token' => 'secret', 'from' => '+15550000000'])->send('+22997000001', 'Test'));
        Http::assertSent(fn (Request $request) => $request['To'] === '+22997000001' && $request['From'] === '+15550000000' && $request->hasHeader('Authorization', 'Basic '.base64_encode('AC1:secret')));
    }

    public function test_the_generic_gateway_maps_fields_and_checks_the_success_value(): void
    {
        Http::fake(['sms.example.test/*' => Http::sequence()->push(['status' => 'ok'])->push(['status' => 'error'])]);
        $sms = $this->gateway('http', [
            'url' => 'https://sms.example.test/send', 'format' => 'form', 'auth' => 'header', 'auth_key' => 'X-Key', 'token' => 'k1',
            'fields' => 'to:recipient,message:text', 'extra' => '{"type":"text"}', 'success_path' => 'status', 'success_value' => 'ok',
        ]);

        $this->assertTrue($sms->send('+22997000001', 'Premier'));
        $this->assertFalse($sms->send('+22997000001', 'Second'));
        Http::assertSent(fn (Request $request) => $request['recipient'] === '+22997000001' && $request['text'] === 'Premier' && $request['type'] === 'text' && $request->hasHeader('X-Key', 'k1'));
    }

    public function test_an_incomplete_configuration_sends_nothing(): void
    {
        Http::fake();

        $this->assertFalse($this->gateway('orange', ['client_id' => 'id'])->send('+22997000001', 'Test'));
        Http::assertNothingSent();
    }
}
