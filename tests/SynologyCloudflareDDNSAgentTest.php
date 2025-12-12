<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../cloudflare.php';

class ExitException extends Error {}

class TestableSynologyCloudflareDDNSAgent extends SynologyCloudflareDDNSAgent
{
    public $exitMsg = null;

    protected function exitWithSynologyMsg($msg = '')
    {
        $this->exitMsg = $msg;
        throw new ExitException("Exit called with message: $msg");
    }
    
    public function callPrivateMethod($methodName, $args = [])
    {
        $reflection = new ReflectionClass($this);
        $method = $reflection->getMethod($methodName);
        return $method->invokeArgs($this, $args);
    }
}

class SynologyCloudflareDDNSAgentTest extends TestCase
{
    public function testIsValidHostname()
    {
        $mockApi = $this->createMock(CloudflareAPI::class);
        $mockIpify = $this->createMock(Ipify::class);
        
        $mockApi->method('verifyToken')->willReturn(['success' => true]);
        
        $mockApi->method('getZones')->willReturn(['result' => []]);

        $agent = new TestableSynologyCloudflareDDNSAgent('apikey', 'example.com', '1.2.3.4', $mockApi, $mockIpify);
        
        $this->assertTrue($agent->callPrivateMethod('isValidHostname', ['example.com']));
        $this->assertTrue($agent->callPrivateMethod('isValidHostname', ['sub.example.com']));
        $this->assertFalse($agent->callPrivateMethod('isValidHostname', ['-example.com']));
        $this->assertFalse($agent->callPrivateMethod('isValidHostname', ['example.com-']));
    }

    public function testExtractHostnames()
    {
        $mockApi = $this->createMock(CloudflareAPI::class);
        $mockIpify = $this->createMock(Ipify::class);
        $mockApi->method('verifyToken')->willReturn(['success' => true]);
        $mockApi->method('getZones')->willReturn(['result' => []]);

        $agent = new TestableSynologyCloudflareDDNSAgent('apikey', 'example.com', '1.2.3.4', $mockApi, $mockIpify);

        $input = "example.com|sub.example.com|invalid-";
        $expected = ['example.com', 'sub.example.com'];
        
        $this->assertEquals($expected, $agent->callPrivateMethod('extractHostnames', [$input]));
    }
    
    public function testConstructorAuthFailure()
    {
        $mockApi = $this->createMock(CloudflareAPI::class);
        $mockIpify = $this->createMock(Ipify::class);
        
        $mockApi->method('verifyToken')->willReturn(['success' => false]);

        $this->expectException(ExitException::class);
        $this->expectExceptionMessage("Exit called with message: " . SynologyOutput::AUTH_FAILED);

        new TestableSynologyCloudflareDDNSAgent('apikey', 'example.com', '1.2.3.4', $mockApi, $mockIpify);
    }
}
