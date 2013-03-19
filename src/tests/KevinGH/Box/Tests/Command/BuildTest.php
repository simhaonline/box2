<?php

namespace KevinGH\Box\Tests\Command;

use KevinGH\Box\Command\Build;
use KevinGH\Box\Test\CommandTestCase;
use Phar;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

class BuildTest extends CommandTestCase
{
    public function getPrivateKey()
    {
        return array(
            <<<KEY
-----BEGIN RSA PRIVATE KEY-----
Proc-Type: 4,ENCRYPTED
DEK-Info: DES-EDE3-CBC,3FF97F75E5A8F534

TvEPC5L3OXjy4X5t6SRsW6J4Dfdgw0Mfjqwa4OOI88uk5L8SIezs4sHDYHba9GkG
RKVnRhA5F+gEHrabsQiVJdWPdS8xKUgpkvHqoAT8Zl5sAy/3e/EKZ+Bd2pS/t5yQ
aGGqliG4oWecx42QGL8rmyrbs2wnuBZmwQ6iIVIfYabwpiH+lcEmEoxomXjt9A3j
Sh8IhaDzMLnVS8egk1QvvhFjyXyBIW5mLIue6cdEgINbxzRReNQgjlyHS8BJRLp9
EvJcZDKJiNJt+VLncbfm4ZhbdKvSsbZbXC/Pqv06YNMY1+m9QwszHJexqjm7AyzB
MkBFedcxcxqvSb8DaGgQfUkm9rAmbmu+l1Dncd72Cjjf8fIfuodUmKsdfYds3h+n
Ss7K4YiiNp7u9pqJBMvUdtrVoSsNAo6i7uFa7JQTXec9sbFN1nezgq1FZmcfJYUZ
rdpc2J1hbHTfUZWtLZebA72GU63Y9zkZzbP3SjFUSWniEEbzWbPy2sAycHrpagND
itOQNHwZ2Me81MQQB55JOKblKkSha6cNo9nJjd8rpyo/lc/Iay9qlUyba7RO0V/t
wm9ZeUZL+D2/JQH7zGyLxkKqcMC+CFrNYnVh0U4nk3ftZsM+jcyfl7ScVFTKmcRc
ypcpLwfS6gyenTqiTiJx/Zca4xmRNA+Fy1EhkymxP3ku0kTU6qutT2tuYOjtz/rW
k6oIhMcpsXFdB3N9iHT4qqElo3rVW/qLQaNIqxd8+JmE5GkHmF43PhK3HX1PCmRC
TnvzVS0y1l8zCsRToUtv5rCBC+r8Q3gnvGGnT4jrsp98ithGIQCbbQ==
-----END RSA PRIVATE KEY-----
KEY
        ,
            'test'
        );
    }

    public function testBuild()
    {
        $key = $this->getPrivateKey();

        mkdir('one');
        mkdir('two');
        touch('test.phar');
        touch('one/test.php');
        touch('two/test.png');
        file_put_contents('private.key', $key[0]);
        file_put_contents('test.php', '<?php echo "Hello, @name@!\n";');
        file_put_contents('run.php', '<?php require "test.php";');
        file_put_contents('box.json', json_encode(array(
            'alias' => 'test.phar',
            'chmod' => '0755',
            'compactors' => array('Herrera\\Box\\Compactor\\Composer'),
            'files' => 'test.php',
            'finder' => array(array('in' => 'one')),
            'finder-bin' => array(array('in' => 'two')),
            'key' => 'private.key',
            'key-pass' => true,
            'replacements' => array('name' => 'world'),
            'main' => 'run.php',
            'metadata' => array('rand' => $rand = rand()),
            'output' => 'test.phar',
            'stub' => true
        )));

        $tester = $this->getTester();
        $tester->execute(array(
            'command' => 'build'
        ), array(
            'verbosity' => OutputInterface::VERBOSITY_VERBOSE
        ));

        $dir = $this->dir . DIRECTORY_SEPARATOR;
        $ds = DIRECTORY_SEPARATOR;

        $this->assertEquals(
            <<<OUTPUT
? Removing previously built Phar...
* Building...
? Output path: {$dir}test.phar
? Setting replacement values...
  + @name@: world
? Registering compactors...
  + Herrera\\Box\\Compactor\\Composer
? Adding files...
  + {$dir}test.php
? Adding Finder files...
  + {$dir}one{$ds}test.php
? Adding binary Finder files...
  + {$dir}two{$ds}test.png
? Adding main file: {$dir}run.php
? Generating new stub...
? Setting metadata...
? Signing using a private key...
? Setting file permissions...
* Done.

OUTPUT
            ,
            $this->getOutput($tester)
        );

        $this->assertEquals(
            'Hello, world!',
            exec('php test.phar')
        );

        $phar = new Phar('test.phar');

        $this->assertEquals(array('rand' => $rand), $phar->getMetadata());

        unset($phar);
    }

