<?php
use \Curl\Curl;

class IndexTest extends PHPUnit_Framework_TestCase
{
    
    public function testStatus()
    {
        $curl = new Curl();
        $curl->setCookie('app', 'hash');
        $curl->get('http://localhost:8080/');
        $this->assertEquals("HTTP/1.1 200 OK", $curl->responseHeaders['Status-Line']);
    }
    
    public function testContentType()
    {
        $curl = new Curl();
        $curl->setCookie('app', 'hash');
        $curl->get('http://localhost:8080/');
        $this->assertEquals("application/json;charset=utf-8", $curl->responseHeaders['Content-Type']);
    }
    
    public function testCount()
    {
        $curl = new Curl();
        $curl->setCookie('app', 'hash');
        $curl->get('http://localhost:8080/');
        $this->assertTrue(count($curl->response)>0);
    }
    
}