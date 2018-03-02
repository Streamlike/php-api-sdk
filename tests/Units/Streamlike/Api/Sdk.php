<?php

namespace Tests\Units\Streamlike\Api;

use mageekguy\atoum;
use Streamlike\Api\Sdk as Tested;

class Sdk extends atoum
{
    public function testInstance()
    {
        $this
            ->given($this->newTestedInstance)
            ->then
            ->object($this->newTestedInstance)
                ->isInstanceOfTestedClass()
        ;
    }

    public function testTokenInit()
    {
        $this
            ->variable($this->newTestedInstance->getToken())
                ->isNull()
            ->exception(function () {
                new Tested(123);
            })->isInstanceOf('\InvalidArgumentException')
        ;

        $tested = new Tested('mytoken');
        $this
            ->string($tested->getToken())
                ->isIdenticalTo('mytoken')
        ;
    }

    public function testHostInit()
    {
        $class = new \ReflectionClass('Streamlike\Api\Sdk');
        $property = $class->getProperty('host');
        $property->setAccessible(true);

        $tested = new Tested();
        $this
            ->string($property->getValue($tested))
                ->isIdenticalTo('https://api.streamlike.com/')
            ->exception(function () use ($tested) {
                $tested->setHost(null);
            })->isInstanceOf('\InvalidArgumentException')
            ->exception(function () use ($tested) {
                $tested->setHost('plop');
            })->isInstanceOf('\InvalidArgumentException')
            ->exception(function () use ($tested) {
                $tested->setHost(1234);
            })->isInstanceOf('\InvalidArgumentException')
            ->variable($tested->setHost('http://plop.streamlike.com/'))
                ->isIdenticalTo($tested)
            ->string($property->getValue($tested))
                ->isIdenticalTo('http://plop.streamlike.com/')
            ->variable($tested->setHost('http://plop2.streamlike.com'))
                ->isIdenticalTo($tested)
            ->string($property->getValue($tested))
                ->isIdenticalTo('http://plop2.streamlike.com/')
        ;
    }

    public function testBuildPayload()
    {
        $class = new \ReflectionClass('Streamlike\Api\Sdk');
        $method = $class->getMethod('buildPayload');
        $method->setAccessible(true);

        $existingFilePath = __DIR__.'/../../../fixtures/text.txt';

        $this
            ->exception(function () use ($method) {
                $method->invoke(null, [], []);
            })
                ->isInstanceOf('\InvalidArgumentException')
            ->string($method->invoke(null, ['plop' => 'toto'], []))
                ->isIdenticalTo('{"plop":"toto"}')

            // with files
            ->exception(function () use ($method) {
                $method->invoke(null, [], ['plop' => '/tmp/coucou']);
            })
                ->isInstanceOf('\Streamlike\Api\Exception\Exception')
            ->string($method->invoke(null, [], ['plop' => $existingFilePath]))
                ->isIdenticalTo('
--STREAMLIKEBOUND
Content-Type: application/json; charset=UTF-8
Content-Disposition: form-data; name="resource"

[]

--STREAMLIKEBOUND
Content-Transfer-Encoding: binary
Content-Disposition: form-data; name="plop"; filename="text.txt"

plop

plop
--STREAMLIKEBOUND--')
            ->string($method->invoke(null, [], ['plop' => $existingFilePath, 'toto' => ['tata' => $existingFilePath]]))
                ->isIdenticalTo('
--STREAMLIKEBOUND
Content-Type: application/json; charset=UTF-8
Content-Disposition: form-data; name="resource"

[]

--STREAMLIKEBOUND
Content-Transfer-Encoding: binary
Content-Disposition: form-data; name="plop"; filename="text.txt"

plop

plop
--STREAMLIKEBOUND
Content-Transfer-Encoding: binary
Content-Disposition: form-data; name="toto[tata]"; filename="text.txt"

plop

plop
--STREAMLIKEBOUND--')
        ;
    }

    public function testParseResponse()
    {
        $class = new \ReflectionClass('Streamlike\Api\Sdk');
        $method = $class->getMethod('parseResponse');
        $method->setAccessible(true);

        $this
            ->exception(function () use ($method) {
                $method->invoke(null,
                    401, ''
                );
            })
                ->isInstanceOf('Streamlike\Api\Exception\AuthRequiredException')
                ->hasCode(401)
                ->hasMessage(401)
            ->exception(function () use ($method) {
                $method->invoke(null,
                    403, ''
                );
            })
                ->isInstanceOf('Streamlike\Api\Exception\AuthRequiredException')
                ->hasCode(403)
                ->hasMessage(403)
            ->exception(function () use ($method) {
                $method->invoke(null,
                    400, ''
                );
            })
                ->isInstanceOf('Streamlike\Api\Exception\InvalidInputException')
                ->hasCode(400)
                ->hasMessage(400)
                ->array($this->exception->getErrors())
                    ->isEmpty()
            ->exception(function () use ($method) {
                $method->invoke(null,
                    400, '{"message": "plop", "data": {"errors": ["TOTO", "TATA"]}}'
                );
            })
                ->isInstanceOf('Streamlike\Api\Exception\InvalidInputException')
                ->hasCode(400)
                ->hasMessage('plop')
                ->array($this->exception->getErrors())
                    ->isIdenticalTo(['TOTO', 'TATA'])
            ->exception(function () use ($method) {
                $method->invoke(null,
                    407, ''
                );
            })
                ->isInstanceOf('Streamlike\Api\Exception\Exception')
                ->hasCode(407)
                ->hasMessage('Invalid request')
            ->exception(function () use ($method) {
                $method->invoke(null,
                    500, ''
                );
            })
                ->isInstanceOf('Streamlike\Api\Exception\Exception')
                ->hasCode(500)
                ->hasMessage('Server error')
            ->boolean($method->invoke(null, 204, ''))
                ->isTrue()
            ->array($method->invoke(null, 200, '{"plop": "toto"}'))
                ->isIdenticalTo([
                    'plop' => 'toto',
                ])
            ->exception(function () use ($method) {
                $method->invoke(null,
                    200, 'coucou'
                );
            })
                ->isInstanceOf('Streamlike\Api\Exception\Exception')
        ;
    }

    /**
     * @dataProvider getArrayToFormData
     */
    public function testArrayToForm(array $input, array $expected)
    {
        $class = new \ReflectionClass('Streamlike\Api\Sdk');
        $method = $class->getMethod('arrayToForm');
        $method->setAccessible(true);

        $this
            ->array($method->invoke(null, $input))
                ->isIdenticalTo($expected)
        ;
    }

    public function getArrayToFormData()
    {
        return [
            [
                ['plop' => 'toto'],
                ['plop' => 'toto'],
            ],
            [
                ['plop' => ['toto']],
                ['plop[0]' => 'toto'],
            ],
            [
                ['plop' => [['plop' => 'toto']]],
                ['plop[0][plop]' => 'toto'],
            ],
            [
                ['toto' => [['toto1' => 'v1', 'toto2' => 'v2']]],
                ['toto[0][toto1]' => 'v1', 'toto[0][toto2]' => 'v2'],
            ],
            [
                ['toto' => [['toto1' => 'v1', 'toto2' => 'v2']], 'tata' => [['tata1' => 'a1', 'tata2' => 'v2']]],
                ['toto[0][toto1]' => 'v1', 'toto[0][toto2]' => 'v2', 'tata[0][tata1]' => 'a1', 'tata[0][tata2]' => 'v2'],
            ],
        ];
    }
}
