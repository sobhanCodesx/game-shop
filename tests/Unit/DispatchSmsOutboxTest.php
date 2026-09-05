<?php

namespace Tests\Unit;

use App\Http\Middleware\DispatchSmsOutbox;
use App\Services\Sms\SmsDispatcher;
use Illuminate\Http\Request;
use Mockery;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class DispatchSmsOutboxTest extends TestCase
{
    public function test_provider_dispatch_starts_only_after_the_response_is_created(): void
    {
        $dispatcher = Mockery::spy(SmsDispatcher::class);
        $this->app->instance(SmsDispatcher::class, $dispatcher);
        $middleware = new DispatchSmsOutbox;
        $request = Request::create('/test', 'GET');

        $response = $middleware->handle($request, fn () => new Response('ok'));

        $dispatcher->shouldNotHaveReceived('dispatchDue');

        $middleware->terminate($request, $response);

        $dispatcher->shouldHaveReceived('dispatchDue')->once();
    }
}
