<?php

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class HttpsEntryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.url' => 'https://jmaccr.com']);
    }

    public function test_http_get_and_head_redirect_to_configured_https_origin_and_preserve_path_query_without_running_backend(): void
    {
        foreach (['GET', 'HEAD'] as $method) {
            $called = false;
            $request = Request::create('http://untrusted.example/catalogo/?q=ABC%2001&page=2', $method);
            $response = app(SecurityHeaders::class)->handle($request, function () use (&$called) {
                $called = true;

                return response('This backend must not execute');
            });
            $this->assertSame(301, $response->getStatusCode());
            $this->assertSame('https://jmaccr.com/catalogo/?q=ABC%2001&page=2', $response->headers->get('Location'));
            $this->assertFalse($called);
            $this->assertSame([], $response->headers->getCookies());
        }
    }

    public function test_insecure_actions_remain_rejected_even_with_spoofed_forwarded_headers(): void
    {
        foreach ([['POST', null], ['PUT', null], ['PATCH', null], ['DELETE', null], ['POST', 'GET'], ['POST', 'HEAD']] as [$method, $override]) {
            $called = false;
            $request = Request::create('http://jmaccr.com/admin/catalog/imports', $method, [], [], [], ['HTTP_X_FORWARDED_PROTO' => 'https', 'HTTP_X_FORWARDED_HOST' => 'jmaccr.com']);
            if ($override) {
                $request->headers->set('X-HTTP-METHOD-OVERRIDE', $override);
            }
            try {
                app(SecurityHeaders::class)->handle($request, function () use (&$called) {
                    $called = true;

                    return response('Must not run');
                });
                $this->fail('Insecure action was accepted');
            } catch (HttpException $exception) {
                $this->assertSame(400, $exception->getStatusCode());
                $this->assertSame('Se requiere HTTPS.', $exception->getMessage());
                $this->assertFalse($called);
            }
        }
    }

    public function test_invalid_or_insecure_canonical_configuration_does_not_redirect(): void
    {
        foreach (['http://jmaccr.com', 'javascript:alert(1)', 'https://user:pass@example.test', 'https://jmaccr.com?next=evil', 'https://jmaccr.com#evil', 'https://jmaccr.com/subdirectory', 'not a URL'] as $url) {
            config(['app.url' => $url]);
            try {
                app(SecurityHeaders::class)->handle(Request::create('http://jmaccr.com/'), fn () => response('Must not run'));
                $this->fail('Invalid canonical origin was accepted');
            } catch (HttpException $exception) {
                $this->assertSame(400, $exception->getStatusCode());
            }
        }
    }

    public function test_secure_requests_keep_security_headers_csp_nonce_and_admin_privacy(): void
    {
        $request = Request::create('https://jmaccr.com/admin/catalog/imports/5');
        $response = app(SecurityHeaders::class)->handle($request, fn () => response('Authenticated backend response'));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($response->isRedirect());
        $this->assertSame('max-age=31536000', $response->headers->get('Strict-Transport-Security'));
        $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString("'nonce-".$request->attributes->get('csp_nonce')."'", $response->headers->get('Content-Security-Policy'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }
}
