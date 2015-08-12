<?php

namespace BabymarktExt\CronBundle\Tests\Service;

use BabymarktExt\CronBundle\DependencyInjection\BabymarktExtCronExtension;
use BabymarktExt\CronBundle\DependencyInjection\Configuration;
use BabymarktExt\CronBundle\Service\CronEntryGenerator;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Created by PhpStorm.
 * User: nfunke
 * Date: 05.08.15
 * Time: 13:34
 */
class CronEntryGeneratorTest extends \PHPUnit_Framework_TestCase
{

    const ROOT_DIR    = '/root/dir';
    const ENVIRONMENT = 'test';

    /**
     * @var string
     */
    private $root;

    protected $definitionDefaults = [
        'minutes'   => '*',
        'hours'     => '*',
        'days'      => '*',
        'months'    => '*',
        'weekdays'  => '*',
        'enabled'   => true,
        'output'    => [
            'file'   => null,
            'append' => null
        ],
        'command'   => null,
        'arguments' => []
    ];

    protected $defaults = [
        'output' => [
            'file'   => '/dev/null',
            'append' => false
        ]
    ];

    public function testDefaultValues()
    {
        $key = 'cron_def';

        $config = [
            'crons' => [
                $key => [
                    'command' => 'babymarktext:test:command'
                ]
            ]
        ];

        $container   = $this->getContainer($config);
        $definitions = $container->getParameter($this->root . '.definitions');
        $outputConf  = $container->getParameter($this->root . '.options.output');

        $generator = new CronEntryGenerator($definitions, $outputConf, self::ROOT_DIR, self::ENVIRONMENT);
        $entries   = $generator->generateEntries();

        $this->assertCount(1, $entries);
        $this->assertArrayHasKey($key, $entries);
        $this->assertEquals(
            sprintf('* * * * * cd %s; php console --env=%s babymarktext:test:command 2>&1 1>%s',
                self::ROOT_DIR, self::ENVIRONMENT, $outputConf['file']),
            $entries[$key]
        );
    }

    public function testDisabledCron()
    {
        $key = 'cron_def';

        $config = [
            'crons' => [
                $key => [
                    'command' => 'babymarktext:test:command',
                    'enabled' => false
                ]
            ]
        ];

        $container   = $this->getContainer($config);
        $definitions = $container->getParameter($this->root . '.definitions');
        $outputConf  = $container->getParameter($this->root . '.options.output');

        $generator = new CronEntryGenerator($definitions, $outputConf, self::ROOT_DIR, self::ENVIRONMENT);
        $entries   = $generator->generateEntries();

        $this->assertCount(0, $entries);
    }

    public function testIndividualOutputRedirection()
    {
        $key = 'cron_def';

        $config = [
            'crons' => [
                $key => [
                    'command' => 'babymarktext:test:command',
                    'output'  => ['file' => '/var/log/log.log']
                ]
            ]
        ];

        $container   = $this->getContainer($config);
        $definitions = $container->getParameter($this->root . '.definitions');
        $outputConf  = $container->getParameter($this->root . '.options.output');

        $generator = new CronEntryGenerator($definitions, $outputConf, self::ROOT_DIR, self::ENVIRONMENT);
        $entries   = $generator->generateEntries();

        $this->assertStringEndsWith('2>&1 1>/var/log/log.log', $entries[$key]);
    }

    public function testCronInterval()
    {
        $key = 'cron_def';

        $config = [
            'crons' => [
                $key => [
                    'minutes'  => '1',
                    'hours'    => '2',
                    'days'     => '3',
                    'months'   => '4',
                    'weekdays' => '5',
                    'command'  => 'babymarktext:test:command'
                ]
            ]
        ];

        $container   = $this->getContainer($config);
        $definitions = $container->getParameter($this->root . '.definitions');
        $outputConf  = $container->getParameter($this->root . '.options.output');

        $generator = new CronEntryGenerator($definitions, $outputConf, self::ROOT_DIR, self::ENVIRONMENT);
        $entries   = $generator->generateEntries();

        $this->assertStringStartsWith('1 2 3 4 5', $entries[$key]);
    }

    public function testCommandArguments()
    {
        $key = 'cron_def';

        $config = [
            'crons' => [
                $key => [
                    'command'   => 'babymarktext:test:command',
                    'arguments' => ['--arg=5', '-d']
                ]
            ]
        ];

        $container   = $this->getContainer($config);
        $definitions = $container->getParameter($this->root . '.definitions');
        $outputConf  = $container->getParameter($this->root . '.options.output');

        $generator = new CronEntryGenerator($definitions, $outputConf, self::ROOT_DIR, self::ENVIRONMENT);
        $entries   = $generator->generateEntries();

        $this->assertContains('babymarktext:test:command --arg=5 -d', $entries[$key]);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testNoCommandSupplied()
    {
        $key = 'cron_def';

        $config = [
            'crons' => [
                $key => [
                    'command' => 'babymarktext:test:command'
                ]
            ]
        ];

        $container   = $this->getContainer($config);
        $definitions = $container->getParameter($this->root . '.definitions');
        $outputConf  = $container->getParameter($this->root . '.options.output');

        // Remove command to test exception
        unset($definitions[$key]['command']);

        $generator = new CronEntryGenerator($definitions, $outputConf, self::ROOT_DIR, self::ENVIRONMENT);
        $generator->generateEntries();
    }

    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        parent::setUp();

        $this->root = 'babymarkt_ext_cron';
    }


    /**
     * @param array $config
     * @return ContainerBuilder
     */
    protected function getContainer($config = [])
    {
        $ext  = new BabymarktExtCronExtension();
        $cont = new ContainerBuilder();
        $cont->setParameter('kernel.bundles', []);
        $cont->setParameter('kernel.root_dir', self::ROOT_DIR);
        $cont->setParameter('kernel.environment', self::ENVIRONMENT);

        $ext->load([$config], $cont);

        return $cont;
    }
}
