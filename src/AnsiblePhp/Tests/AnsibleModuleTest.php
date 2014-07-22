<?php
namespace AnsiblePhp\Tests;

use AnsiblePhp\AnsibleModule;
use Hampel\Json\Json;

class AnsibleModuleTest extends TestCase
{
    private $argFiles = array();

    /**
     * @expectedException AnsiblePhp\TypeException
     * @expectedExceptionMessage ___ is not a valid argument type
     */
    public function testConstructorInvalidArgumentType()
    {
        $this->createArgumentsFile(array(
            'an_arg' => 'some value',
        ));

        new AnsibleModule(array(
            'an_arg' => array('type' => '___'),
        ));
    }

    /**
     * @expectedException AnsiblePhp\InvalidArgumentException
     * @expectedExceptionMessage Argument keyword "an_arg" is not an array
     */
    public function testConstructorNonArrayArgumentInSpec()
    {
        $this->createArgumentsFile(array('an_arg' => 'some value'));
        new AnsibleModule(array('an_arg' => null));
    }

    /**
     * @expectedException AnsiblePhp\ValidationException
     * @expectedExceptionMessage A boolean value should be one of the following values: yes, no, true, false. Got "some value"
     */
    public function testConstructorInvalidArgumentForBoolean()
    {
        $this->createArgumentsFile(array(
            'an_arg' => 'some value',
        ));
        new AnsibleModule(array(
            'an_arg' => array('type' => 'bool'),
        ));
    }

    /**
     * @expectedException AnsiblePhp\ValidationException
     * @expectedExceptionMessage Directory "some value" does not exist (key: "an_arg")
     */
    public function testConstructorInvalidArgumentForDirectory()
    {
        $this->createArgumentsFile(array(
            'an_arg' => 'some value',
        ));
        new AnsibleModule(array(
            'an_arg' => array('type' => 'directory'),
        ));
    }

    /**
     * @expectedException AnsiblePhp\ValidationException
     * @expectedExceptionMessage Expected a numberic value to convert to float (key: "an_arg")
     */
    public function testConstructorInvalidArgumentForFloat()
    {
        $this->createArgumentsFile(array(
            'an_arg' => 'some value',
        ));
        new AnsibleModule(array(
            'an_arg' => array('type' => 'float'),
        ));
    }

    /**
     * @expectedException AnsiblePhp\ValidationException
     * @expectedExceptionMessage Expected a numberic value to convert to integer (key: "an_arg")
     */
    public function testConstructorInvalidArgumentForInteger()
    {
        $this->createArgumentsFile(array(
            'an_arg' => 'some value',
        ));
        new AnsibleModule(array(
            'an_arg' => array('type' => 'int'),
        ));
    }

    /**
     * @expectedException AnsiblePhp\ValidationException
     * @expectedExceptionMessage Expected numeric argument for key "an_arg"
     */
    public function testConstructorInvalidArgumentForNumber()
    {
        $this->createArgumentsFile(array(
            'an_arg' => 'some value',
        ));
        new AnsibleModule(array(
            'an_arg' => array('type' => 'number'),
        ));
    }

    /**
     * @expectedException AnsiblePhp\ValidationException
     * @expectedExceptionMessage Expected list but expanding list argument failed (key: "an_arg")
     */
    public function testConstructorInvalidArgumentForList()
    {
        $this->createArgumentsFile(array(
            'an_arg' => ',',
        ));
        new AnsibleModule(array(
            'an_arg' => array('type' => 'list'),
        ));
    }

    /**
     * @expectedException AnsiblePhp\ValidationException
     * @expectedExceptionMessage Expected valid URI for key "an_arg"
     */
    public function testConstructorInvalidArgumentForUri()
    {
        if (!function_exists('openssl_random_pseudo_bytes')) {
            $this->markTestSkipped('Function openssl_random_pseudo_bytes() not available');
        }

        $this->createArgumentsFile(array(
            'an_arg' => openssl_random_pseudo_bytes(100),
        ));
        new AnsibleModule(array(
            'an_arg' => array('type' => 'uri'),
        ));
    }

    public function testConstructorBooleanStrings()
    {
        $this->createArgumentsFile(array(
            'one' => 'yes',
            'two' => 'true',
            'three' => 'no',
            'four' => 'false',
        ));
        $spec = array(
            'one' => array('type' => 'bool'),
            'two' => array('type' => 'bool'),
            'three' => array('type' => 'bool'),
            'four' => array('type' => 'bool'),
        );

        $mod = new AnsibleModule($spec);

        foreach ($spec as $key => $_) {
            $this->assertInternalType('bool', $mod->params[$key]);
        }

        foreach (array('one', 'two') as $key) {
            $this->assertSame(true, $mod->params[$key]);
        }
        foreach (array('three', 'four') as $key) {
            $this->assertSame(false, $mod->params[$key]);
        }
    }

