<?php
namespace Tests\Unit;
use App\Http\Controllers\ClinicalReplacementController;
use App\Models\User;
use App\Services\Clinical\Api\ClinicalRequestContext;
use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Facade,Http};
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** No application boot, database, .env, migrations or external requests. */
final class ClinicalReplacementBridgeTest extends TestCase
{
    private Application $app;
    private ClinicalReplacementController $controller;
    protected function setUp(): void
    {
        parent::setUp();
        $this->app=new Application(dirname(__DIR__,2));
        $this->app->instance('config',new Repository(['clinical_replacement'=>['enabled'=>true], 'services'=>['clinical'=>['url'=>'https://clinical.example.invalid','service_key'=>'synthetic-server-key','identity_transport'=>'headers']]]));
        $factory=new \Illuminate\Routing\ResponseFactory($this->createMock(\Illuminate\Contracts\View\Factory::class),$this->createMock(\Illuminate\Routing\Redirector::class));
        $this->app->instance(\Illuminate\Contracts\Routing\ResponseFactory::class,$factory);
        $this->app->instance(\Illuminate\Http\Client\Factory::class,new \Illuminate\Http\Client\Factory());
        Facade::clearResolvedInstances();Facade::setFacadeApplication($this->app);
        Http::preventStrayRequests();
        $this->controller=new ClinicalReplacementController(new ClinicalRequestContext());
    }
    protected function tearDown(): void { Facade::clearResolvedInstances();Facade::setFacadeApplication(null);parent::tearDown(); }
    private function request(array $input=['operation'=>'tasks.mine','value'=>[]], array $attributes=[]): Request
    {
        $u=new User();$u->forceFill(array_replace(['id'=>11,'business_id'=>41,'branch_id'=>7,'name'=>'Synthetic Clinician','status'=>'active','permissions'=>['View Ward Census']],$attributes));
        $r=Request::create('/clinical/replacement/workflow','POST',[],[],[],['CONTENT_TYPE'=>'application/json','HTTP_X_USER_ID'=>'999','HTTP_X_TENANT_ID'=>'other','HTTP_X_SERVICE_KEY'=>'browser-key'],json_encode($input));
        $r->setUserResolver(fn()=>$u);return $r;
    }
    public function test_server_identity_and_exact_operation_are_forwarded_once(): void
    {
        Http::fake(['*'=>Http::response(['ok'=>true,'result'=>['saved'=>true]],200)]);
        $input=['operation'=>'tasks.execute','value'=>['operationId'=>'original-operation','taskId'=>'original-task']];
        $r=$this->controller->workflow($this->request($input));
        self::assertSame(200,$r->getStatusCode());self::assertTrue($r->getData(true)['result']['saved']);
        Http::assertSent(fn($r)=>$r->url()==='https://clinical.example.invalid/api/v1/replacement/workflow'&&$r->hasHeader('X-Service-Key','synthetic-server-key')&&$r->hasHeader('X-User-Id','11')&&$r->hasHeader('X-Tenant-Id','41')&&$r->data()===$input);
        Http::assertSentCount(1);self::assertStringNotContainsString('synthetic-server-key',$r->getContent());
    }
    public function test_transport_loss_keeps_original_operation_recoverable_and_is_not_retried(): void
    {
        Http::fake(fn()=>throw new \Illuminate\Http\Client\ConnectionException('secret transport detail'));
        $r=$this->controller->workflow($this->request());
        self::assertSame(503,$r->getStatusCode());self::assertFalse($r->getData(true)['definitelyNotCommitted']);self::assertStringNotContainsString('secret',$r->getContent());
    }
    public function test_redirect_and_upstream_access_failure_do_not_leak_content(): void
    {
        Http::fakeSequence()->push('secret redirect',302,['Location'=>'https://elsewhere.invalid'])->push('secret denied',403);
        self::assertSame(503,$this->controller->workflow($this->request())->getStatusCode());
        $r=$this->controller->workflow($this->request());self::assertSame(403,$r->getStatusCode());self::assertSame('ACCESS_REVOKED',$r->getData(true)['code']);Http::assertSentCount(2);
    }
    public function test_only_task_operations_are_forwarded(): void
    {
        Http::fake();
        foreach ([['operation'=>'settings.execute','value'=>[]],['operation'=>'tasks.mine','value'=>[],'actor'=>'substitution']] as $input)self::assertSame(400,$this->controller->workflow($this->request($input))->getStatusCode());
        Http::assertNothingSent();
    }
    public function test_active_authorized_session_is_required(): void
    {
        Http::fake();
        foreach ([['status'=>'suspended'],['permissions'=>[]],['business_id'=>null],['branch_id'=>null]] as $attributes){
            try{$this->controller->workflow($this->request(attributes:$attributes));self::fail('Must refuse');}catch(HttpException $e){self::assertSame(403,$e->getStatusCode());}
        }
        $this->app['config']->set('clinical_replacement.enabled',false);
        try{$this->controller->workflow($this->request());self::fail('Disabled');}catch(HttpException $e){self::assertSame(404,$e->getStatusCode());}
        Http::assertNothingSent();
    }
    public function test_fixed_asset_proxy_and_mime(): void
    {
        Http::fakeSequence()->push('export const isolated=true;',200,['Content-Type'=>'text/javascript'])->push('<html>login</html>',200,['Content-Type'=>'text/html']);
        $r=$this->controller->asset($this->request(),'task-view.mjs');self::assertSame(200,$r->getStatusCode());self::assertSame('nosniff',$r->headers->get('X-Content-Type-Options'));
        self::assertSame(503,$this->controller->asset($this->request(),'task-view.mjs')->getStatusCode());
        try{$this->controller->asset($this->request(),'../.env');self::fail('Traversal');}catch(HttpException $e){self::assertSame(404,$e->getStatusCode());}
        Http::assertSentCount(2);
    }
    public function test_registered_routes_keep_main_session_and_csrf(): void
    {
        $router=new \Illuminate\Routing\Router(new \Illuminate\Events\Dispatcher($this->app),$this->app);$this->app->instance('router',$router);
        $router->middleware('web')->group(dirname(__DIR__,2).'/routes/clinical_replacement.php');
        foreach($router->getRoutes() as $route)self::assertSame(['web','auth','verified'],$route->gatherMiddleware());
        self::assertStringContainsString("require __DIR__.'/clinical_replacement.php'",file_get_contents(dirname(__DIR__,2).'/routes/web.php'));
        // Exercise real CSRF middleware without Laravel's test-environment bypass.
        $csrf=new class($this->app,$this->createMock(\Illuminate\Contracts\Encryption\Encrypter::class)) extends \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken {
            protected $addHttpCookie=false;
            protected function runningUnitTests(){return false;}
        };
        $session=new \Illuminate\Session\Store('isolated',new \Illuminate\Session\ArraySessionHandler(120));$session->put('_token','synthetic-csrf');
        $r=$this->request();$r->setLaravelSession($session);
        try{$csrf->handle($r,fn()=>new \Illuminate\Http\Response('ok'));self::fail('CSRF required');}catch(\Illuminate\Session\TokenMismatchException){self::assertTrue(true);}
        $r->headers->set('X-CSRF-TOKEN','synthetic-csrf');self::assertSame('ok',$csrf->handle($r,fn()=>new \Illuminate\Http\Response('ok'))->getContent());
    }
}
