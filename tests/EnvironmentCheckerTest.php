<?php

namespace SilverStripe\EnvironmentCheck\Tests;

use Phockito;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\EnvironmentCheck\EnvironmentCheck;
use SilverStripe\EnvironmentCheck\EnvironmentChecker;
use SilverStripe\EnvironmentCheck\EnvironmentCheckSuite;

/**
 * Class EnvironmentCheckerTest
 *
 * @package environmentcheck
 */
class EnvironmentCheckerTest extends SapphireTest
{
    /**
     * {@inheritDoc}
     * @var bool
     */
    protected $usesDatabase = true;

    /**
     * {@inheritDoc}
     */
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        Phockito::include_hamcrest();

        $logger = Injector::inst()->get(LoggerInterface::class);
        if ($logger instanceof \Monolog\Logger) {
            // It logs to stderr by default - disable
            $logger->pushHandler(new \Monolog\Handler\NullHandler);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function setUp()
    {
        parent::setUp();
        Config::nest();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown()
    {
        Config::unnest();
        parent::tearDown();
    }

    public function testOnlyLogsWithErrors()
    {
        Config::modify()->set(EnvironmentChecker::class, 'log_results_warning', true);
        Config::modify()->set(EnvironmentChecker::class, 'log_results_error', true);
        EnvironmentCheckSuite::register('test suite', new EnvironmentCheckerTest_CheckNoErrors());
        $checker = Phockito::spy(
            EnvironmentChecker::class,
            'test suite',
            'test'
        );

        $response = $checker->index();
        Phockito::verify($checker, 0)->log(\anything(), \anything());
        EnvironmentCheckSuite::reset();
    }

    public function testLogsWithWarnings()
    {
        Config::modify()->set(EnvironmentChecker::class, 'log_results_warning', true);
        Config::modify()->set(EnvironmentChecker::class, 'log_results_error', false);
        EnvironmentCheckSuite::register('test suite', new EnvironmentCheckerTest_CheckWarnings());
        EnvironmentCheckSuite::register('test suite', new EnvironmentCheckerTest_CheckErrors());
        $checker = Phockito::spy(
            EnvironmentChecker::class,
            'test suite',
            'test'
        );

        $response = $checker->index();
        Phockito::verify($checker, 1)->log(containsString('warning'), \anything());
        Phockito::verify($checker, 0)->log(containsString('error'), \anything());
        EnvironmentCheckSuite::reset();
    }

    public function testLogsWithErrors()
    {
        Config::modify()->set(EnvironmentChecker::class, 'log_results_error', false);
        Config::modify()->set(EnvironmentChecker::class, 'log_results_error', true);
        EnvironmentCheckSuite::register('test suite', new EnvironmentCheckerTest_CheckWarnings());
        EnvironmentCheckSuite::register('test suite', new EnvironmentCheckerTest_CheckErrors());
        $checker = Phockito::spy(
            EnvironmentChecker::class,
            'test suite',
            'test'
        );

        $response = $checker->index();
        Phockito::verify($checker, 0)->log(containsString('warning'), \anything());
        Phockito::verify($checker, 1)->log(containsString('error'), \anything());
        EnvironmentCheckSuite::reset();
    }
}

class EnvironmentCheckerTest_CheckNoErrors implements EnvironmentCheck, TestOnly
{
    public function check()
    {
        return [EnvironmentCheck::OK, ''];
    }
}

class EnvironmentCheckerTest_CheckWarnings implements EnvironmentCheck, TestOnly
{
    public function check()
    {
        return [EnvironmentCheck::WARNING, 'test warning'];
    }
}

class EnvironmentCheckerTest_CheckErrors implements EnvironmentCheck, TestOnly
{
    public function check()
    {
        return [EnvironmentCheck::ERROR, 'test error'];
    }
}