    public function testConstructorTypecasts() {
        $this->createArgumentsFile(array(
            'a' => '1',
            'b' => '2.3',
        ));
        $mod = new AnsibleModule(array(
            'a' => array('type' => 'int'),
            'b' => array('type' => 'float'),
        ));
        $this->assertSame(1, $mod->params['a']);
        $this->assertInternalType('float', $mod->params['b']);
        // Unfortunately there is rounding here (which is why isSame() is not used), so never not use float for money (use string)!
        $this->assertEquals(2.3, $mod->params['b']);
    }

    public function testConstructorStringTrimming()
    {
        $this->createArgumentsFile(array(
            'an_arg' => 'my string arg ',
        ));
        $mod = new AnsibleModule(array('an_arg' => array()));
        $this->assertSame('my string arg', $mod->params['an_arg']);

        $mod = new AnsibleModule(array('an_arg' => array()), false);
        $this->assertSame('my string arg ', $mod->params['an_arg']);
    }

    /**
     * @expectedException AnsiblePhp\ValidationException
     * @expectedExceptionMessage Argument "another_arg" is required
     */
    public function testConstructorRequiredArgument()
    {
        $this->createArgumentsFile(array(
            'an_arg' => 'my string arg ',
        ));
        new AnsibleModule(array(
            'an_arg' => array(),
            'another_arg' => array('required' => true),
        ));
    }

    public function testExitJsonNotChanged()
    {
        $this->expectOutputString('{"changed":false}');

        $this->createArgumentsFile(array(
            'an_arg' => 'my string arg ',
        ));
        $mock = $this->getMock(
            'AnsiblePhp\AnsibleModule',
            array('terminate'),
            array(
                array('an_arg' => array()),
            )
        );
        $mock->expects($this->once())
             ->method('terminate')
             ->with($this->identicalTo(0));

        $mock->exitJson();
    }

    public function testExitJsonChanged()
    {
        $this->expectOutputString('{"changed":true}');

        $this->createArgumentsFile(array(
            'an_arg' => 'my string arg ',
        ));
        $mock = $this->getMock(
            'AnsiblePhp\AnsibleModule',
            array('terminate'),
            array(
                array('an_arg' => array()),
            )
        );
        $mock->expects($this->once())
             ->method('terminate')
             ->with($this->identicalTo(0));

        $mock->exitJson(array('changed' => true));
    }

    public function testFailJson()
    {
        $this->expectOutputString('{"failed":true}');

        $this->createArgumentsFile(array(
            'an_arg' => 'my string arg ',
        ));
        $mock = $this->getMock(
            'AnsiblePhp\AnsibleModule',
            array('terminate'),
            array(
                array('an_arg' => array()),
            )
        );
        $mock->expects($this->once())
             ->method('terminate')
             ->with($this->identicalTo(1));

        $mock->failJson();
    }

    /**
     * @expectedException Hampel\Json\JsonException
     */
    public function testDecodeJsonFailure()
    {
        $this->createArgumentsFile(array(
            'an_arg' => 'my string arg ',
        ));
        $mod = new AnsibleModule(array(
            'an_arg' => array(),
        ));
        $mod->decodeJson('{badjson}');
    }

    public function testDecodeJson()
    {
        $this->createArgumentsFile(array(
            'an_arg' => 'my string arg ',
        ));
        $mod = new AnsibleModule(array(
            'an_arg' => array(),
        ));
        $this->assertEquals(array('good' => 'json'), $mod->decodeJson('{"good":"json"}', true));
    }

    public function tearDown()
    {
        foreach ($this->argFiles as $file) {
            unlink($file);
        }
    }

    private function createArgumentsFile(array $args)
    {
        global $argv;

        $outfile = tempnam(sys_get_temp_dir(), 'ansible-php-tests_');
        $out = '';

        // Required argument
        $args['ansible_php'] = realpath(dirname(__FILE__).'/../../..');

        foreach ($args as $key => $value) {
            if (!is_scalar($value)) {
                $value = Json::encode($value);
            }

            $out[] = sprintf('%s=%s', $key, $value);
        }

        $this->argFiles[] = $outfile;
        $argv[] = $outfile;
        file_put_contents($outfile, join(' ', $out), LOCK_EX);
    }
}
