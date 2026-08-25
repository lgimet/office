<?php
namespace Tests\Unit;
use App\Services\Oidc\LocalReturnToValidator;
use PHPUnit\Framework\TestCase;
final class LocalReturnToValidatorTest extends TestCase
{
    /** @dataProvider returnToCases */
    public function testReturnToIsStrictlyLocal(string $value,string $expected):void { self::assertSame($expected,(new LocalReturnToValidator())->validate($value)); }
    public static function returnToCases():array { return [['/dashboard','/dashboard'],['/clients?id=1','/clients?id=1'],['https://evil.example','/dashboard'],['//evil.example','/dashboard'],['/\\evil.example','/dashboard'],['/\\\\evil.example','/dashboard'],['\\evil.example','/dashboard'],['javascript:alert(1)','/dashboard'],['/clients#fragment','/dashboard'],["/clients\rX-Test: injected",'/dashboard'],["/clients\nX-Test: injected",'/dashboard']]; }
}
