<?php


namespace QMan;


class NativeFunctionStub_TestCase extends \PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        global $nativeFunctionMock;
        $nativeFunctionMock = null;
    }

    /**
     * @param array $methods
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getNativeFunctionMock(array $methods)
    {
        global $nativeFunctionMock;

        if (! isset($nativeFunctionMock)) {
            $nativeFunctionMock = $this
                ->getMockBuilder('stdClass')
                ->setMethods($methods)
                ->getMock()
            ;
        }

        return $nativeFunctionMock;
    }
}

$nativeFunctionMock = null;
$mockedNativeFunctions = [
    'extension_loaded',
    'pcntl_sigprocmask'
];

$namespace = __NAMESPACE__;

foreach ($mockedNativeFunctions as $mockedFunction) {
    eval(<<<EOD
namespace {$namespace};

function {$mockedFunction}()
{
    global \$nativeFunctionMock;
    return is_callable([\$nativeFunctionMock, '{$mockedFunction}'])
        ? call_user_func_array([\$nativeFunctionMock, '{$mockedFunction}'], func_get_args())
        : call_user_func_array('{$mockedFunction}', func_get_args());
}
EOD
    );
}