    public function testBuildStubFile()
    {
        touch('test.php');
        file_put_contents('stub.php', '<?php echo "Hello!"; __HALT_COMPILER();');
        file_put_contents('box.json', json_encode(array(
            'alias' => 'test.phar',
            'files' => 'test.php',
            'output' => 'test.phar',
            'stub' => 'stub.php'
        )));

        $tester = $this->getTester();
        $tester->execute(array(
            'command' => 'build'
        ), array(
            'verbosity' => OutputInterface::VERBOSITY_VERBOSE
        ));

        $dir = $this->dir . DIRECTORY_SEPARATOR;

        $this->assertEquals(
            <<<OUTPUT
* Building...
? Output path: {$dir}test.phar
? Adding files...
  + {$dir}test.php
? Using stub file: {$dir}stub.php
* Done.

OUTPUT
            ,
            $this->getOutput($tester)
        );
    }

    public function testBuildDefaultStub()
    {
        touch('test.php');
        file_put_contents('box.json', json_encode(array(
            'alias' => 'test.phar',
            'files' => 'test.php',
            'output' => 'test.phar'
        )));

        $tester = $this->getTester();
        $tester->execute(array(
            'command' => 'build'
        ), array(
            'verbosity' => OutputInterface::VERBOSITY_VERBOSE
        ));

        $dir = $this->dir . DIRECTORY_SEPARATOR;

        $this->assertEquals(
            <<<OUTPUT
* Building...
? Output path: {$dir}test.phar
? Adding files...
  + {$dir}test.php
? Using default stub.
* Done.

OUTPUT
            ,
            $this->getOutput($tester)
        );
    }

    public function testBuildCompressed()
    {
        file_put_contents('test.php', '<?php echo "Hello!";');
        file_put_contents('box.json', json_encode(array(
            'alias' => 'test.phar',
            'compression' => 'GZ',
            'files' => 'test.php',
            'main' => 'test.php',
            'output' => 'test.phar',
            'stub' => true
        )));

        $tester = $this->getTester();
        $tester->execute(array(
            'command' => 'build'
        ), array(
            'verbosity' => OutputInterface::VERBOSITY_VERBOSE
        ));

        $dir = $this->dir . DIRECTORY_SEPARATOR;

        $this->assertEquals(
            <<<OUTPUT
* Building...
? Output path: {$dir}test.phar
? Adding files...
  + {$dir}test.php
? Setting main file: {$dir}test.php
? Generating new stub...
? Compressing...
* Done.

OUTPUT
            ,
            $this->getOutput($tester)
        );

        $this->assertEquals(
            'Hello!',
            exec('php test.phar')
        );
    }

    public function testBuildQuiet()
    {
        mkdir('one');
        file_put_contents('one/test.php', '<?php echo "Hello!";');
        file_put_contents('run.php', '<?php require "one/test.php";');
        file_put_contents('box.json', json_encode(array(
            'alias' => 'test.phar',
            'finder' => array(array('in'  => 'one')),
            'main' => 'run.php',
            'output' => 'test.phar',
            'stub' => true
        )));

        $tester = $this->getTester();
        $tester->execute(array('command' => 'build'));

        $this->assertEquals("Building...\n", $this->getOutput($tester));
    }

    protected function getCommand()
    {
        return new Build();
    }

    protected function setUp()
    {
        parent::setUp();

        $this->app->getHelperSet()->set(new FixedResponse('test'));
    }
}

class FixedResponse extends DialogHelper
{
    private $response;

    public function __construct($response)
    {
        $this->response = $response;
    }

    public function askHiddenResponse(
        OutputInterface $output,
        $question,
        $fallback = true
    ){
        return $this->response;
    }
}